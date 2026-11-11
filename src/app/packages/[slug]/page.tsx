import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowRight, ArrowUpRight, Check, ChevronRight, X } from "lucide-react";
import type { TourPackage } from "@/types";
import { packages, getPackage, categoryLabels } from "@/data/packages";
import { getDestination, regionLabels } from "@/data/destinations";
import { site } from "@/data/site";
import { cn, formatINR, pad2 } from "@/lib/utils";
import { Container, Eyebrow } from "@/components/ui/primitives";
import { Reveal } from "@/components/ui/Reveal";
import { CtaBand } from "@/components/shared/CtaBand";
import { PACKAGE_CARD_SIZES, PackageCard, destinationChain } from "@/components/packages/PackageCard";
import { ItineraryTimeline } from "@/components/packages/ItineraryTimeline";
import { BookingSidebar } from "@/components/packages/BookingSidebar";

const PLACEHOLDER = "__placeholder__";

/** Skip layout/paint of long below-the-fold blocks until they approach the viewport. Never on the sticky column. */
const deferRender = "[content-visibility:auto]";

export function generateStaticParams() {
  // cacheComponents requires at least one entry.
  if (packages.length === 0) return [{ slug: PLACEHOLDER }];
  return packages.map((p) => ({ slug: p.slug }));
}

export async function generateMetadata({ params }: PageProps<"/packages/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const pkg = getPackage(slug);
  if (!pkg) return { title: "Package not found", robots: { index: false } };

  const chain = destinationChain(pkg).join(", ");
  const description = `${pkg.nights} nights / ${pkg.days} days · ${chain}. ${pkg.subtitle} From ${formatINR(pkg.priceFrom)} per person.`;

  return {
    title: pkg.title,
    description,
    alternates: { canonical: `/packages/${pkg.slug}` },
    openGraph: {
      type: "website",
      title: `${pkg.title} · ${site.name}`,
      description,
      url: `/packages/${pkg.slug}`,
      images: [{ url: pkg.image, alt: pkg.title }],
    },
    twitter: { card: "summary_large_image", title: pkg.title, description, images: [pkg.image] },
  };
}

/** Packages sharing a region or category, best matches first; topped up with others if needed. */
function relatedPackages(pkg: TourPackage, count = 3) {
  const others = packages.filter((p) => p.slug !== pkg.slug);
  const scored = others
    .map((p, i) => ({
      p,
      i,
      score:
        (p.region === pkg.region ? 2 : 0) + p.categories.filter((c) => pkg.categories.includes(c)).length,
    }))
    .sort((a, b) => b.score - a.score || a.i - b.i);
  return scored.slice(0, count).map((s) => s.p);
}

function absoluteUrl(path: string) {
  return new URL(path, site.url).toString();
}

function packageJsonLd(pkg: TourPackage) {
  const stops = pkg.destinations.map((slug) => getDestination(slug)).filter((d) => d !== undefined);
  const url = absoluteUrl(`/packages/${pkg.slug}`);
  return {
    "@context": "https://schema.org",
    "@type": ["Product", "TouristTrip"],
    "@id": `${url}#trip`,
    name: pkg.title,
    description: pkg.subtitle,
    image: absoluteUrl(pkg.image),
    url,
    category: "Tour package",
    brand: { "@type": "Brand", name: site.name },
    touristType: pkg.categories.map((c) => categoryLabels[c]),
    itinerary: {
      "@type": "ItemList",
      numberOfItems: stops.length,
      itemListElement: stops.map((d, i) => ({
        "@type": "ListItem",
        position: i + 1,
        item: {
          "@type": "TouristDestination",
          name: d.name,
          url: absoluteUrl(`/destinations/${d.slug}`),
          geo: { "@type": "GeoCoordinates", latitude: d.geo.lat, longitude: d.geo.lng },
        },
      })),
    },
    offers: {
      "@type": "Offer",
      url,
      price: pkg.priceFrom,
      priceCurrency: "INR",
      availability: "https://schema.org/InStock",
      priceSpecification: {
        "@type": "UnitPriceSpecification",
        price: pkg.priceFrom,
        priceCurrency: "INR",
        unitText: "per person",
      },
      seller: { "@type": "TravelAgency", name: site.name, url: site.url, telephone: site.phones[0].raw },
    },
    provider: { "@type": "TravelAgency", name: site.name, url: site.url },
  };
}

