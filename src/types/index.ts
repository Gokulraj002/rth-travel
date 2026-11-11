/**
 * Shared domain types for the Reyaan Trip Holiday site.
 * Every data file in `src/data` and every component consumes these — keep them the single source of truth.
 */

export type Region = "kashmir" | "ladakh" | "jammu";

export type TripCategory =
  | "honeymoon"
  | "family"
  | "adventure"
  | "pilgrimage"
  | "snow"
  | "group";

export interface GeoPoint {
  lat: number;
  lng: number;
}

export interface Destination {
  slug: string;
  /** Short display name, e.g. "Srinagar" */
  name: string;
  /** Giant display word used in cinematic headings, e.g. "SRINAGAR" */
  displayName: string;
  region: Region;
  /** e.g. "Summer capital of Jammu & Kashmir" */
  tagline: string;
  description: string;
  geo: GeoPoint;
  /** Altitude in metres above sea level */
  altitudeM: number;
  bestTime: string;
  /** Typical travel time from Srinagar (or from Jammu for Katra/Jammu) */
  distanceNote: string;
  highlights: string[];
  thingsToDo: string[];
  /** Path under /public, e.g. "/images/destinations/srinagar.jpg" */
  image: string;
  gallery: string[];
  /** Photographer credit for the hero image (Unsplash) */
  credit: string;
}

export interface ItineraryDay {
  day: number;
  title: string;
  description: string;
  /** Optional overnight stay location */
  stay?: string;
}

export interface TourPackage {
  slug: string;
  title: string;
  subtitle: string;
  /** Destination slugs covered, in visit order */
  destinations: string[];
  region: Region;
  categories: TripCategory[];
  nights: number;
  days: number;
  /** Starting price per person in INR — PLACEHOLDER values, owner should confirm */
  priceFrom: number;
  image: string;
  highlights: string[];
  inclusions: string[];
  exclusions: string[];
  itinerary: ItineraryDay[];
  featured?: boolean;
}

export interface OriginCity {
  code: string; // IATA, e.g. "DEL"
  city: string;
  geo: GeoPoint;
}

export interface Vehicle {
  name: string;
  seats: string;
  description: string;
  idealFor: string;
  features: string[];
}

export interface Testimonial {
  name: string;
  from: string;
  trip: string;
  quote: string;
  rating: number;
}

export interface Faq {
  q: string;
  a: string;
}
