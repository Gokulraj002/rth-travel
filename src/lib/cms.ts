/**
 * Server-only content layer.
 *
 * Reads everything the website shows from the PHP CMS (`GET ${CMS_API_URL}/api/v1/content`) in ONE
 * request, cached with `'use cache'` under the `cms` tag. The admin panel pings `POST /api/revalidate`
 * after every save, which expires the tag immediately; the 5-minute cache life is only a safety net.
 *
 * Fallback: when CMS_API_URL is unset, or the request / JSON / shape check fails, the local files in
 * `src/data/*` are used (or, at runtime, the last good CMS response this process saw), so the site
 * builds and runs with no backend at all.
 *
 * Do not import this file from a "use client" component — pass the data down as props instead.
 */
import { cacheLife, cacheTag } from "next/cache";
import { site as localSite } from "@/data/site";
import { destinations as localDestinations } from "@/data/destinations";
import { packages as localPackages } from "@/data/packages";
import { faqs as localFaqs, testimonials as localTestimonials } from "@/data/content";
import type {
  Destination,
  Faq,
  ItineraryDay,
  PhoneNumber,
  PostalAddress,
  Region,
  SiteInfo,
  Testimonial,
  TourPackage,
  TripCategory,
} from "@/types";

/** Cache tag for all CMS content — `revalidateTag(CMS_TAG, { expire: 0 })` refreshes the whole site. */
export const CMS_TAG = "cms";

const TIMEOUT_MS = 6000;

interface Content {
  site: SiteInfo;
  destinations: Destination[];
  packages: TourPackage[];
  testimonials: Testimonial[];
  faqs: Faq[];
}

const LOCAL: Content = {
  site: localSite,
  destinations: localDestinations,
  packages: localPackages,
  testimonials: localTestimonials,
  faqs: localFaqs,
};

/** Last successful CMS response in this server process — preferred over the local files if the CMS blips. */
let lastGood: Content | null = null;

let warned = false;
function warnOnce(message: string) {
  if (warned) return;
  warned = true;
  console.warn(`[cms] ${message}`);
}

/* ------------------------------------------------------------------ */
/* Loose validation / normalisation of the API payload                 */
/* ------------------------------------------------------------------ */

type Obj = Record<string, unknown>;

const REGIONS: readonly Region[] = ["kashmir", "ladakh", "jammu"];
const CATEGORIES: readonly TripCategory[] = ["honeymoon", "family", "adventure", "pilgrimage", "snow", "group"];

const isObj = (v: unknown): v is Obj => typeof v === "object" && v !== null && !Array.isArray(v);
const str = (v: unknown) => (typeof v === "string" ? v.trim() : typeof v === "number" ? String(v) : "");
const num = (v: unknown, fallback = 0) => {
  const n = typeof v === "number" ? v : typeof v === "string" && v.trim() !== "" ? Number(v) : NaN;
  return Number.isFinite(n) ? n : fallback;
};
const strList = (v: unknown) => (Array.isArray(v) ? v.map(str).filter(Boolean) : []);
const list = (v: unknown): Obj[] => {
  if (!Array.isArray(v)) throw new Error("expected an array");
  return v.filter(isObj);
};
const isRegion = (v: string): v is Region => (REGIONS as readonly string[]).includes(v);
const isCategory = (v: string): v is TripCategory => (CATEGORIES as readonly string[]).includes(v);

/**
 * Image paths: `/images/…` (website files) and absolute http(s) URLs (admin uploads) are kept;
 * a stray relative `/uploads/…` is made absolute against the CMS origin. Anything else → "".
 */
