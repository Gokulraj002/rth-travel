import { clsx, type ClassValue } from "clsx";
import { site } from "@/data/site";

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

/** Build a wa.me deep link with a prefilled message. */
export function whatsappLink(message?: string) {
  const base = `https://wa.me/${site.whatsapp}`;
  return message ? `${base}?text=${encodeURIComponent(message)}` : base;
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
