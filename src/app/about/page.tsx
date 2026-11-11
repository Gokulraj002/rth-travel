import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { ArrowUpRight, BedDouble, Car, Headphones, MapPin, ShieldCheck, Wallet, type LucideIcon } from "lucide-react";
import { CtaBand } from "@/components/shared/CtaBand";
import { PageHero } from "@/components/shared/PageHero";
import { Container, Eyebrow, Section, SectionHeading } from "@/components/ui/primitives";
import { Reveal } from "@/components/ui/Reveal";
import { whyUs, type WhyUsItem } from "@/data/content";
import { getDestination, regionLabels } from "@/data/destinations";
import { site } from "@/data/site";
import { pad2 } from "@/lib/utils";
import type { Destination } from "@/types";

const description = `${site.name} is a local travel team based in Nowgam, Srinagar, planning trips across Kashmir, Ladakh, Katra and Jammu with our own vehicles and local drivers.`;

export const metadata: Metadata = {
  title: "About Us",
  description,
  alternates: { canonical: "/about" },
  openGraph: {
    type: "website",
    siteName: site.name,
    locale: "en_IN",
    title: `About · ${site.name}`,
    description,
    url: "/about",
    images: [{ url: "/images/destinations/srinagar-4.jpg", width: 1600, height: 1067 }],
  },
};

const ICONS: Record<WhyUsItem["icon"], LucideIcon> = {
  MapPin,
  Car,
  ShieldCheck,
  Headphones,
  BedDouble,
  Wallet,
};

/** Used only while `whyUs` in content.ts is still empty. Honest, non-numeric claims only. */
const FALLBACK_VALUES: WhyUsItem[] = [
  {
    icon: "MapPin",
    title: "Planned by locals",
    description:
      "We live and work in Srinagar, so routes, timings and stays come from first-hand knowledge — not a brochure.",
  },
  {
    icon: "Car",
    title: "Our own vehicles",
    description:
      "You travel with our own vehicles and local drivers who know the valley roads and the mountain passes.",
  },
  {
    icon: "Headphones",
    title: "One team, start to finish",
    description: "The people who plan your trip are the people you call on the road — on the phone or on WhatsApp.",
  },
  {
    icon: "BedDouble",
    title: "Stays that suit you",
    description: "Budget hotels, deluxe rooms or a night on a Dal Lake houseboat — we match the stay to your plans.",
  },
];

const STEPS = [
  {
    title: "Tell us",
    description:
      "Share your dates, who's coming and what you'd love to see — on WhatsApp, on a call or through the enquiry form.",
  },
  {
    title: "We design",
    description:
      "We sketch a day-by-day route with stays, drive times and a clear quote, then adjust it with you until it feels right.",
  },
  {
    title: "We confirm",
    description:
      "Once you're happy, we lock in your hotels or houseboat, your vehicle and anything your route needs, and send it all in writing.",
  },
  {
    title: "You travel",
    description: "A local driver meets you on arrival and we stay one call or message away for the whole trip.",
  },
];

const regions = site.destinationsLine
  .map((name) => getDestination(name.toLowerCase()))
  .filter((d): d is Destination => Boolean(d));

const values = whyUs.length > 0 ? whyUs : FALLBACK_VALUES;

