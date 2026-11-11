"use client";

import { useEffect, useRef } from "react";
import { destinations, regionLabels } from "@/data/destinations";
import { cn, pad2 } from "@/lib/utils";
import { scrollToStep } from "./shared";

const total = destinations.length;

/** Desktop (≥lg): numbered vertical list down the left edge with a progress track. */
export function DesktopRail({ activeIndex }: { activeIndex: number }) {
  const progress = activeIndex < 0 ? 0 : (activeIndex + 1) / total;

  return (
    <div className="pointer-events-none absolute inset-y-0 left-6 hidden items-center lg:flex xl:left-10">
      <nav aria-label="Journey stops" className="pointer-events-auto">
        <p className="label mb-5 tabular-nums">
          Journey <span className={activeIndex >= 0 ? "text-accent" : undefined}>{pad2(Math.max(activeIndex + 1, 0))}</span>
          <span className="text-faint"> / {pad2(total)}</span>
        </p>

        <div className="flex gap-4">
          {/* progress track — scaleY (compositor-only), not height */}
          <div aria-hidden className="relative w-px shrink-0 bg-line-strong">
            <div
              className="absolute inset-0 origin-top bg-accent transition-transform duration-700 ease-out-expo"
              style={{ transform: `scaleY(${progress})` }}
            />
          </div>

          <ol className="flex flex-col">
            {destinations.map((d, i) => {
              const active = i === activeIndex;
              const visited = activeIndex >= 0 && i < activeIndex;
              return (
                <li key={d.slug}>
                  <button
                    type="button"
                    onClick={() => scrollToStep(i)}
                    aria-current={active ? "step" : undefined}
                    className="group flex w-full items-start gap-3 rounded-md py-2 pr-2 text-left"
                  >
                    <span
                      aria-hidden
                      className={cn(
                        "mt-[0.45rem] size-1.5 shrink-0 rounded-full transition-[background-color,box-shadow] duration-500",
                        active ? "bg-accent shadow-[0_0_12px_2px_rgb(15_108_189/0.8)]" : "bg-transparent",
                      )}
                    />
                    <span
                      className={cn(
                        "w-[2ch] pt-px text-xs font-semibold tabular-nums transition-colors",
                        active ? "text-accent" : visited ? "text-muted" : "text-faint",
                      )}
                    >
                      {pad2(i + 1)}
                    </span>
                    <span className="min-w-0">
                      <span
                        className={cn(
                          "block text-[14px] font-semibold leading-tight transition-colors",
                          active ? "text-accent" : "text-muted group-hover:text-ink",
                        )}
                      >
                        {d.name}
                      </span>
                      <span
                        className={cn(
                          "mt-1 block text-[11px] font-medium uppercase tracking-[0.14em] transition-colors",
                          active ? "text-accent/70" : "text-faint",
                        )}
                      >
                        {regionLabels[d.region]}
                      </span>
                    </span>
                  </button>
                </li>
              );
            })}
          </ol>
        </div>

        <a
          href="#packages"
          className="label mt-6 inline-flex items-center gap-1.5 transition-colors hover:text-accent"
        >
          Skip journey <span aria-hidden>→</span>
        </a>
      </nav>
    </div>
  );
}

/**
 * Mobile (<lg): horizontal, scroll-snapping chip row at the top of the stage.
 * Each button is a 44px-tall tap target around a 32px visual chip. Below md the chips
 * use a solid translucent fill instead of backdrop blur (cheaper over the live canvas).
 */
export function MobileChips({ activeIndex }: { activeIndex: number }) {
  const rowRef = useRef<HTMLOListElement>(null);

  // Centre the active chip within the row only. Equivalent to
  // scrollIntoView({ inline: "center" }) but scoped to the row: Element.scrollIntoView
  // would also scroll the page (and fight Lenis / the scroll-driven journey).
  useEffect(() => {
    const row = rowRef.current;
    if (!row || activeIndex < 0) return;
    const chip = row.children[activeIndex] as HTMLElement | undefined;
    if (!chip) return;
    const rowBox = row.getBoundingClientRect();
    const chipBox = chip.getBoundingClientRect();
    const left = row.scrollLeft + (chipBox.left - rowBox.left) - (rowBox.width - chipBox.width) / 2;
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    row.scrollTo({ left, behavior: reduce ? "auto" : "smooth" });
  }, [activeIndex]);

  return (
    <nav aria-label="Journey stops" className="pointer-events-auto absolute inset-x-0 top-2 lg:hidden">
      <div className="mb-1 flex items-center justify-between pl-4 pr-2">
        <p className="label tabular-nums">
          Journey <span className={activeIndex >= 0 ? "text-accent" : undefined}>{pad2(Math.max(activeIndex + 1, 0))}</span>
          <span> / {pad2(total)}</span>
        </p>
        <a
          href="#packages"
          className="label -my-2.5 inline-flex min-h-11 items-center gap-1 px-2 transition-colors hover:text-accent"
        >
          Skip <span aria-hidden>→</span>
        </a>
      </div>
      <ol
        ref={rowRef}
        className="flex snap-x snap-mandatory gap-2 overflow-x-auto overscroll-x-contain scroll-px-4 px-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        {destinations.map((d, i) => {
          const active = i === activeIndex;
          return (
            <li key={d.slug} className="shrink-0 snap-center">
              <button
                type="button"
                onClick={() => scrollToStep(i)}
                aria-current={active ? "step" : undefined}
                className="group flex min-h-11 items-center rounded-full focus-visible:outline-none"
              >
                <span
                  className={cn(
                    "inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-[13px] font-semibold transition-colors",
                    "group-focus-visible:ring-2 group-focus-visible:ring-accent",
                    active
                      ? "border-accent/60 bg-primary text-white shadow-[0_6px_20px_-6px_rgb(15_108_189/0.8)]"
                      : "border-line bg-night/80 text-muted group-hover:text-ink md:glass",
                  )}
                >
                  <span className={cn("text-[11px] tabular-nums", active ? "text-white" : "text-faint")}>
                    {pad2(i + 1)}
                  </span>
                  {d.name}
                </span>
              </button>
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
