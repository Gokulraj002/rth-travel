"use client";

import { ArrowUpRight, MessageCircle } from "lucide-react";
import type { CSSProperties, ReactNode } from "react";
import type { JourneyStop } from "@/types";
import { regionLabels } from "@/data/labels";
import { useSite } from "@/components/layout/SiteProvider";
import { ButtonLink } from "@/components/ui/Button";
import { LiveConditions } from "@/components/destinations/LiveConditions";
import { cn, formatCoords, pad2, whatsappLink } from "@/lib/utils";
import { formatAltitude } from "./shared";

const planMessage = (name: string) => `Hi Reyaan Trip Holiday, I'd like to plan a trip to ${name}`;

/**
 * Giant display word; letters rise in sequence via CSS transitions from `@starting-style`
 * (opacity + transform only, no JS animation library). The panels re-key on the destination, so
 * every switch remounts the word and replays the stagger. Under reduced motion the global rule
 * collapses the transitions and the per-letter delay is zeroed, so the word appears at once.
 * Letters are grouped per word in nowrap spans so a name can never break mid-word.
 */
function GiantWord({ text, className, style }: { text: string; className?: string; style?: CSSProperties }) {
  const base = cn(
    "font-display font-semibold uppercase leading-[0.95] tracking-[-0.01em] text-ink",
    "[text-shadow:0_6px_50px_rgb(2_11_26/0.85)]",
    className,
  );
  let n = 0;
  return (
    <h3 className={base} style={style} aria-label={text}>
      {text.split(" ").map((word, w) => (
        <span key={`${word}-${w}`} aria-hidden className="inline-block whitespace-nowrap">
          {w > 0 && " "}
          {Array.from(word).map((ch, i) => (
            <span
              key={`${ch}-${i}`}
              className="inline-block transition-[opacity,transform] duration-800 ease-out-expo [transition-delay:var(--d)] starting:opacity-0 starting:[transform:translateY(0.45em)] motion-reduce:[transition-delay:0s]"
              style={{ "--d": `${(0.04 * n++).toFixed(2)}s` } as CSSProperties}
            >
              {ch}
            </span>
          ))}
        </span>
      ))}
    </h3>
  );
}

