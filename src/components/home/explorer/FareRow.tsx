"use client";

import { memo } from "react";
import Image from "next/image";
import { ChevronDown } from "lucide-react";
import type { TourPackage } from "@/types";
import { cn, formatINR } from "@/lib/utils";
import { destinationChain, durationLabel, packageCode, packageImage, type DestinationLookup } from "./utils";
import { HighlightList, PackageCtas } from "./PreviewCard";

/**
 * One "fare row". Desktop: hover (with a short intent delay) / focus / click selects it for the
 * sticky preview pane. Mobile: tapping expands an inline accordion with image, highlights and CTAs.
 *
 * Visual active state is pure CSS: `selected` styles apply at `lg:` and `expanded` styles at `max-lg:`,
 * so the server HTML already matches what the client renders after `useIsDesktop` resolves.
 * Memoised with slug-based callbacks, so a hover only re-renders the two rows whose state changed.
 */
export const FareRow = memo(function FareRow({
  pkg,
  lookup,
  isDesktop,
  selected,
  expanded,
  onSelect,
  onHover,
  onHoverEnd,
  onToggle,
  originCity,
  panelId,
  previewId,
}: {
  pkg: TourPackage;
  lookup: DestinationLookup;
  isDesktop: boolean;
  selected: boolean;
  expanded: boolean;
  onSelect: (slug: string) => void;
  onHover: (slug: string) => void;
  onHoverEnd: () => void;
  onToggle: (slug: string) => void;
  originCity?: string;
  panelId: string;
  previewId: string;
}) {
  const chain = destinationChain(pkg, lookup);
  const slug = pkg.slug;

  return (
    <div
      className={cn(
        "relative transition-colors duration-300",
        selected ? "lg:bg-surface-2" : "lg:hover:bg-surface-2/60",
        expanded && "max-lg:bg-surface-2",
      )}
    >
      {/* primary bar on the active row */}
      <span
        aria-hidden
        className={cn(
          "absolute inset-y-0 left-0 w-[3px] bg-primary opacity-0 transition-opacity duration-300",
          selected && "lg:opacity-100",
          expanded && "max-lg:opacity-100",
        )}
      />
      <button
        type="button"
        onMouseEnter={isDesktop ? () => onHover(slug) : undefined}
        onMouseLeave={isDesktop ? onHoverEnd : undefined}
        onFocus={isDesktop ? () => onSelect(slug) : undefined}
        onClick={() => (isDesktop ? onSelect(slug) : onToggle(slug))}
        aria-expanded={isDesktop ? undefined : expanded}
        aria-controls={isDesktop ? previewId : panelId}
        aria-current={isDesktop && selected ? "true" : undefined}
        className="group grid min-h-16 w-full grid-cols-[2.75rem_minmax(0,1fr)_auto] items-center gap-3 px-4 py-3.5 text-left sm:grid-cols-[3.25rem_minmax(0,1fr)_auto] sm:gap-5 sm:px-5 focus-visible:rounded-none focus-visible:outline-offset-[-2px]"
      >
        <span
          className={cn(
            "inline-flex h-6 items-center justify-center justify-self-start rounded-md border px-1.5 text-[11px] font-semibold tracking-[0.1em] tabular-nums transition-colors duration-300",
            expanded
              ? "max-lg:border-accent/30 max-lg:bg-accent/10 max-lg:text-accent"
              : "max-lg:border-line max-lg:bg-surface-2 max-lg:text-muted",
            selected
              ? "lg:border-accent/30 lg:bg-accent/10 lg:text-accent"
              : "lg:border-line lg:bg-surface-2 lg:text-muted lg:group-hover:text-ink",
          )}
        >
          {packageCode(pkg, lookup)}
        </span>

        <span className="min-w-0">
          <span className="line-clamp-2 text-[15px] font-semibold leading-snug text-ink sm:line-clamp-1">
            {pkg.title}
          </span>
          <span className="mt-1 flex min-w-0 items-center gap-2 text-[13px] text-muted">
            <span className="shrink-0 tabular-nums text-faint">{durationLabel(pkg)}</span>
            <span aria-hidden className="shrink-0 text-faint">·</span>
            <span className="truncate">{chain}</span>
          </span>
        </span>

        <span className="flex items-center gap-2 text-right">
          <span>
            <span className="block text-[11px] font-medium text-muted">from</span>
            <span className="block text-[15px] font-semibold tabular-nums text-ink">{formatINR(pkg.priceFrom)}</span>
          </span>
          <span
            aria-hidden
            className={cn(
              "grid size-8 shrink-0 place-items-center rounded-full border transition-colors duration-300 lg:hidden",
              expanded ? "border-accent/30 bg-accent/10 text-accent" : "border-line-strong bg-surface-2 text-muted",
            )}
          >
            <ChevronDown className={cn("size-4 transition-transform duration-300", expanded && "rotate-180")} />
          </span>
        </span>
      </button>

      {/* Mobile accordion panel — never shown at lg+, where the preview pane takes over.
          Opens instantly (no height animation) and its content fades/slides in with a CSS transition
          from @starting-style (transform + opacity only); closes instantly. Mounted only while open,
          so the photo is fetched on demand. */}
      {!isDesktop && expanded && (
        <div
          id={panelId}
          role="region"
          aria-label={`${pkg.title} details`}
          className="transition-[opacity,transform] duration-300 ease-out-expo starting:opacity-0 starting:[transform:translateY(-0.5rem)] lg:hidden"
        >
          <div className="grid gap-4 px-4 pb-5 sm:px-5">
            <div className="relative aspect-[4/3] w-full overflow-hidden rounded-xl border border-line bg-surface sm:aspect-[16/9]">
              <Image
                src={packageImage(pkg, lookup)}
                alt={`${pkg.title} — ${chain}`}
                fill
                loading="lazy"
                sizes="(min-width: 1024px) 1px, calc(100vw - 4rem)"
                className="object-cover"
              />
            </div>
            {pkg.subtitle && <p className="text-[15px] leading-relaxed text-muted text-pretty">{pkg.subtitle}</p>}
            <HighlightList pkg={pkg} />
            <PackageCtas pkg={pkg} originCity={originCity} size="md" />
          </div>
        </div>
      )}
    </div>
  );
});
