"use client";

import { useRef, type KeyboardEvent } from "react";
import { cn } from "@/lib/utils";
import type { FilterId, FilterOption } from "./utils";

/**
 * Region-style filter pills using the WAI-ARIA tabs pattern (automatic activation, roving tabindex).
 * Mobile: one horizontally scrolling, snapping row of 44px-tall pills (40px at lg).
 */
export function FilterTabs({
  filters,
  active,
  onChange,
  panelId,
  idPrefix,
}: {
  filters: FilterOption[];
  active: FilterId;
  onChange: (id: FilterId) => void;
  panelId: string;
  idPrefix: string;
}) {
  const refs = useRef<(HTMLButtonElement | null)[]>([]);

  const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
    const current = filters.findIndex((f) => f.id === active);
    let next = -1;
    if (e.key === "ArrowRight" || e.key === "ArrowDown") next = (current + 1) % filters.length;
    else if (e.key === "ArrowLeft" || e.key === "ArrowUp") next = (current - 1 + filters.length) % filters.length;
    else if (e.key === "Home") next = 0;
    else if (e.key === "End") next = filters.length - 1;
    if (next < 0) return;
    e.preventDefault();
    onChange(filters[next].id);
    const el = refs.current[next];
    el?.focus();
    el?.scrollIntoView({ block: "nearest", inline: "nearest" });
  };

  return (
    <div
      role="tablist"
      aria-label="Filter journeys"
      onKeyDown={onKeyDown}
      className="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-2 overflow-x-auto overscroll-x-contain px-4 pb-1 [scrollbar-width:none] sm:-mx-5 sm:scroll-px-5 sm:px-5 [&::-webkit-scrollbar]:hidden"
    >
      {filters.map((f, i) => {
        const selected = f.id === active;
        return (
          <button
            key={f.id}
            ref={(el) => {
              refs.current[i] = el;
            }}
            id={`${idPrefix}-tab-${f.id}`}
            type="button"
            role="tab"
            aria-selected={selected}
            aria-controls={panelId}
            tabIndex={selected ? 0 : -1}
            onClick={() => onChange(f.id)}
            className={cn(
              "inline-flex h-11 shrink-0 snap-start items-center whitespace-nowrap rounded-full border px-4 text-[13px] font-semibold transition-colors duration-300 lg:h-10",
              selected
                ? "border-primary bg-primary text-white shadow-[0_6px_18px_-8px_var(--color-primary)]"
                : "border-line bg-surface text-muted hover:border-line-strong hover:bg-surface-2 hover:text-ink",
            )}
          >
            {f.label}
          </button>
        );
      })}
    </div>
  );
}
