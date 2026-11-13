"use client";

import { memo, useId, useMemo, useState, type ReactNode } from "react";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import { ChevronDown, Compass, RotateCcw } from "lucide-react";
import type { Region, TourPackage, TripCategory } from "@/types";
import { categoryLabels } from "@/data/labels";
import { regionLabels } from "@/data/labels";
import { cn } from "@/lib/utils";
import { PACKAGE_CARD_SIZES, PackageCard } from "./PackageCard";

type DurationBucket = "any" | "short" | "mid" | "long";
type SortKey = "recommended" | "price-asc" | "price-desc" | "shortest";

const durationOptions: { value: DurationBucket; label: string }[] = [
  { value: "any", label: "Any length" },
  { value: "short", label: "≤ 4 nights" },
  { value: "mid", label: "5 – 7 nights" },
  { value: "long", label: "8+ nights" },
];

const sortOptions: { value: SortKey; label: string }[] = [
  { value: "recommended", label: "Recommended" },
  { value: "price-asc", label: "Price: low → high" },
  { value: "price-desc", label: "Price: high → low" },
  { value: "shortest", label: "Shortest first" },
];

function inBucket(nights: number, bucket: DurationBucket) {
  if (bucket === "short") return nights <= 4;
  if (bucket === "mid") return nights >= 5 && nights <= 7;
  if (bucket === "long") return nights >= 8;
  return true;
}

const ease = [0.16, 1, 0.3, 1] as const;

/** Cards only depend on their package, so filter/sort changes never re-render (or re-mount) their images. */
const MemoPackageCard = memo(PackageCard);

/**
 * Filter bar + animated grid of PackageCards. Filters live in client state (no useSearchParams).
 * Pass `packages` and the destination `names` lookup (`nameMap(destinations)`) from a server component.
 *
 * Perf: nothing animates on first render (`AnimatePresence initial={false}` → server HTML is fully visible);
 * motion layout animations only run on filter/sort changes. Cards are keyed by slug and memoised, and the
 * <ul> stays mounted even when empty, so surviving cards (and their <img>) are never re-mounted.
 */