export default async function PackagePage({ params }: PageProps<"/packages/[slug]">) {
  const { slug } = await params;
  const pkg = getPackage(slug);
  if (!pkg) notFound();

  const stops = pkg.destinations.map((s) => getDestination(s)).filter((d) => d !== undefined);
  const chain = destinationChain(pkg);
  const related = relatedPackages(pkg);
  const bestFor = pkg.categories.map((c) => categoryLabels[c]);

  const facts = [
    { label: "Duration", value: `${pkg.nights}N / ${pkg.days}D` },
    { label: "Destinations", value: chain.length ? chain.join(" · ") : regionLabels[pkg.region] },
    { label: "From / person", value: formatINR(pkg.priceFrom), highlight: true },
    { label: "Best for", value: bestFor.length ? bestFor.join(" · ") : "Everyone" },
  ];

  let n = 0;
  const num = () => pad2(++n);

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(packageJsonLd(pkg)).replace(/</g, "\\u003c") }}
      />

      {/* ───────── Hero ───────── */}
      {/* Dark scope over the photo; the site header is sticky in flow above, so only modest top padding. */}
      <section className="theme-dark relative isolate flex min-h-[52svh] items-end overflow-hidden bg-scrim pb-10 pt-10 sm:min-h-[56svh] sm:pb-14 sm:pt-14">
        <Image
          src={pkg.image}
          alt={`${pkg.title} — ${chain.join(", ")}`}
          fill
          preload
          sizes="100vw"
          className="-z-20 object-cover"
        />
        <div aria-hidden className="absolute inset-0 -z-10 bg-gradient-to-t from-scrim/90 via-scrim/45 to-scrim/20" />
        <div aria-hidden className="absolute inset-0 -z-10 bg-gradient-to-r from-scrim/60 via-scrim/10 to-transparent" />

        <Container>
          <nav aria-label="Breadcrumb" className="mb-5">
            <ol className="flex flex-wrap items-center gap-1.5 text-[13px] font-medium text-muted">
              <li className="flex items-center gap-1.5">
                <Link href="/" className="transition-colors hover:text-ink">
                  Home
                </Link>
                <ChevronRight aria-hidden className="size-3.5 text-faint" />
              </li>
              <li className="flex items-center gap-1.5">
                <Link href="/packages" className="transition-colors hover:text-ink">
                  Packages
                </Link>
                <ChevronRight aria-hidden className="size-3.5 text-faint" />
              </li>
              <li aria-current="page" className="max-w-[16rem] truncate text-ink">
                {pkg.title}
              </li>
            </ol>
          </nav>

          <Eyebrow>
            {regionLabels[pkg.region]} · {pkg.nights}N / {pkg.days}D
          </Eyebrow>
          <h1 className="mt-3 max-w-4xl font-display text-[clamp(2rem,5.5vw,4rem)] font-semibold leading-[1.08] tracking-[-0.01em] text-ink text-balance break-words">
            {pkg.title}
          </h1>
          <p className="mt-4 max-w-2xl text-[15px] leading-relaxed text-ink/85 text-pretty sm:text-[17px]">{pkg.subtitle}</p>

          {/* Key facts as pills. Solid tinted fills (no backdrop blur) keep the hero cheap on mobile GPUs. */}
          <dl className="mt-6 flex flex-wrap gap-2">
            {facts.map((f) => (
              <div
                key={f.label}
                className={cn(
                  "inline-flex min-w-0 max-w-full items-baseline gap-1.5 rounded-2xl border px-3.5 py-1.5 text-[13px]",
                  f.highlight ? "border-primary bg-primary text-white" : "border-white/20 bg-scrim/55 text-ink",
                )}
              >
                <dt className={cn("shrink-0", f.highlight ? "text-white/85" : "text-muted")}>{f.label}</dt>
                <dd className="min-w-0 font-semibold break-words">
                  {f.value}
                  {f.highlight && "*"}
                </dd>
              </div>
            ))}
          </dl>
        </Container>
      </section>

      {/* ───────── Body ───────── */}
      <div className="relative py-12 sm:py-16 lg:py-20">
        <Container className="grid grid-cols-1 gap-10 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-14">
          <div className="min-w-0 space-y-14 sm:space-y-16">
            {/* Overview + highlights */}
            <section aria-labelledby="overview">
              <Eyebrow>{num()} — Overview</Eyebrow>
              <h2 id="overview" className="mt-3 font-display text-[1.75rem] font-semibold leading-[1.15] tracking-[-0.01em] text-ink text-balance sm:text-[2rem]">
                {pkg.nights} nights across {regionLabels[pkg.region]}
              </h2>
              <p className="mt-4 max-w-2xl text-base leading-relaxed text-muted text-pretty sm:text-[17px]">
                {pkg.subtitle}
                {chain.length > 1 && <> The route runs {chain.join(" → ")}.</>}
              </p>

              {pkg.highlights.length > 0 && (
                <ul className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2" aria-label="Highlights">
                  {pkg.highlights.map((h) => (
                    <li
                      key={h}
                      className="flex min-w-0 items-start gap-3 rounded-xl border border-line bg-surface p-4 shadow-soft"
                    >
                      <span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-leaf/12 text-leaf">
                        <Check className="size-3" strokeWidth={3} aria-hidden />
                      </span>
                      <span className="min-w-0 text-[15px] leading-relaxed text-ink break-words">{h}</span>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            {/* Itinerary */}
            {pkg.itinerary.length > 0 && (
              <section
                aria-labelledby="itinerary"
                className={cn(deferRender, "[contain-intrinsic-size:auto_1600px]")}
              >
                <Eyebrow>{num()} — Itinerary</Eyebrow>
                <h2 id="itinerary" className="mb-8 mt-3 font-display text-[1.75rem] font-semibold leading-[1.15] tracking-[-0.01em] text-ink sm:text-[2rem]">
                  Day by day
                </h2>
                <ItineraryTimeline days={pkg.itinerary} />
              </section>
            )}

            {/* Inclusions / exclusions */}
            <section aria-labelledby="inclusions" className={cn(deferRender, "[contain-intrinsic-size:auto_720px]")}>
              <Eyebrow>{num()} — What&apos;s covered</Eyebrow>
              <h2 id="inclusions" className="mt-3 font-display text-[1.75rem] font-semibold leading-[1.15] tracking-[-0.01em] text-ink sm:text-[2rem]">
                Inclusions &amp; exclusions
              </h2>
              <div className="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="min-w-0 rounded-2xl border border-line bg-surface p-5 shadow-soft sm:p-6">
                  <h3 className="label text-leaf">Included</h3>
                  <ul className="mt-4 space-y-3">
                    {pkg.inclusions.map((item) => (
                      <li key={item} className="flex items-start gap-3 text-[15px] leading-relaxed text-ink">
                        <span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-leaf/12 text-leaf">
                          <Check className="size-3" strokeWidth={3} aria-hidden />
                        </span>
                        {item}
                      </li>
                    ))}
                  </ul>
                </div>
                <div className="min-w-0 rounded-2xl border border-line bg-surface p-5 shadow-soft sm:p-6">
                  <h3 className="label text-rose-700">Not included</h3>
                  <ul className="mt-4 space-y-3">
                    {pkg.exclusions.map((item) => (
                      <li key={item} className="flex items-start gap-3 text-[15px] leading-relaxed text-muted">
                        <span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-rose-500/10 text-rose-600">
                          <X className="size-3" strokeWidth={3} aria-hidden />
                        </span>
                        {item}
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </section>

            {/* Destinations on this trip */}
            {stops.length > 0 && (
              <section aria-labelledby="stops" className={cn(deferRender, "[contain-intrinsic-size:auto_480px]")}>
                <Eyebrow>{num()} — Where you&apos;ll go</Eyebrow>
                <h2 id="stops" className="mt-3 font-display text-[1.75rem] font-semibold leading-[1.15] tracking-[-0.01em] text-ink sm:text-[2rem]">
                  Destinations on this trip
                </h2>
                <ul className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
                  {stops.map((d, i) => (
                    <li key={d.slug} className="min-w-0">
                      <Link
                        href={`/destinations/${d.slug}`}
                        className="group flex items-center gap-4 rounded-2xl border border-line bg-surface p-3 pr-4 shadow-soft transition-[transform,box-shadow,border-color] duration-300 ease-out-expo hover:-translate-y-0.5 hover:border-line-strong hover:shadow-lift motion-reduce:hover:translate-y-0"
                      >
                        <span className="relative size-16 shrink-0 overflow-hidden rounded-xl sm:size-20">
                          <Image
                            src={d.image}
                            alt=""
                            fill
                            sizes="80px"
                            className="object-cover transition-transform duration-700 ease-out-expo group-hover:scale-110 motion-reduce:group-hover:scale-100"
                          />
                        </span>
                        <span className="min-w-0 flex-1">
                          <span className="label">Stop {pad2(i + 1)}</span>
                          <span className="mt-0.5 block font-display text-lg font-semibold leading-snug text-ink">
                            {d.name}
                          </span>
                          <span className="mt-0.5 block truncate text-[13px] text-muted">{d.tagline}</span>
                        </span>
                        <ArrowUpRight
                          aria-hidden
                          className="size-4 shrink-0 text-faint transition-colors group-hover:text-primary"
                        />
                      </Link>
                    </li>
                  ))}
                </ul>
              </section>
            )}
          </div>

          <BookingSidebar pkg={pkg} />
        </Container>
      </div>

      {/* ───────── You may also like ───────── */}
      {related.length > 0 && (
        <section
          aria-labelledby="related"
          className={cn(deferRender, "border-t border-line bg-night-2 py-16 [contain-intrinsic-size:auto_900px] sm:py-24")}
        >
          <Container>
            <div className="flex flex-wrap items-end justify-between gap-4">
              <div>
                <Eyebrow>More journeys</Eyebrow>
                <h2
                  id="related"
                  className="mt-3 font-display text-[1.875rem] font-semibold leading-[1.12] tracking-[-0.01em] text-ink sm:text-4xl"
                >
                  You may also like
                </h2>
              </div>
              <Link
                href="/packages"
                className="-mx-2 inline-flex min-h-11 items-center gap-1.5 px-2 text-sm font-semibold text-primary transition-colors hover:text-primary-bright"
              >
                All packages <ArrowRight className="size-4" aria-hidden />
              </Link>
            </div>
            <ul className="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 [&>li]:min-w-0">
              {related.map((p, i) => (
                <Reveal as="li" key={p.slug} delay={i * 0.08} className="h-full">
                  <PackageCard pkg={p} sizes={PACKAGE_CARD_SIZES} />
                </Reveal>
              ))}
            </ul>
          </Container>
        </section>
      )}

      <CtaBand
        title="Make it yours"
        subtitle={`Tweak the days, hotels or pace of “${pkg.title}” — our Srinagar team will tailor it around you.`}
      />
    </>
  );
}
