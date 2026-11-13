import type { Metadata } from "next";
import { Suspense } from "react";
import { DetailPageSkeleton } from "@/components/shared/DetailPageSkeleton";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft, ArrowRight, Check } from "lucide-react";
import { regionLabels } from "@/data/labels";
import { getDestination, getDestinations, getPackagesForDestination, getSite } from "@/lib/cms";
import { PageHero } from "@/components/shared/PageHero";
import { CtaBand } from "@/components/shared/CtaBand";
import { PackageCard } from "@/components/packages/PackageCard";
import { DestinationFacts } from "@/components/destinations/DestinationFacts";
import { DestinationGallery } from "@/components/destinations/DestinationGallery";
import { ButtonLink } from "@/components/ui/Button";
import { Container, Section, SectionHeading } from "@/components/ui/primitives";
import { Reveal } from "@/components/ui/Reveal";
import { absoluteUrl, cn, nameMap, pad2 } from "@/lib/utils";

const PLACEHOLDER = "__placeholder__";

export async function generateStaticParams() {
  const destinations = await getDestinations();
  // cacheComponents requires at least one entry (the placeholder renders the 404 page).
  if (destinations.length === 0) return [{ slug: PLACEHOLDER }];
  return destinations.map((d) => ({ slug: d.slug }));
}

export async function generateMetadata({ params }: PageProps<"/destinations/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const [destination, site] = await Promise.all([getDestination(slug), getSite()]);
  if (!destination) return { title: "Destination not found" };

  const { name, tagline, description, image } = destination;
  const title = `${name} — ${tagline}`;
  return {
    title,
    description,
    alternates: { canonical: `/destinations/${slug}` },
    openGraph: {
      type: "article",
      title: `${title} · ${site.name}`,
      description,
      url: `/destinations/${slug}`,
      images: [{ url: image, width: 1600, height: 1067, alt: `${name}, ${regionLabels[destination.region]}` }],
    },
    twitter: { card: "summary_large_image", title, description, images: [image] },
  };
}

export default function DestinationPage({ params }: PageProps<"/destinations/[slug]">) {
  // Param-dependent content sits inside <Suspense>: Cache Components may regenerate this route's
  // static shell at runtime (after a CMS edit), when no slug is known. Known slugs stay fully prerendered.
  return (
    <Suspense fallback={<DetailPageSkeleton />}>
      <DestinationContent params={params} />
    </Suspense>
  );
}

