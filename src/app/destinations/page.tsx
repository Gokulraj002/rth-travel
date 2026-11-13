import type { Metadata } from "next";
import { MessageCircle, Mountain } from "lucide-react";
import type { Region } from "@/types";
import { regionLabels } from "@/data/labels";
import { getDestinations, getSite } from "@/lib/cms";
import { PageHero } from "@/components/shared/PageHero";
import { CtaBand } from "@/components/shared/CtaBand";
import { DestinationCard } from "@/components/destinations/DestinationCard";
import { ButtonLink } from "@/components/ui/Button";
import { Container, Section } from "@/components/ui/primitives";
import { Reveal } from "@/components/ui/Reveal";
import { cn, pad2, whatsappLink } from "@/lib/utils";

export const metadata: Metadata = {
  title: "Destinations",
  description:
    "Srinagar, Gulmarg, Pahalgam, Sonamarg, Ladakh, Katra and Jammu — explore every destination Reyaan Trip Holiday travels to across Kashmir, Ladakh and the Jammu region.",
  alternates: { canonical: "/destinations" },
  openGraph: {
    title: "Destinations · Kashmir, Ladakh, Katra & Jammu",
    description:
      "Houseboats on Dal Lake, snow at Gulmarg, the high passes of Ladakh and the shrine at Katra — every place we travel to.",
    images: [{ url: "/images/destinations/pahalgam.jpg", width: 1600, height: 1067 }],
  },
};

const REGION_ORDER = Object.keys(regionLabels) as Region[];

const altitudeFormat = new Intl.NumberFormat("en-IN");

/* Static class maps so Tailwind can see every span it needs. */
const LG_SPAN: Record<number, string> = { 1: "lg:col-span-1", 2: "lg:col-span-2", 3: "lg:col-span-3" };
const SM_SPAN: Record<number, string> = { 1: "sm:col-span-1", 2: "sm:col-span-2" };

export default async function DestinationsPage() {
  const [destinations, site] = await Promise.all([getDestinations(), getSite()]);
  return (
    <>
      <PageHero
        eyebrow="SRINAGAR • LADAKH • KATRA • JAMMU"
        title="DESTINATIONS"
        subtitle="Mirror-still lakes, meadows of snow, high-altitude deserts and holy hills — every place we travel to across Kashmir, Ladakh and the Jammu region."
        image="/images/destinations/pahalgam.jpg"
        imageAlt="Pine forests and green meadows of the Lidder valley, Pahalgam"
      />

      <div>
        {REGION_ORDER.map((region, r) => {
          const list = destinations.filter((d) => d.region === region);
          if (list.length === 0) return null;

          const hasFeature = region === "kashmir";
          // Fill leftover cells in the last desktop row with a "region at a glance" tile.
          const lgCells = list.length + (hasFeature ? 1 : 0);
          const lgEmpty = (3 - (lgCells % 3)) % 3;
          const lgSpan = lgEmpty === 0 ? 3 : lgEmpty;
          const smSpan = list.length % 2 === 0 ? 2 : 1;

          const highlights = list.flatMap((d) => d.highlights.slice(0, list.length > 2 ? 2 : 4)).slice(0, 8);
          const alts = list.map((d) => d.altitudeM);
          const minAlt = Math.min(...alts);
          const maxAlt = Math.max(...alts);
          const label = regionLabels[region];
          const headingId = `region-${region}`;

          return (
            <Section
              key={region}
              className={cn(
                r > 0 &&
                  // Later regions are well below the fold: skip their layout/paint until near the viewport.
                  "pt-0 [content-visibility:auto] [contain-intrinsic-size:auto_2400px] sm:pt-0 sm:[contain-intrinsic-size:auto_1500px] lg:[contain-intrinsic-size:auto_1100px]",
              )}
            >
              <Container>
                <div role="group" aria-labelledby={headingId}>
                  <Reveal className="mb-8 flex flex-col gap-4 border-b border-line pb-6 sm:mb-10 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                      <p className="label mb-4 text-accent">{pad2(r + 1)} — Region</p>
                      <h2
                        id={headingId}
                        className="font-display text-[1.875rem] leading-[1.12] font-semibold tracking-[-0.01em] text-balance break-words text-ink sm:text-4xl lg:text-[2.75rem]"
                      >
                        {label}
                      </h2>
                    </div>
                    <p className="label shrink-0 text-muted">
                      <span className="text-accent">{pad2(list.length)}</span>{" "}
                      {list.length === 1 ? "Destination" : "Destinations"}
                    </p>
                  </Reveal>

                  <ul role="list" className="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 [&>li]:min-w-0">
                    {list.map((d, i) => {
                      const feature = hasFeature && i === 0;
                      return (
                        <li key={d.slug} className={cn(feature && "lg:row-span-2")}>
                          <Reveal delay={Math.min(i, 3) * 0.08} className="h-full">
                            <DestinationCard
                              destination={d}
                              index={destinations.indexOf(d)}
                              size={feature ? "lg" : "md"}
                            />
                          </Reveal>
                        </li>
                      );
                    })}

                    <li className={cn(SM_SPAN[smSpan], LG_SPAN[lgSpan])}>
                      <Reveal delay={0.16} className="h-full">
                        {/* Light card: white surface, faint logo-blue wash in one corner, leaf bullets. */}
                        <div className="relative isolate flex h-full flex-col justify-between gap-8 overflow-hidden rounded-2xl border border-line bg-surface p-6 shadow-soft sm:p-8">
                          <div
                            aria-hidden
                            className="absolute -top-24 -right-24 -z-10 size-72 rounded-full bg-[radial-gradient(closest-side,rgb(0_84_144/0.07),transparent)]"
                          />
                          <div>
                            <p className="label mb-4 text-accent">{label} at a glance</p>
                            <p className="text-sm font-medium text-ink tabular-nums">
                              <Mountain aria-hidden className="mr-2 inline size-4 align-[-0.15em] text-accent" />
                              {minAlt === maxAlt
                                ? `${altitudeFormat.format(minAlt)} m`
                                : `${altitudeFormat.format(minAlt)} – ${altitudeFormat.format(maxAlt)} m`}{" "}
                              <span className="text-muted">above sea level</span>
                            </p>
                            <ul role="list" className="mt-6 flex flex-wrap gap-2">
                              {highlights.map((h) => (
                                <li
                                  key={h}
                                  className="inline-flex max-w-full items-center gap-2 rounded-full border border-line bg-surface-2 px-3 py-1 text-xs font-medium text-ink"
                                >
                                  <span aria-hidden className="size-1.5 shrink-0 rounded-full bg-leaf" />
                                  <span className="min-w-0 truncate">{h}</span>
                                </li>
                              ))}
                            </ul>
                          </div>
                          <div>
                            <p className="font-display text-xl leading-tight font-semibold text-balance text-ink sm:text-[1.375rem]">
                              {list.length > 1 ? "See them all in one trip." : `Plan your ${label} trip.`}
                            </p>
                            <p className="mt-2 text-sm text-muted">
                              Tell us your dates — we&apos;ll shape the route, stays and car.
                            </p>
                            <ButtonLink
                              href={whatsappLink(site.whatsapp, `Hi Reyaan Trip Holiday, I'd like to plan a ${label} trip.`)}
                              variant="primary"
                              size="md"
                              className="mt-5"
                            >
                              <MessageCircle aria-hidden className="size-4" />
                              Plan on WhatsApp
                            </ButtonLink>
                          </div>
                        </div>
                      </Reveal>
                    </li>
                  </ul>
                </div>
              </Container>
            </Section>
          );
        })}
      </div>

      <CtaBand />
    </>
  );
}
