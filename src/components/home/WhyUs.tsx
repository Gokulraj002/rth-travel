import Image from "next/image";
import {
  BedDouble,
  Car,
  Headphones,
  MapPin,
  ShieldCheck,
  Wallet,
  type LucideIcon,
} from "lucide-react";
import type { ReactNode } from "react";
import { whyUs, type WhyUsItem } from "@/data/content";
import { getDestination } from "@/data/destinations";
import { site } from "@/data/site";
import { Container, Section, SectionHeading } from "@/components/ui/primitives";
import { Reveal } from "@/components/ui/Reveal";
import { cn, formatCoords, pad2 } from "@/lib/utils";

const icons: Record<WhyUsItem["icon"], LucideIcon> = {
  MapPin,
  Car,
  ShieldCheck,
  Headphones,
  BedDouble,
  Wallet,
};

/**
 * Column spans for the tiles after the first four (which sit beside the feature tile on lg).
 * Stretches a short final row so the 4-col bento never leaves holes.
 */
function trailingSpan(index: number, total: number) {
  if (index < 4) return "";
  const rest = total - 4;
  const lastRowCount = rest % 4;
  const lastRowStart = 4 + rest - lastRowCount;
  if (lastRowCount === 0 || index < lastRowStart) return "";
  if (lastRowCount === 1) return "lg:col-span-4";
  if (lastRowCount === 2) return "lg:col-span-2";
  // 3 left: last one spans two columns
  return index === total - 1 ? "lg:col-span-2" : "";
}

/**
 * Bento card on `bg-surface` (white in light scope, navy #0b1c3a on the dark home page), with a
 * 1px top highlight that only shows on dark. On desktop (fine pointer) hover adds a soft accent
 * tint + lift — pure CSS (no pointer tracking), so the section ships no extra client JS.
 */
function Tile({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={cn(
        "group relative h-full overflow-hidden rounded-2xl border border-line bg-surface p-6 shadow-soft inset-shadow-[0_1px_0_rgb(255_255_255/0.05)] transition-[box-shadow,transform,border-color] duration-500 ease-out-expo sm:p-7",
        "pointer-fine:hover:-translate-y-0.5 pointer-fine:hover:border-line-strong pointer-fine:hover:shadow-lift",
        className,
      )}
    >
      {/* subtle accent tint (scope-aware), desktop hover only */}
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 hidden bg-[radial-gradient(120%_80%_at_0%_0%,color-mix(in_oklab,var(--accent)_8%,transparent),transparent_60%)] opacity-0 transition-opacity duration-500 lg:block pointer-fine:group-hover:opacity-100"
      />
      <div className="relative">{children}</div>
    </div>
  );
}

export function WhyUs() {
  const srinagar = getDestination("srinagar");
  const items = whyUs;
  const hasItems = items.length > 0;

  return (
    <Section id="why-us" className="overflow-hidden">
      {/* soft accent wash — logo blue on light, a faint sky glow on the dark home page */}
      <div
        aria-hidden
        className="pointer-events-none absolute -top-40 left-1/2 h-[480px] w-[min(900px,120vw)] -translate-x-1/2 bg-[radial-gradient(closest-side,color-mix(in_oklab,var(--accent)_8%,transparent),transparent)]"
      />

      <Container className="relative">
        <Reveal>
          <SectionHeading
            eyebrow="WHY REYAAN TRIP HOLIDAY"
            title={
              <>
                Locals who know <span className="text-accent">every bend</span> of the road.
              </>
            }
            lead="We live where you're travelling. That means honest advice on routes, seasons and stays — and a team that's a phone call away for the whole trip."
          />
        </Reveal>

        <div
          className={cn(
            "mt-10 grid grid-cols-1 gap-4 sm:mt-12 md:grid-cols-2 lg:gap-5",
            hasItems && "lg:grid-cols-4 lg:auto-rows-fr",
          )}
        >
          {/* Feature tile */}
          <Reveal
            className={cn(
              "md:col-span-2",
              hasItems && "lg:col-span-2 lg:row-span-2",
            )}
          >
            <article className="theme-dark group relative isolate flex h-full min-h-[400px] flex-col justify-end overflow-hidden rounded-2xl shadow-soft sm:min-h-[440px]">
              <Image
                src="/images/destinations/srinagar-5.jpg"
                alt="Houseboats reflected in the still water of Dal Lake, Srinagar"
                fill
                sizes="(min-width: 1024px) 50vw, 100vw"
                className="-z-20 object-cover transition-transform duration-[1.6s] ease-out-expo group-hover:scale-[1.04]"
              />
              <div
                aria-hidden
                className="absolute inset-0 -z-10 bg-linear-to-t from-scrim/90 via-scrim/55 to-scrim/10"
              />

              <div className="flex items-center justify-between gap-3 p-6 sm:p-8">
                <span className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-scrim/60 px-3 py-1.5 text-xs font-medium text-white">
                  <span className="relative flex size-2">
                    <span className="absolute inset-0 rounded-full bg-leaf motion-safe:animate-pulse-ring" />
                    <span className="relative size-2 rounded-full bg-leaf" />
                  </span>
                  Based in Nowgam, Srinagar
                </span>
              </div>

              <div className="mt-auto p-6 pt-0 sm:p-8 sm:pt-0">
                {srinagar && (
                  <p className="label mb-3 text-white/75">
                    {formatCoords(srinagar.geo.lat, srinagar.geo.lng)}
                  </p>
                )}
                <h3 className="font-display text-2xl font-semibold leading-[1.15] tracking-[-0.01em] text-balance text-white sm:text-[1.75rem]">
                  A Srinagar team, planning trips from home.
                </h3>
                <p className="mt-3 max-w-xl text-[15px] leading-relaxed text-white/85 text-pretty">
                  Our office is in Nowgam, Srinagar — so your itinerary is built by people who drive these
                  roads, know which passes are open and which houseboat suits your family. From Dal Lake to
                  Ladakh, Katra and Jammu, we handle the details on the ground.
                </p>
                <ul className="mt-5 flex flex-wrap gap-2">
                  {site.destinationsLine.map((d) => (
                    <li
                      key={d}
                      className="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium text-white"
                    >
                      <MapPin aria-hidden className="size-3 text-accent-soft" />
                      {d}
                    </li>
                  ))}
                </ul>
              </div>
            </article>
          </Reveal>

          {items.map((item, i) => {
            const Icon = icons[item.icon] ?? MapPin;
            return (
              <Reveal
                key={item.title}
                delay={0.08 * (i + 1)}
                className={cn("h-full", trailingSpan(i, items.length))}
              >
                <Tile>
                  <div className="flex items-start justify-between gap-4">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-accent/10 text-accent ring-1 ring-accent/15">
                      <Icon aria-hidden className="size-5" strokeWidth={1.75} />
                    </span>
                    <span aria-hidden className="label transition-colors group-hover:text-accent">
                      {pad2(i + 1)}
                    </span>
                  </div>
                  <h3 className="mt-5 font-display text-lg font-semibold leading-snug text-ink sm:text-xl">
                    {item.title}
                  </h3>
                  <p className="mt-2 text-[15px] leading-relaxed text-muted text-pretty">{item.description}</p>
                </Tile>
              </Reveal>
            );
          })}
        </div>
      </Container>
    </Section>
  );
}
