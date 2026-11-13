import type { Region, TripCategory } from "@/types";

/*
 * Display labels (local — not edited in the CMS). Kept in their own tiny module so client components
 * can import them without pulling the full destination / package data into the browser bundle.
 */

export const regionLabels: Record<Region, string> = {
  kashmir: "Kashmir Valley",
  ladakh: "Ladakh",
  jammu: "Jammu Region",
};

export const categoryLabels: Record<TripCategory, string> = {
  honeymoon: "Honeymoon",
  family: "Family",
  adventure: "Adventure",
  pilgrimage: "Pilgrimage",
  snow: "Snow & Winter",
  group: "Group Tours",
};