function DataItem({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
  return (
    <div className={cn("min-w-0", className)}>
      <dt className="label">{label}</dt>
      <dd className="mt-1.5 text-[15px] leading-snug tabular-nums text-ink">{children}</dd>
    </div>
  );
}

/** "34.0837° N, 74.7973° E" stacked as two tabular lines so the digits line up. */
function Coords({ lat, lng }: { lat: number; lng: number }) {
  const [a, b] = formatCoords(lat, lng).split(", ");
  return (
    <>
      <span className="block whitespace-nowrap">{a}</span>
      <span className="block whitespace-nowrap">{b}</span>
    </>
  );
}

function Ctas({ d }: { d: JourneyStop }) {
  const { whatsapp } = useSite();
  return (
    <div className="flex flex-wrap gap-2">
      <ButtonLink href={`/destinations/${d.slug}`} variant="glass" size="sm">
        Explore {d.name}
        <ArrowUpRight aria-hidden className="size-3.5" />
      </ButtonLink>
      <ButtonLink href={whatsappLink(whatsapp, planMessage(d.name))} variant="primary" size="sm">
        <MessageCircle aria-hidden className="size-3.5" />
        Plan this trip
      </ButtonLink>
    </div>
  );
}

/** Phone CTAs: 44px tap targets, no backdrop blur, short "Explore" label so both fit at 360px. */
function MobileCtas({ d }: { d: JourneyStop }) {
  const { whatsapp } = useSite();
  return (
    <div className="grid grid-cols-[auto_minmax(0,1fr)] gap-2">
      <ButtonLink
        href={`/destinations/${d.slug}`}
        variant="outline"
        size="md"
        aria-label={`Explore ${d.name}`}
        className="px-4"
      >
        Explore
        <ArrowUpRight aria-hidden className="size-4" />
      </ButtonLink>
      <ButtonLink href={whatsappLink(whatsapp, planMessage(d.name))} variant="primary" size="md" className="min-w-0 px-4">
        <MessageCircle aria-hidden className="size-4 shrink-0" />
        Plan this trip
      </ButtonLink>
    </div>
  );
}

/** Desktop (≥lg) info panel on the right; the display word may spill left over the globe. */
export function DesktopPanel({ stops, activeIndex }: { stops: JourneyStop[]; activeIndex: number }) {
  const d = activeIndex >= 0 ? stops[activeIndex] : undefined;
  return (
    // pt clears the origin chip (top-5, 44px); the stage itself already starts below the header.
    <div className="pointer-events-none absolute inset-y-0 right-0 hidden items-center justify-end pb-10 pr-6 pt-20 lg:flex xl:pr-10">
      {/* Keyed by destination: each switch remounts and fades in via @starting-style (no exit). */}
      {d && (
        <article
          key={d.slug}
          className="pointer-events-auto flex w-[400px] flex-col items-end transition-opacity duration-400 ease-out-expo starting:opacity-0 xl:w-[420px]"
        >
          <p className="label whitespace-nowrap text-accent">
            {pad2(activeIndex + 1)} — {regionLabels[d.region]}
          </p>
          <GiantWord
            text={d.displayName}
            className="mt-3 whitespace-nowrap text-right"
            style={{ fontSize: "clamp(2.5rem, 6vw, 5rem)" }}
          />

          <div className="glass mt-5 w-full rounded-2xl p-5 text-left transition-[opacity,transform] delay-200 duration-800 ease-out-expo starting:opacity-0 starting:[transform:translateY(1.5rem)] motion-reduce:delay-0">
            <p className="text-[15px] leading-relaxed text-muted text-pretty">{d.tagline}</p>

            <div className="mt-4 border-t border-line pt-4">
              <LiveConditions lat={d.geo.lat} lng={d.geo.lng} />
              <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-4">
                <DataItem label="Altitude">{formatAltitude(d.altitudeM)}</DataItem>
                <DataItem label="Coordinates">
                  <Coords lat={d.geo.lat} lng={d.geo.lng} />
                </DataItem>
                <DataItem label="Best time" className="col-span-2 [&_dd]:text-ink/90">
                  {d.bestTime}
                </DataItem>
              </dl>
            </div>

            <div className="mt-4 border-t border-line pt-4 [@media(max-height:820px)]:hidden">
              <p className="label">Highlights</p>
              <ol className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2">
                {d.highlights.slice(0, 4).map((h, i) => (
                  <li key={h} className="flex min-w-0 gap-2 text-[14px] leading-snug text-ink/90">
                    <span className="pt-0.5 text-[11px] font-semibold tabular-nums text-accent">{pad2(i + 1)}</span>
                    <span className="min-w-0">{h}</span>
                  </li>
                ))}
              </ol>
            </div>

            <div className="mt-5">
              <Ctas d={d} />
            </div>
          </div>
        </article>
      )}
    </div>
  );
}

/** Mobile sheet: the whole block (incl. safe-area padding) stays within ~45% of the screen. */
const SHEET_MAX_H = "max-h-[calc(45svh_-_max(0.75rem,env(safe-area-inset-bottom)))]";

/**
 * Mobile (<lg) bottom sheet with condensed info. Below md it is a solid translucent card
 * (no backdrop blur over the live WebGL canvas); glass from md up. The sheet is capped at
 * ~45svh: the info block shrinks/clips first, the CTAs never do, and lower-priority rows
 * drop out on short screens.
 */
export function MobileSheet({ stops, activeIndex }: { stops: JourneyStop[]; activeIndex: number }) {
  const d = activeIndex >= 0 ? stops[activeIndex] : undefined;
  const fit = ((d?.displayName.length ?? 8) * 0.8).toFixed(2);

  return (
    <div className="pointer-events-none absolute inset-x-0 bottom-0 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:hidden">
      {/* Keyed by destination: each switch remounts and rises in via @starting-style (no exit). */}
      {d && (
        <article
          key={d.slug}
          className={cn(
            "pointer-events-auto mx-auto flex max-w-md flex-col transition-[opacity,transform] duration-600 ease-out-expo starting:opacity-0 starting:[transform:translateY(1.75rem)]",
            SHEET_MAX_H,
          )}
        >
          <p className="label shrink-0 px-1 text-accent">
            {pad2(activeIndex + 1)} — {regionLabels[d.region]}
          </p>
          <GiantWord
            text={d.displayName}
            className="mt-2 max-w-full shrink-0 px-1"
            // Fit the name on one line at 360px: width budget ÷ (letters × ~0.8em Playfair caps),
            // capped by the sheet width.
            style={{
              fontSize: `clamp(1.75rem, min(9vw, calc((100vw - 2.5rem) / ${fit}), calc(26rem / ${fit})), 2.75rem)`,
            }}
          />
          <div className="mt-3 flex min-h-0 flex-col rounded-2xl border border-line bg-night/85 p-4 md:glass">
            <div className="min-h-0 overflow-hidden">
              <p className="line-clamp-2 pb-3 text-[14px] leading-snug text-muted [@media(max-height:620px)]:hidden">
                {d.tagline}
              </p>
              <div className="border-t border-line pt-3">
                <LiveConditions lat={d.geo.lat} lng={d.geo.lng} compact />
                <dl className="mt-2 grid grid-cols-2 gap-x-4 [@media(max-height:760px)]:hidden">
                  <div className="min-w-0">
                    <dt className="label">Altitude</dt>
                    <dd className="mt-1 text-[14px] tabular-nums text-ink">{formatAltitude(d.altitudeM)}</dd>
                  </div>
                  <div className="min-w-0">
                    <dt className="label">Coordinates</dt>
                    <dd className="mt-1 text-[13px] leading-4 tabular-nums text-ink">
                      <Coords lat={d.geo.lat} lng={d.geo.lng} />
                    </dd>
                  </div>
                </dl>
              </div>
            </div>
            <div className="mt-3 shrink-0">
              <MobileCtas d={d} />
            </div>
          </div>
        </article>
      )}
    </div>
  );
}
