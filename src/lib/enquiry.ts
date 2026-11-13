/**
 * Fire-and-forget lead saving to the CMS inbox (`POST ${NEXT_PUBLIC_CMS_API_URL}/api/v1/enquiries`).
 * Client-safe (no server imports). WhatsApp / email stay the primary channel: this never blocks,
 * delays or fails them — no await, errors are swallowed, and it is skipped when the env var is unset.
 *
 * The CMS requires a phone number, so only forms that collect one save leads. Today that is the
 * contact-page EnquiryForm (source "website"). The home HeroPlanner (month / travellers / destination
 * only) and the package-page WhatsApp CTAs (BookingSidebar / MobileEnquiryBar — plain links, no form)
 * collect no phone, so they just open WhatsApp and post nothing. If they ever gain a phone field, call
 * `saveEnquiry({ …, source: "planner" | "package" })` right before opening WhatsApp.
 *
 * Sent as `application/x-www-form-urlencoded` (the API accepts JSON or form posts): a CORS "simple"
 * request, so there is no preflight and `keepalive` works in every browser even if the tab navigates
 * away to WhatsApp. The CMS must list this site's origin in API_ALLOWED_ORIGINS.
 */

const API = (process.env.NEXT_PUBLIC_CMS_API_URL ?? "").trim().replace(/\/+$/, "");

/** True when leads are saved (NEXT_PUBLIC_CMS_API_URL is set at build time). */
export const enquirySavingEnabled = API !== "";

export type EnquirySource = "website" | "planner" | "package";

export interface EnquiryLead {
  name: string;
  phone: string;
  email?: string;
  /** Human-readable destination name, e.g. "Gulmarg" */
  destination?: string;
  /** Human-readable package title */
  package?: string;
  travelMonth?: string;
  adults?: number;
  children?: number;
  hotelCategory?: string;
  message?: string;
  source: EnquirySource;
  /** Honeypot — real visitors leave it empty; the API discards leads where it is filled. */
  website?: string;
}

export function saveEnquiry(lead: EnquiryLead) {
  if (!API || !lead.phone.trim()) return;
  try {
    const body = new URLSearchParams();
    for (const [key, value] of Object.entries(lead)) {
      if (value === undefined || value === "") continue;
      body.set(key, String(value));
    }
    body.set("website", lead.website ?? "");
    void fetch(`${API}/api/v1/enquiries`, {
      method: "POST",
      body,
      keepalive: true,
      credentials: "omit",
    }).catch(() => {});
  } catch {
    // Never let lead saving break the WhatsApp / email hand-off.
  }
}
