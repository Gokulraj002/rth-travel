"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import { ArrowRight, Check, MessageCircle } from "lucide-react";
import type { TourPackage } from "@/types";
import { ButtonLink } from "@/components/ui/Button";
import { cn, formatINR, whatsappLink } from "@/lib/utils";
import { destinationChain, durationLabel, enquiryMessage, packageCode, packageImage } from "./utils";

/** Up to four highlights with leaf-green ticks (the RTH logo leaves). */
export function HighlightList({ pkg }: { pkg: TourPackage }) {
  const items = pkg.highlights.slice(0, 4);
  if (items.length === 0) return null;
  return (
    <ul className="grid gap-2 text-[15px] leading-snug text-muted">
      {items.map((h) => (
        <li key={h} className="flex items-start gap-2.5">
          <Check aria-hidden className="mt-0.5 size-4 shrink-0 text-leaf" strokeWidth={2.5} />
          <span>{h}</span>
        </li>
      ))}
    </ul>
  );
}

/**
 * "View itinerary" (secondary) + solid primary WhatsApp "Enquire".
 * `size="md"` (44px tall) for touch layouts; `sm` for the desktop preview.
 */
export function PackageCtas({
  pkg,
  originCity,
  size = "sm",
}: {
  pkg: TourPackage;
  originCity?: string;
  size?: "sm" | "md";
}) {
  return (
    <div className="flex flex-wrap gap-3">
      {/* Filled surface-2 + strong hairline so it stays visible on a white card (light) and a navy card (dark). */}
      <ButtonLink
        href={`/packages/${pkg.slug}`}
        variant="ghost"
        size={size}
        className="flex-1 border border-line-strong bg-surface-2 hover:border-accent/50 sm:flex-none"
      >
        View itinerary
        <ArrowRight aria-hidden className="size-3.5" />
      </ButtonLink>
      <ButtonLink
        href={whatsappLink(enquiryMessage(pkg, originCity))}
        variant="primary"
        size={size}
        className="flex-1 sm:flex-none"
      >
        <MessageCircle aria-hidden className="size-3.5" />
        Enquire
        <span className="sr-only"> about {pkg.title} on WhatsApp (opens in a new tab)</span>
      </ButtonLink>
    </div>
  );
}

/**
 * Desktop sticky preview of the selected package. Only the selected package's photo is mounted
 * (lazy, sized for the 25–27rem column); switching packages crossfades the photo with opacity only —
 * the new layer fades in on top (CSS transition from @starting-style) and the old one is dropped
 * once it is fully covered. No fade on first render; reduced motion collapses it via the global rule.
 */
const FADE_MS = 350;

type Layer = { pkg: TourPackage; fade: boolean };

export function PreviewCard({ pkg, originCity }: { pkg: TourPackage; originCity?: string }) {
  const chain = destinationChain(pkg);

  // Photo layers, bottom → top. A new selection is pushed on top (state adjusted during render).
  const [layers, setLayers] = useState<Layer[]>(() => [{ pkg, fade: false }]);
  if (layers[layers.length - 1].pkg.slug !== pkg.slug) {
    setLayers((ls) => [...ls.filter((l) => l.pkg.slug !== pkg.slug).slice(-1), { pkg, fade: true }]);
  }
  // Drop the covered layer once the fade-in has finished.
  useEffect(() => {
    if (layers.length < 2) return;
    const t = window.setTimeout(() => setLayers((ls) => ls.slice(-1)), FADE_MS + 50);
    return () => window.clearTimeout(t);
  }, [layers]);

  return (
    <article className="overflow-hidden rounded-2xl border border-line bg-surface shadow-lift">
      <div className="relative aspect-[4/3] w-full overflow-hidden bg-surface-2">
        {layers.map(({ pkg: p, fade }, i) => {
          const top = i === layers.length - 1;
          return (
            <div
              key={p.slug}
              aria-hidden={top ? undefined : true}
              className={cn(
                "absolute inset-0",
                fade && "transition-opacity duration-[350ms] ease-out starting:opacity-0",
              )}
            >
              <Image
                src={packageImage(p)}
                alt={top ? `${p.title} — ${destinationChain(p)}` : ""}
                fill
                loading="lazy"
                sizes="(min-width: 1280px) 432px, (min-width: 1024px) 400px, 1px"
                className="object-cover"
              />
            </div>
          );
        })}
        {/* Solid surface chips over the photo (no backdrop blur) — white on light pages, navy on the dark home */}
        <div className="absolute inset-x-0 top-0 flex items-center justify-between p-3.5">
          <span className="rounded-md border border-line bg-surface/95 px-2 py-1 text-[11px] font-semibold tracking-[0.1em] text-accent shadow-soft">
            {packageCode(pkg)}
          </span>
          <span className="rounded-full border border-line bg-surface/95 px-3 py-1 text-[12px] font-semibold tabular-nums text-ink shadow-soft">
            {durationLabel(pkg)}
          </span>
        </div>
      </div>
      <div className="grid gap-5 p-5 sm:p-6">
        <div>
          <p className="label">{chain}</p>
          <h3 className="mt-2 font-display text-[22px] font-semibold leading-snug text-ink text-balance">{pkg.title}</h3>
          {pkg.subtitle && <p className="mt-2 text-[15px] leading-relaxed text-muted text-pretty">{pkg.subtitle}</p>}
          <p className="mt-4 flex items-baseline gap-1.5 border-t border-line pt-4">
            <span className="text-[12px] font-medium text-muted">from</span>
            <span className="text-lg font-semibold tabular-nums text-ink">{formatINR(pkg.priceFrom)}</span>
            <span className="text-[13px] text-muted">/ person</span>
          </p>
        </div>
        <HighlightList pkg={pkg} />
        <PackageCtas pkg={pkg} originCity={originCity} />
      </div>
    </article>
  );
}
