import type { TourPackage, TripCategory } from "@/types";
import { getDestination } from "@/data/destinations";
import { categoryLabels } from "@/data/packages";

/** Price ceiling for the "Under ₹15,000" filter pill. */
export const BUDGET_CEILING = 15000;

const AIRPORT_CODES: Record<string, string> = {
  srinagar: "SXR",
  ladakh: "IXL",
  leh: "IXL",
  katra: "KAT",
  jammu: "IXJ",
};

/** Mono 3-letter "fare code" for a package, from its first destination. */
export function packageCode(pkg: TourPackage) {
  const first = pkg.destinations[0] ?? "";
  if (AIRPORT_CODES[first]) return AIRPORT_CODES[first];
  const name = getDestination(first)?.name ?? (first || pkg.title);
  return name.replace(/[^a-z]/gi, "").slice(0, 3).toUpperCase();
}

/** "Srinagar → Gulmarg → Pahalgam" */
export function destinationChain(pkg: TourPackage) {
  return pkg.destinations
    .map((slug) => getDestination(slug)?.name ?? slug.charAt(0).toUpperCase() + slug.slice(1))
    .join(" → ");
}

/** "5N / 6D" */
export function durationLabel(pkg: TourPackage) {
  return `${pkg.nights}N / ${pkg.days}D`;
}

/** Package image with a safe fallback to the first destination's hero photo. */
export function packageImage(pkg: TourPackage) {
  return (
    pkg.image ||
    getDestination(pkg.destinations[0] ?? "")?.image ||
    "/images/destinations/srinagar.jpg"
  );
}

export function enquiryMessage(pkg: TourPackage, originCity?: string) {
  const from = originCity ? ` I'll be flying in from ${originCity}.` : "";
  return `Hi Reyaan Trip Holiday! I'm interested in the "${pkg.title}" package (${durationLabel(pkg)}).${from} Please share details and availability.`;
}

export type FilterId = "all" | "budget" | TripCategory;

export interface FilterOption {
  id: FilterId;
  label: string;
}

/** All · categories present in the data (in categoryLabels order) · Under ₹15,000 (if any qualify). */
export function buildFilters(list: TourPackage[]): FilterOption[] {
  const present = new Set(list.flatMap((p) => p.categories));
  const cats = (Object.keys(categoryLabels) as TripCategory[])
    .filter((c) => present.has(c))
    .map((c) => ({ id: c, label: categoryLabels[c] }));
  const hasBudget = list.some((p) => p.priceFrom < BUDGET_CEILING);
  return [
    { id: "all", label: "All" },
    ...cats,
    ...(hasBudget ? [{ id: "budget" as const, label: "Under ₹15,000" }] : []),
  ];
}

export function applyFilter(list: TourPackage[], filter: FilterId) {
  if (filter === "all") return list;
  if (filter === "budget") return list.filter((p) => p.priceFrom < BUDGET_CEILING);
  return list.filter((p) => p.categories.includes(filter));
}