async function DestinationContent({ params }: { params: PageProps<"/destinations/[slug]">["params"] }) {
  const { slug } = await params;
  const [destinations, trips, site] = await Promise.all([
    getDestinations(),
    getPackagesForDestination(slug),
    getSite(),
  ]);
  const destination = destinations.find((d) => d.slug === slug);
  if (!destination) notFound();

  const { name, displayName, region, tagline, description, image, gallery, highlights, thingsToDo, geo } =
    destination;
  const regionLabel = regionLabels[region];
  const names = nameMap(destinations);

  const i = destinations.findIndex((d) => d.slug === slug);
  const prev = destinations[(i - 1 + destinations.length) % destinations.length];
  const next = destinations[(i + 1) % destinations.length];

  const url = `${site.url}/destinations/${slug}`;
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "TouristDestination",
    name,
    description,
    url,
    image: [image, ...gallery].map((src) => absoluteUrl(src, site.url)),
    geo: { "@type": "GeoCoordinates", latitude: geo.lat, longitude: geo.lng, elevation: destination.altitudeM },
    containedInPlace: { "@type": "Place", name: `${regionLabel}, India` },
    includesAttraction: highlights.map((h) => ({ "@type": "TouristAttraction", name: h })),
    provider: { "@type": "TravelAgency", name: site.name, url: site.url },
  };

  /*
   * Below-the-fold sections skip layout/paint until they near the viewport. `auto` makes the
   * browser remember the real size once rendered; the px values are rough phone / desktop heights.
   * Safe here: no sticky children or pinned ScrollTriggers inside these sections.
   */
  const lazySection = "[content-visibility:auto]";

  let section = 0;
  const num = () => pad2(++section);

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }}
      />

      <PageHero
        eyebrow={regionLabel}
        title={displayName}
        subtitle={tagline}
        image={image}
        imageAlt={`${name}, ${regionLabel}`}
      />

      {/* Facts strip — a white card overlapping the hero's bottom edge. Can be above the fold,
          so it is visible in the server HTML (no JS reveal). */}
      <Container className="relative z-10 -mt-8 sm:-mt-10">
        <h2 className="sr-only">{name} at a glance</h2>
        <DestinationFacts destination={destination} />
      </Container>

      {/* Overview + highlights */}
      <Section>
        <Container>
          <div className="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-14 [&>*]:min-w-0">
            <Reveal className="lg:col-span-7">
              <SectionHeading eyebrow={`${num()} — Overview`} title={`About ${name}`} />
              <p className="mt-6 max-w-2xl text-base leading-relaxed text-ink/85 text-pretty sm:text-[17px]">
                {description}
              </p>
            </Reveal>
            <Reveal className="lg:col-span-5" delay={0.1}>
              <div className="rounded-2xl border border-line bg-surface p-6 shadow-soft sm:p-7">
                <h2 className="font-display text-xl leading-tight font-semibold text-ink">Highlights</h2>
                <ul role="list" className="mt-5 space-y-3.5">
                  {highlights.map((h) => (
                    <li key={h} className="flex items-start gap-3">
                      <span
                        aria-hidden
                        className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-leaf/12 text-leaf"
                      >
                        <Check className="size-3.5" strokeWidth={2.5} />
                      </span>
                      <span className="min-w-0 text-[15px] leading-snug font-medium break-words text-ink">{h}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </Reveal>
          </div>
        </Container>
      </Section>

      {/* Things to do */}
      {thingsToDo.length > 0 && (
        <Section
          className={cn(
            "bg-night-2",
            lazySection,
            "[contain-intrinsic-size:auto_1600px] sm:[contain-intrinsic-size:auto_1100px] lg:[contain-intrinsic-size:auto_900px]",
          )}
        >
          <Container>
            <Reveal>
              <SectionHeading eyebrow={`${num()} — Things to do`} title={`What to do in ${name}`} />
            </Reveal>
            <ol role="list" className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3">
              {thingsToDo.map((t, n) => (
                <Reveal as="li" key={t} delay={Math.min(n, 4) * 0.06} className="h-full min-w-0">
                  <div className="group flex h-full items-start gap-4 rounded-2xl border border-line bg-surface p-5 shadow-soft transition-[box-shadow,transform] duration-500 ease-out-expo hover:-translate-y-0.5 hover:shadow-lift motion-reduce:hover:translate-y-0 sm:p-6">
                    <span
                      aria-hidden
                      className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/8 font-display text-base font-semibold text-accent tabular-nums transition-colors duration-500 group-hover:bg-primary group-hover:text-white"
                    >
                      {pad2(n + 1)}
                    </span>
                    <p className="min-w-0 pt-2 text-[15px] leading-snug font-medium text-ink text-pretty sm:text-base">{t}</p>
                  </div>
                </Reveal>
              ))}
            </ol>
          </Container>
        </Section>
      )}

      {/* Gallery */}
      {gallery.length > 0 && (
        <Section
          className={cn(
            lazySection,
            "[contain-intrinsic-size:auto_2400px] sm:[contain-intrinsic-size:auto_1500px] lg:[contain-intrinsic-size:auto_1200px]",
          )}
        >
          <Container>
            <Reveal className="mb-10">
              <SectionHeading eyebrow={`${num()} — Gallery`} title={`${name} in pictures`} />
            </Reveal>
            <DestinationGallery images={gallery} name={name} />
          </Container>
        </Section>
      )}

      {/* Packages */}
      {trips.length > 0 && (
        <Section
          className={cn(
            "pt-0 sm:pt-0",
            lazySection,
            "[contain-intrinsic-size:auto_1800px] sm:[contain-intrinsic-size:auto_1200px] lg:[contain-intrinsic-size:auto_800px]",
          )}
        >
          <Container>
            <Reveal className="mb-10 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
              <SectionHeading eyebrow={`${num()} — Trips`} title={`Trips that include ${name}`} />
              <ButtonLink href="/packages" variant="outline" size="md" className="self-start sm:self-auto">
                All packages
                <ArrowRight aria-hidden className="size-4" />
              </ButtonLink>
            </Reveal>
            <ul role="list" className="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 [&>li]:min-w-0">
              {trips.map((pkg) => (
                <li key={pkg.slug}>
                  <PackageCard pkg={pkg} names={names} />
                </li>
              ))}
            </ul>
          </Container>
        </Section>
      )}

      {/* Prev / next */}
      <nav aria-label="More destinations" className="border-y border-line bg-surface">
        <Container className="grid grid-cols-2">
          <Link
            href={`/destinations/${prev.slug}`}
            rel="prev"
            className="group flex min-w-0 flex-col gap-2 py-8 pr-3 sm:gap-3 sm:py-12 sm:pr-8"
          >
            <span className="label flex items-center gap-2">
              <ArrowLeft
                aria-hidden
                className="size-3.5 transition-transform duration-500 ease-out-expo group-hover:-translate-x-1"
              />
              Previous
            </span>
            <span className="font-display text-2xl leading-tight font-semibold break-words text-ink transition-colors duration-500 group-hover:text-accent sm:text-[1.75rem] lg:text-[2rem]">
              {prev.name}
            </span>
            <span className="text-xs text-muted text-pretty sm:text-sm">{prev.tagline}</span>
          </Link>
          <Link
            href={`/destinations/${next.slug}`}
            rel="next"
            className="group flex min-w-0 flex-col items-end gap-2 border-l border-line py-8 pl-3 text-right sm:gap-3 sm:py-12 sm:pl-8"
          >
            <span className="label flex items-center gap-2">
              Next
              <ArrowRight
                aria-hidden
                className="size-3.5 transition-transform duration-500 ease-out-expo group-hover:translate-x-1"
              />
            </span>
            <span className="font-display text-2xl leading-tight font-semibold break-words text-ink transition-colors duration-500 group-hover:text-accent sm:text-[1.75rem] lg:text-[2rem]">
              {next.name}
            </span>
            <span className="text-xs text-muted text-pretty sm:text-sm">{next.tagline}</span>
          </Link>
        </Container>
      </nav>

      <CtaBand title={`Plan your ${name} trip`} />
    </>
  );
}
