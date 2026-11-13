import { clsx, type ClassValue } from "clsx";

/** Merge conditional class names. */
export function cn(...inputs: ClassValue[]) {
  return clsx(inputs);
}

/** ₹ formatting in the Indian numbering system, e.g. 12500 → "₹12,500". */
export function formatINR(value: number) {
  return new Intl.NumberFormat("en-IN", {
    style: "currency",
    currency: "INR",
    maximumFractionDigits: 0,
  }).format(value);
}

/**
 * Build a wa.me deep link with a prefilled message. `number` is the site's WhatsApp number
 * (digits only — `site.whatsapp` from `getSite()` on the server, `useSite().whatsapp` in client components).
 */
export function whatsappLink(number: string, message?: string) {
  const base = `https://wa.me/${number.replace(/\D/g, "")}`;
  return message ? `${base}?text=${encodeURIComponent(message)}` : base;
}

/** Absolute URL for a path or an already-absolute URL (CMS uploads), e.g. for JSON-LD / sitemaps. */
export function absoluteUrl(src: string, base: string) {
  return new URL(src, base).toString();
}

/** slug → name lookup, e.g. for package routes ("Srinagar → Gulmarg"). */
export function nameMap(list: readonly { slug: string; name: string }[]): Record<string, string> {
  return Object.fromEntries(list.map((d) => [d.slug, d.name]));
}

/** "34.0837° N, 74.7973° E" */
export function formatCoords(lat: number, lng: number) {
  const ns = lat >= 0 ? "N" : "S";
  const ew = lng >= 0 ? "E" : "W";
  return `${Math.abs(lat).toFixed(4)}° ${ns}, ${Math.abs(lng).toFixed(4)}° ${ew}`;
}

/** Zero-padded index label: 0 → "01". */
export function pad2(n: number) {
  return String(n).padStart(2, "0");
}