export default function AboutPage() {
  return (
    <>
      <PageHero
        eyebrow="EXPLORE • TRAVEL • CREATE MEMORIES"
        title="ABOUT US"
        subtitle="A Srinagar travel team from Nowgam, planning journeys across Kashmir, Ladakh, Katra and Jammu — the places we call home."
        image="/images/destinations/srinagar-4.jpg"
        imageAlt="Colourful shikaras moored on the shore of Dal Lake below the Zabarwan hills"
      />

      {/* Story */}
      <Section className="overflow-hidden">
        <Container>
          <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
            <Reveal className="relative">
              <figure className="relative rounded-2xl border border-line bg-surface p-2 shadow-lift">
                <Image
                  src={site.banner}
                  alt={`${site.name} banner — Srinagar, Ladakh, Katra and Jammu`}
                  width={715}
                  height={477}
                  sizes="(min-width: 1024px) 40vw, 100vw"
                  className="h-auto w-full rounded-xl"
                />
                <figcaption className="label flex flex-wrap items-center justify-between gap-2 px-3 pt-3 pb-1">
                  <span>{site.tagline}</span>
                  <span className="text-accent">Nowgam · Srinagar</span>
                </figcaption>
              </figure>
            </Reveal>

            <Reveal delay={0.1}>
              <Eyebrow>01 — Our story</Eyebrow>
              <h2 className="mt-4 font-display text-[1.875rem] font-semibold leading-[1.12] tracking-[-0.01em] text-balance text-ink sm:text-4xl lg:text-[2.75rem]">
                Local roots, <span className="text-accent">open roads</span>
              </h2>
              <div className="mt-6 space-y-4 text-base leading-relaxed text-muted text-pretty sm:text-[17px]">
                <p>
                  {site.name} is a travel company based in Nowgam, Srinagar. We plan trips across the places we know
                  best — the lakes and meadows of Kashmir, the high passes of Ladakh, the pilgrim trail at Katra and the
                  temple city of Jammu.
                </p>
                <p>
                  Living here means we plan around what really shapes a mountain holiday: which roads are open, how long
                  a drive actually takes, where to stay for the view and when to set off to beat the crowds. And because
                  we travel with our own vehicles and local drivers, the people looking after you on the road know it
                  well.
                </p>
                <p>
                  Whether it&apos;s a honeymoon on a houseboat, a family snow trip or a yatra to Mata Vaishno Devi, we
                  shape it around you — and stay a call away from the first message to the drive home.
                </p>
              </div>
              <ul className="mt-8 flex flex-wrap gap-2">
                {site.destinationsLine.map((place) => (
                  <li
                    key={place}
                    className="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5 text-xs font-medium text-muted shadow-soft"
                  >
                    <MapPin className="size-3.5 text-accent" aria-hidden="true" />
                    {place}
                  </li>
                ))}
              </ul>
            </Reveal>
          </div>
        </Container>
      </Section>

      {/* Regions */}
      {regions.length > 0 && (
        <Section className="bg-night-2">
          <Container>
            <Reveal>
              <SectionHeading
                eyebrow="02 — Where we take you"
                title="Four regions, one local team"
                lead="From houseboats in Srinagar to monasteries in Ladakh and the temples of Katra and Jammu."
                className="mb-12"
              />
            </Reveal>
            <ul className="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
              {regions.map((d, i) => (
                <Reveal as="li" key={d.slug} delay={i * 0.08} className="min-w-0">
                  <Link
                    href={`/destinations/${d.slug}`}
                    className="group flex h-full flex-col overflow-hidden rounded-2xl border border-line bg-surface shadow-soft transition-all duration-300 ease-out-expo hover:-translate-y-0.5 hover:shadow-lift"
                  >
                    <div className="relative aspect-[4/3] overflow-hidden bg-surface-2">
                      <Image
                        src={d.image}
                        alt=""
                        fill
                        sizes="(min-width: 1024px) 25vw, 50vw"
                        className="object-cover transition-transform duration-700 ease-out-expo group-hover:scale-105"
                      />
                    </div>
                    <div className="flex flex-1 flex-col p-3 sm:p-5">
                      <div className="flex items-center justify-between gap-2">
                        <p className="label truncate text-accent">
                          <span className="sm:hidden">{pad2(i + 1)}</span>
                          <span className="hidden sm:inline">
                            {pad2(i + 1)} · {regionLabels[d.region]}
                          </span>
                        </p>
                        <span
                          aria-hidden="true"
                          className="grid size-8 shrink-0 place-items-center rounded-full bg-primary/10 text-primary transition-colors duration-300 group-hover:bg-primary group-hover:text-white"
                        >
                          <ArrowUpRight className="size-4" />
                        </span>
                      </div>
                      <h3 className="mt-2 font-display text-lg font-semibold break-words text-ink sm:text-xl">
                        {d.displayName}
                      </h3>
                      <p className="mt-1 line-clamp-2 text-[13px] leading-relaxed text-muted sm:text-sm">{d.tagline}</p>
                    </div>
                  </Link>
                </Reveal>
              ))}
            </ul>
          </Container>
        </Section>
      )}

      {/* Process */}
      <Section>
        <Container>
          <Reveal>
            <SectionHeading
              eyebrow="03 — How we plan your trip"
              title="Simple, personal, done properly"
              lead="It starts with a conversation and ends with you on the road. Here's how it works."
              className="mb-12"
            />
          </Reveal>
          <ol className="grid gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-4">
            {STEPS.map((step, i) => (
              <Reveal
                as="li"
                key={step.title}
                delay={i * 0.08}
                className="relative rounded-2xl border border-line bg-surface p-6 shadow-soft sm:p-7"
              >
                <span className="font-display text-[2rem] leading-none font-semibold text-accent" aria-hidden="true">
                  {pad2(i + 1)}
                </span>
                <h3 className="mt-5 font-display text-xl font-semibold text-ink">
                  <span className="sr-only">Step {i + 1}: </span>
                  {step.title}
                </h3>
                <p className="mt-3 text-sm leading-relaxed text-muted text-pretty">{step.description}</p>
              </Reveal>
            ))}
          </ol>
        </Container>
      </Section>

      {/* Values */}
      <Section className="bg-night-2">
        <Container>
          <Reveal>
            <SectionHeading
              eyebrow="04 — What we care about"
              title="Why travellers plan with us"
              align="center"
              className="mb-12"
            />
          </Reveal>
          <ul className={`grid gap-4 sm:grid-cols-2 ${values.length % 3 === 0 ? "lg:grid-cols-3" : "lg:grid-cols-4"}`}>
            {values.map((item, i) => {
              const Icon = ICONS[item.icon] ?? MapPin;
              return (
                <Reveal as="li" key={item.title} delay={(i % 4) * 0.08} className="min-w-0">
                  <div className="group h-full rounded-2xl border border-line bg-surface p-6 shadow-soft transition-all duration-300 ease-out-expo hover:-translate-y-0.5 hover:shadow-lift sm:p-7">
                    <span className="grid size-12 place-items-center rounded-full bg-primary/10 text-primary transition-colors duration-300 group-hover:bg-primary group-hover:text-white">
                      <Icon className="size-5" aria-hidden="true" />
                    </span>
                    <h3 className="mt-5 font-display text-lg font-semibold text-ink">{item.title}</h3>
                    <p className="mt-2 text-sm leading-relaxed text-muted text-pretty">{item.description}</p>
                  </div>
                </Reveal>
              );
            })}
          </ul>
        </Container>
      </Section>

      <CtaBand
        title="Let's plan your trip"
        subtitle="Tell us your dates and we'll get back to you shortly with a route that fits."
      />
    </>
  );
}
