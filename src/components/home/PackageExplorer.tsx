"use client";

import Link from "next/link";
import { useCallback, useEffect, useId, useMemo, useRef, useState } from "react";
import { ArrowRight, Plane } from "lucide-react";
import { packages } from "@/data/packages";
import { Container, Eyebrow, Section } from "@/components/ui/primitives";
import { Reveal } from "@/components/ui/Reveal";
import { useJourney } from "@/lib/store";
import { cn, pad2 } from "@/lib/utils";
import { FilterTabs } from "./explorer/FilterTabs";
import { FareRow } from "./explorer/FareRow";
import { PreviewCard } from "./explorer/PreviewCard";
import { EmptyState } from "./explorer/EmptyState";
import { useIsDesktop } from "./explorer/useIsDesktop";
import { applyFilter, buildFilters, type FilterId } from "./explorer/utils";

/** Hover-intent delay, so sweeping the mouse down the list doesn't swap (and fetch) every photo. */
const HOVER_DELAY_MS = 90;

/**
 * Home-page "Package explorer" — a fare-board style list of every journey,
 * filterable by trip type, with a sticky preview pane on desktop and an
 * inline accordion on mobile.
 *
 * Perf notes: no animation library. A filter change re-keys the list, which fades in with a CSS
 * transition from `@starting-style` (opacity + transform only); hover selection never re-mounts
 * anything and nothing animates on first render.
 * The section uses `overflow-clip` (not `overflow-hidden`, which would make it a scroll
 * container and break the sticky preview); `content-visibility:auto` sits on the fare board
 * only, never on an ancestor of the sticky aside.
 */
