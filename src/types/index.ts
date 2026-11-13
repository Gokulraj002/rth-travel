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
  /** Optional brighter/cleaner photo for the large featured card (falls back to `image`). */
  cardImage?: string;
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

export interface PhoneNumber {
  /** Human format, e.g. "+91 60060 45211" */
  display: string;
  /** tel: format, e.g. "+916006045211" */
  raw: string;
}

export interface PostalAddress {
  line1: string;
  line2: string;
}

/**
 * Business details. Editable fields come from the CMS settings (see `src/lib/cms.ts`);
 * `url`, `logo`, `banner`, `shortName`, `taglineParts` and `destinationsLine` always stay local (`src/data/site.ts`).
 */
export interface SiteInfo {
  name: string;
  shortName: string;
  tagline: string;
  taglineParts: readonly string[];
  description: string;
  url: string;
  phones: readonly PhoneNumber[];
  /** Number used for WhatsApp deep links (digits only, no plus sign) */
  whatsapp: string;
  emails: readonly string[];
  addresses: readonly PostalAddress[];
  mapQuery: string;
  destinationsLine: readonly string[];
  logo: string;
  banner: string;
  social: { instagram: string; facebook: string; youtube?: string };
}

/** The slice of `SiteInfo` that client components need (passed down via `SiteProvider`). */
export type SiteContact = Pick<SiteInfo, "name" | "whatsapp" | "phones" | "emails" | "addresses">;

/** Destination fields the home-page globe journey (client) renders — keeps its RSC payload small. */
export type JourneyStop = Pick<
  Destination,
  "slug" | "name" | "displayName" | "region" | "tagline" | "geo" | "altitudeM" | "bestTime" | "highlights"
>;

/** Minimal destination info for client lookups (route names, fallback photos). */
export type DestinationRef = Pick<Destination, "slug" | "name" | "region" | "image">;