export function PackageGrid({
  packages,
  names,
  initialCategory = "all",
  initialRegion = "all",
  className,
}: {
  packages: TourPackage[];
  names: Record<string, string>;
  initialCategory?: TripCategory | "all";
  initialRegion?: Region | "all";
  className?: string;
}) {
  const reduce = useReducedMotion();
  const uid = useId();

  const [category, setCategory] = useState<TripCategory | "all">(initialCategory);
  const [region, setRegion] = useState<Region | "all">(initialRegion);
  const [duration, setDuration] = useState<DurationBucket>("any");
  const [sort, setSort] = useState<SortKey>("recommended");

  // Only offer filters that actually match something in the data.
  const categories = useMemo(() => {
    const present = new Set(packages.flatMap((p) => p.categories));
    return (Object.keys(categoryLabels) as TripCategory[]).filter((c) => present.has(c));
  }, [packages]);
  const regions = useMemo(() => {
    const present = new Set(packages.map((p) => p.region));
    return (Object.keys(regionLabels) as Region[]).filter((r) => present.has(r));
  }, [packages]);

  const results = useMemo(() => {
    const filtered = packages.filter(
      (p) =>
        (category === "all" || p.categories.includes(category)) &&
        (region === "all" || p.region === region) &&
        inBucket(p.nights, duration),
    );
    const sorted = filtered.map((p, i) => ({ p, i }));
    sorted.sort((a, b) => {
      switch (sort) {
        case "price-asc":
          return a.p.priceFrom - b.p.priceFrom || a.i - b.i;
        case "price-desc":
          return b.p.priceFrom - a.p.priceFrom || a.i - b.i;
        case "shortest":
          return a.p.nights - b.p.nights || a.p.priceFrom - b.p.priceFrom;
        default:
          return Number(!!b.p.featured) - Number(!!a.p.featured) || a.i - b.i;
      }
    });
    return sorted.map((s) => s.p);
  }, [packages, category, region, duration, sort]);

  const isFiltered = category !== "all" || region !== "all" || duration !== "any";
  const reset = () => {
    setCategory("all");
    setRegion("all");
    setDuration("any");
    setSort("recommended");
  };

  const pillOptions: { value: TripCategory | "all"; label: string }[] = [
    { value: "all", label: "All trips" },
    ...categories.map((c) => ({ value: c, label: categoryLabels[c] })),
  ];

  return (
    <div className={className}>
      {/* ───────── Filter bar ───────── */}
      {/* Solid white card instead of `glass`: it sits on the flat page bg, so a backdrop blur buys nothing. */}
      <div className="rounded-2xl border border-line bg-surface p-4 shadow-soft sm:p-6">
        <fieldset>
          <legend className="label mb-3 text-faint">Trip type</legend>
          <div className="flex flex-wrap gap-2">
            {pillOptions.map((o) => {
              const active = category === o.value;
              return (
                <button
                  key={o.value}
                  type="button"
                  aria-pressed={active}
                  onClick={() => setCategory(o.value)}
                  className={cn(
                    "inline-flex min-h-11 items-center rounded-full border px-4 py-2 text-sm font-medium transition-colors duration-300",
                    active
                      ? "border-primary bg-primary text-white"
                      : "border-line bg-surface text-muted hover:border-primary/40 hover:bg-primary/5 hover:text-ink",
                  )}
                >
                  {o.label}
                </button>
              );
            })}
          </div>
        </fieldset>

        <div className="mt-5 grid grid-cols-1 gap-4 border-t border-line pt-5 sm:grid-cols-3 [&>*]:min-w-0">
          <SelectField
            id={`${uid}-region`}
            label="Region"
            value={region}
            onChange={(v) => setRegion(v as Region | "all")}
          >
            <option value="all">All regions</option>
            {regions.map((r) => (
              <option key={r} value={r}>
                {regionLabels[r]}
              </option>
            ))}
          </SelectField>
          <SelectField
            id={`${uid}-duration`}
            label="Duration"
            value={duration}
            onChange={(v) => setDuration(v as DurationBucket)}
          >
            {durationOptions.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </SelectField>
          <SelectField id={`${uid}-sort`} label="Sort by" value={sort} onChange={(v) => setSort(v as SortKey)}>
            {sortOptions.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </SelectField>
        </div>
      </div>

      {/* ───────── Result count ───────── */}
      <div className="mt-8 flex flex-wrap items-center justify-between gap-3">
        <p className="label text-muted" aria-live="polite" aria-atomic="true">
          <span className="text-ink">{results.length}</span>{" "}
          {results.length === 1 ? "package" : "packages"}
          {isFiltered ? " match your filters" : " available"}
        </p>
        {isFiltered && (
          <button
            type="button"
            onClick={reset}
            className="-mx-2 inline-flex min-h-11 items-center gap-1.5 px-2 text-[13px] font-semibold text-primary transition-colors hover:text-primary-bright"
          >
            <RotateCcw className="size-3.5" aria-hidden />
            Clear filters
          </button>
        )}
      </div>

      {/* ───────── Grid ───────── */}
      <motion.ul
        layout={!reduce}
        className={cn(
          "relative grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 [&>li]:min-w-0",
          results.length > 0 && "mt-6",
        )}
      >
        <AnimatePresence mode="popLayout" initial={false}>
          {results.map((pkg) => (
            <motion.li
              key={pkg.slug}
              layout={!reduce}
              initial={reduce ? false : { opacity: 0, y: 24, scale: 0.98 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={reduce ? { opacity: 0, transition: { duration: 0 } } : { opacity: 0, scale: 0.96, transition: { duration: 0.25 } }}
              transition={reduce ? { duration: 0 } : { duration: 0.6, ease }}
            >
              <MemoPackageCard pkg={pkg} names={names} sizes={PACKAGE_CARD_SIZES} />
            </motion.li>
          ))}
        </AnimatePresence>
      </motion.ul>

      <AnimatePresence initial={false}>
        {results.length === 0 && (
          <motion.div
            key="empty"
            initial={reduce ? false : { opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: reduce ? 0 : 0.5, ease }}
            className="mt-6 flex flex-col items-center rounded-2xl border border-dashed border-line-strong bg-surface px-5 py-14 text-center sm:px-6 sm:py-16"
          >
            <span className="grid size-12 place-items-center rounded-full bg-primary/10 text-primary">
              <Compass className="size-6" aria-hidden />
            </span>
            <h3 className="mt-5 font-display text-xl font-semibold text-ink sm:text-2xl">
              {packages.length === 0 ? "Packages coming soon" : "No trips match — yet"}
            </h3>
            <p className="mt-3 max-w-md text-[15px] leading-relaxed text-muted">
              {packages.length === 0
                ? "Our team is finalising this season's itineraries. Message us and we'll plan one around you."
                : "Every itinerary we run can be tailored. Loosen a filter, or tell us what you have in mind and we'll build it around you."}
            </p>
            {isFiltered && (
              <button
                type="button"
                onClick={reset}
                className="mt-6 inline-flex h-11 items-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-white transition-colors hover:bg-primary-bright"
              >
                <RotateCcw className="size-4" aria-hidden />
                Reset filters
              </button>
            )}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}

function SelectField({
  id,
  label,
  value,
  onChange,
  children,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  children: ReactNode;
}) {
  return (
    <div className="min-w-0">
      <label htmlFor={id} className="label mb-2 block text-faint">
        {label}
      </label>
      <div className="relative">
        <select
          id={id}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="h-11 w-full min-w-0 cursor-pointer appearance-none truncate rounded-xl border border-line bg-surface-2 pl-4 pr-10 text-base text-ink transition-colors hover:border-line-strong focus:border-primary focus:bg-surface focus:outline-none"
        >
          {children}
        </select>
        <ChevronDown
          aria-hidden
          className="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-faint"
        />
      </div>
    </div>
  );
}