export function PackageExplorer() {
  const origin = useJourney((s) => s.origin);
  const isDesktop = useIsDesktop();
  const uid = useId();
  const panelId = `${uid}-panel`;
  const previewId = `${uid}-preview`;

  const filters = useMemo(() => buildFilters(packages), []);
  const [filterState, setFilterState] = useState<FilterId>("all");
  // Only animate the list after the user has changed the filter (never on first paint).
  const [filterTouched, setFilterTouched] = useState(false);
  const setFilter = useCallback((id: FilterId) => {
    setFilterState(id);
    setFilterTouched(true);
  }, []);
  const filter = filters.some((f) => f.id === filterState) ? filterState : "all";
  const filtered = useMemo(() => applyFilter(packages, filter), [filter]);

  const [selectedSlug, setSelectedSlug] = useState<string | null>(null);
  const [expandedSlug, setExpandedSlug] = useState<string | null>(null);
  const selected = filtered.find((p) => p.slug === selectedSlug) ?? filtered[0];

  // Stable, slug-based handlers so memoised FareRows only re-render when their own state changes.
  const hoverTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const cancelHover = useCallback(() => clearTimeout(hoverTimer.current), []);
  const select = useCallback((slug: string) => {
    clearTimeout(hoverTimer.current);
    setSelectedSlug(slug);
  }, []);
  const hoverSelect = useCallback((slug: string) => {
    clearTimeout(hoverTimer.current);
    hoverTimer.current = setTimeout(() => setSelectedSlug(slug), HOVER_DELAY_MS);
  }, []);
  const toggle = useCallback((slug: string) => setExpandedSlug((s) => (s === slug ? null : slug)), []);
  useEffect(() => () => clearTimeout(hoverTimer.current), []);

  const count = packages.length;

  return (
    <Section id="packages" className="overflow-clip bg-night-2">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-line-strong to-transparent"
      />
      <Container>
        <Reveal className="max-w-3xl">
          <Eyebrow className="mb-4">
            Explore every journey · {count > 0 ? `${count} ${count === 1 ? "trip" : "trips"}` : "made to measure"}
          </Eyebrow>
          <h2 className="font-display text-[1.875rem] font-semibold leading-[1.12] tracking-[-0.01em] text-balance text-ink sm:text-4xl lg:text-[2.75rem]">
            Every journey from <span className="text-accent">Srinagar</span>
          </h2>
          <p className="mt-4 flex items-start gap-2.5 text-[15px] leading-relaxed text-muted sm:text-[17px]">
            <Plane aria-hidden className="mt-1 size-4 shrink-0 text-accent" />
            <span>
              Flying in from <span className="font-semibold text-ink">{origin.city}</span>? We pick you up at{" "}
              <span className="font-semibold tracking-[0.08em] text-ink">SXR</span>.
            </span>
          </p>
        </Reveal>

        {count === 0 ? (
          <div className="mt-10 sm:mt-12">
            <EmptyState originCity={origin.city} />
          </div>
        ) : (
          <div className="mt-10 grid items-start gap-6 sm:mt-12 lg:gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,25rem)] xl:grid-cols-[minmax(0,1fr)_27rem]">
            {/* Fare board — skipped for rendering until near the viewport */}
            <div className="min-w-0 overflow-hidden rounded-2xl border border-line bg-surface shadow-lift [contain-intrinsic-size:auto_52rem] [content-visibility:auto]">
              <div className="grid gap-4 px-4 pb-4 pt-5 sm:px-5">
                <div className="flex items-center justify-between gap-4">
                  <p className="label">Departures · SXR</p>
                  <p className="label tabular-nums" aria-live="polite">
                    <span className="text-ink">{pad2(filtered.length)}</span> {filtered.length === 1 ? "result" : "results"}
                  </p>
                </div>
                <FilterTabs
                  filters={filters}
                  active={filter}
                  onChange={setFilter}
                  panelId={panelId}
                  idPrefix={uid}
                />
              </div>

              <div
                aria-hidden
                className="hidden grid-cols-[3.25rem_minmax(0,1fr)_auto] gap-5 border-y border-line bg-surface-2 px-5 py-2.5 sm:grid"
              >
                <span className="label">Code</span>
                <span className="label">Journey</span>
                <span className="label text-right">Per person</span>
              </div>

              <div
                id={panelId}
                role="tabpanel"
                aria-labelledby={`${uid}-tab-${filter}`}
                className="border-t border-line sm:border-t-0"
              >
                {filtered.length === 0 ? (
                  <p className="px-5 py-10 text-center text-[15px] text-muted">
                    No journeys match this filter yet — ask us for a custom plan.
                  </p>
                ) : (
                  <ul
                    key={filter}
                    className={cn(
                      "relative divide-y divide-line",
                      filterTouched &&
                        "transition-[opacity,transform] duration-300 ease-out-expo starting:opacity-0 starting:[transform:translateY(0.5rem)]",
                    )}
                  >
                    {filtered.map((pkg) => (
                      <li key={pkg.slug}>
                        <FareRow
                          pkg={pkg}
                          isDesktop={isDesktop}
                          selected={selected?.slug === pkg.slug}
                          expanded={expandedSlug === pkg.slug}
                          onSelect={select}
                          onHover={hoverSelect}
                          onHoverEnd={cancelHover}
                          onToggle={toggle}
                          originCity={origin.city}
                          panelId={`${uid}-row-${pkg.slug}`}
                          previewId={previewId}
                        />
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>

            {/* Desktop preview pane (display:none below lg, so its lazy image is never fetched on phones) */}
            <aside
              id={previewId}
              aria-label="Selected journey"
              className="hidden lg:sticky lg:top-[calc(var(--header-h)+1.5rem)] lg:block"
            >
              {selected && <PreviewCard pkg={selected} originCity={origin.city} />}
            </aside>
          </div>
        )}

        <div className="mt-10 flex flex-col gap-4 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
          <p className="max-w-xl text-[13px] leading-relaxed text-faint">
            Starting prices per person — your final quote depends on dates, hotel category and group size.
          </p>
          <Link
            href="/packages"
            className="group inline-flex min-h-11 items-center gap-2 self-start text-sm font-semibold text-accent transition-colors hover:text-ink sm:self-auto"
          >
            View all packages
            <ArrowRight aria-hidden className="size-4 transition-transform duration-300 group-hover:translate-x-1" />
          </Link>
        </div>
      </Container>
    </Section>
  );
}
