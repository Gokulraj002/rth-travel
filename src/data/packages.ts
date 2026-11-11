import type { TourPackage, TripCategory } from "@/types";

/**
 * Tour packages. PRICES ARE PLACEHOLDERS — confirm with the owner before launch.
 * (Stub — filled in by the data agent.)
 */
export const packages: TourPackage[] = [];

export const categoryLabels: Record<TripCategory, string> = {
  honeymoon: "Honeymoon",
  family: "Family",
  adventure: "Adventure",
  pilgrimage: "Pilgrimage",
  snow: "Snow & Winter",
  group: "Group Tours",
};

export function getPackage(slug: string) {
  return packages.find((p) => p.slug === slug);
}

export function getPackagesForDestination(destinationSlug: string) {
  return packages.filter((p) => p.destinations.includes(destinationSlug));
}

export const featuredPackages = () => packages.filter((p) => p.featured);