function media(v: unknown, apiBase: string): string {
  const src = str(v);
  if (!src) return "";
  if (/^https?:\/\//i.test(src)) return src;
  if (src.startsWith("/uploads/")) return new URL(src, apiBase).href;
  if (src.startsWith("/") && !src.startsWith("//")) return src;
  return "";
}

function parseDestination(o: Obj, apiBase: string): Destination | null {
  const slug = str(o.slug);
  const name = str(o.name);
  const region = str(o.region);
  const geo = isObj(o.geo) ? o.geo : {};
  const lat = num(geo.lat, NaN);
  const lng = num(geo.lng, NaN);
  if (!slug || !name || !isRegion(region) || !Number.isFinite(lat) || !Number.isFinite(lng)) return null;

  const image = media(o.image, apiBase) || localDestinations.find((d) => d.slug === slug)?.image || "";
  if (!image) return null;
  const cardImage = media(o.cardImage, apiBase);

  return {
    slug,
    name,
    displayName: str(o.displayName) || name.toUpperCase(),
    region,
    tagline: str(o.tagline),
    description: str(o.description),
    geo: { lat, lng },
    altitudeM: num(o.altitudeM),
    bestTime: str(o.bestTime),
    distanceNote: str(o.distanceNote),
    highlights: strList(o.highlights),
    thingsToDo: strList(o.thingsToDo),
    image,
    ...(cardImage ? { cardImage } : {}),
    gallery: Array.isArray(o.gallery) ? o.gallery.map((g) => media(g, apiBase)).filter(Boolean) : [],
    credit: str(o.credit),
  };
}

function parseItineraryDay(o: Obj, i: number): ItineraryDay {
  const stay = str(o.stay);
  return {
    day: num(o.day, i + 1),
    title: str(o.title),
    description: str(o.description),
    ...(stay ? { stay } : {}),
  };
}

function parsePackage(o: Obj, apiBase: string, destinations: Destination[]): TourPackage | null {
  const slug = str(o.slug);
  const title = str(o.title);
  const region = str(o.region);
  if (!slug || !title || !isRegion(region)) return null;

  const stops = strList(o.destinations);
  const image =
    media(o.image, apiBase) ||
    destinations.find((d) => d.slug === stops[0])?.image ||
    "/images/destinations/srinagar.jpg";
  const nights = Math.max(0, Math.round(num(o.nights)));

  return {
    slug,
    title,
    subtitle: str(o.subtitle),
    destinations: stops,
    region,
    categories: strList(o.categories).filter(isCategory),
    nights,
    days: Math.max(0, Math.round(num(o.days, nights + 1))),
    priceFrom: Math.max(0, num(o.priceFrom)),
    image,
    highlights: strList(o.highlights),
    inclusions: strList(o.inclusions),
    exclusions: strList(o.exclusions),
    itinerary: Array.isArray(o.itinerary) ? o.itinerary.filter(isObj).map(parseItineraryDay) : [],
    ...(o.featured === true || o.featured === 1 || o.featured === "1" ? { featured: true } : {}),
  };
}

function parseTestimonial(o: Obj): Testimonial | null {
  const name = str(o.name);
  const quote = str(o.quote);
  if (!name || !quote) return null;
  return {
    name,
    from: str(o.from),
    trip: str(o.trip),
    quote,
    rating: Math.min(5, Math.max(0, num(o.rating, 5))),
  };
}

function parseFaq(o: Obj): Faq | null {
  const q = str(o.q);
  const a = str(o.a);
  return q && a ? { q, a } : null;
}

/**
 * CMS settings merged over the local `site` object. url, logo, banner, shortName, taglineParts and
 * destinationsLine always stay local; an empty CMS value keeps the local one, so nothing renders blank.
 */
function mergeSite(settings: Obj): SiteInfo {
  const phones: PhoneNumber[] = (Array.isArray(settings.phones) ? settings.phones : [])
    .filter(isObj)
    .map((p) => {
      const display = str(p.display);
      const raw = str(p.raw).replace(/[^\d+]/g, "") || display.replace(/[^\d+]/g, "");
      return { display: display || raw, raw };
    })
    .filter((p) => p.raw);
  const addresses: PostalAddress[] = (Array.isArray(settings.addresses) ? settings.addresses : [])
    .filter(isObj)
    .map((a) => ({ line1: str(a.line1), line2: str(a.line2) }))
    .filter((a) => a.line1 || a.line2);
  const emails = strList(settings.emails);
  const social = isObj(settings.social) ? settings.social : {};

  return {
    ...localSite,
    name: str(settings.name) || localSite.name,
    tagline: str(settings.tagline) || localSite.tagline,
    description: str(settings.description) || localSite.description,
    phones: phones.length > 0 ? phones : localSite.phones,
    whatsapp: str(settings.whatsapp).replace(/\D/g, "") || localSite.whatsapp,
    emails: emails.length > 0 ? emails : localSite.emails,
    addresses: addresses.length > 0 ? addresses : localSite.addresses,
    mapQuery: str(settings.mapQuery) || localSite.mapQuery,
    social: {
      instagram: str(social.instagram),
      facebook: str(social.facebook),
      youtube: str(social.youtube),
    },
  };
}

const compact = <T>(items: (T | null)[]) => items.filter((x): x is T => x !== null);

function parseContent(json: unknown, apiBase: string): Content {
  if (!isObj(json) || !isObj(json.settings)) throw new Error("response has no `settings` object");
  const destinations = compact(list(json.destinations).map((o) => parseDestination(o, apiBase)));
  return {
    site: mergeSite(json.settings),
    destinations,
    packages: compact(list(json.packages).map((o) => parsePackage(o, apiBase, destinations))),
    testimonials: compact(list(json.testimonials).map(parseTestimonial)),
    faqs: compact(list(json.faqs).map(parseFaq)),
  };
}

/* ------------------------------------------------------------------ */
/* The one cached request                                              */
/* ------------------------------------------------------------------ */

async function loadContent(): Promise<Content> {
  "use cache";
  cacheTag(CMS_TAG);

  const base = process.env.CMS_API_URL?.trim().replace(/\/+$/, "");
  if (!base) {
    // No backend configured: local data, cached like real content.
    cacheLife({ stale: 300, revalidate: 300, expire: 86400 });
    warnOnce("CMS_API_URL is not set — using the local content in src/data/*.");
    return LOCAL;
  }

  try {
    const headers: Record<string, string> = { Accept: "application/json" };
    const key = process.env.CMS_API_KEY?.trim();
    if (key) headers["X-RTH-Key"] = key;

    const res = await fetch(`${base}/api/v1/content`, { headers, signal: AbortSignal.timeout(TIMEOUT_MS) });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const content = parseContent(await res.json(), base);

    // ~5 minutes: a safety net only — admin saves expire the `cms` tag straight away.
    cacheLife({ stale: 300, revalidate: 300, expire: 86400 });
    lastGood = content;
    return content;
  } catch (error) {
    // Retry sooner while the CMS is unreachable.
    cacheLife({ stale: 300, revalidate: 60, expire: 86400 });
    warnOnce(
      `Could not load ${base}/api/v1/content (${error instanceof Error ? error.message : String(error)}) — ` +
        `using ${lastGood ? "the last good CMS response" : "the local content in src/data/*"}.`,
    );
    return lastGood ?? LOCAL;
  }
}

/* ------------------------------------------------------------------ */
/* Public getters (server components, generateMetadata, route files)   */
/* ------------------------------------------------------------------ */

export async function getSite(): Promise<SiteInfo> {
  return (await loadContent()).site;
}

export async function getDestinations(): Promise<Destination[]> {
  return (await loadContent()).destinations;
}

export async function getDestination(slug: string): Promise<Destination | undefined> {
  return (await getDestinations()).find((d) => d.slug === slug);
}

export async function getPackages(): Promise<TourPackage[]> {
  return (await loadContent()).packages;
}

export async function getPackage(slug: string): Promise<TourPackage | undefined> {
  return (await getPackages()).find((p) => p.slug === slug);
}

export async function getPackagesForDestination(destinationSlug: string): Promise<TourPackage[]> {
  return (await getPackages()).filter((p) => p.destinations.includes(destinationSlug));
}

export async function getFeaturedPackages(): Promise<TourPackage[]> {
  return (await getPackages()).filter((p) => p.featured);
}

export async function getTestimonials(): Promise<Testimonial[]> {
  return (await loadContent()).testimonials;
}

export async function getFaqs(): Promise<Faq[]> {
  return (await loadContent()).faqs;
}
