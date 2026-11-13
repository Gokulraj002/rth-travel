/**
 * Generates backend/database/seed.sql from the website's current content in src/data/*.
 * Run:  npm run backend:seed      (node --experimental-strip-types scripts/export-seed.mts)
 * The admin panel then starts with exactly what the website shows today.
 */
import { writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { packages } from "../src/data/packages.ts";
import { destinations } from "../src/data/destinations.ts";
import { testimonials, faqs } from "../src/data/content.ts";
import { site } from "../src/data/site.ts";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const out = resolve(root, "backend/database/seed.sql");

/** MySQL string literal (default sql_mode: backslash escapes enabled). */
const q = (v: unknown): string => {
  if (v === null || v === undefined) return "NULL";
  if (typeof v === "number") return Number.isFinite(v) ? String(v) : "NULL";
  if (typeof v === "boolean") return v ? "1" : "0";
  const s = String(v)
    .replace(/\\/g, "\\\\")
    .replace(/'/g, "\\'")
    .replace(/\n/g, "\\n")
    .replace(/\r/g, "\\r")
    .replace(/\x00/g, "\\0");
  return `'${s}'`;
};
const row = (vals: unknown[]) => `(${vals.map(q).join(", ")})`;
const insert = (table: string, cols: string[], rows: unknown[][]) =>
  rows.length === 0
    ? ""
    : `INSERT INTO ${table} (${cols.map((c) => "`" + c + "`").join(", ")}) VALUES\n  ${rows.map(row).join(",\n  ")};\n`;

const lines: string[] = [];
lines.push(
  "-- ============================================================================",
  "-- Seed data generated from the website's src/data/* by scripts/export-seed.mts",
  `-- Generated: ${new Date().toISOString()}`,
  "-- Import AFTER schema.sql. Safe to re-run on an empty database only.",
  "-- ============================================================================",
  "",
  "SET NAMES utf8mb4;",
  "SET FOREIGN_KEY_CHECKS = 0;",
  "",
);

// ── Destinations ──
lines.push("-- Destinations");
lines.push(
  insert(
    "destinations",
    ["id", "slug", "name", "display_name", "region", "tagline", "description", "lat", "lng", "altitude_m", "best_time", "distance_note", "image", "card_image", "credit", "status", "sort_order"],
    destinations.map((d, i) => [i + 1, d.slug, d.name, d.displayName, d.region, d.tagline, d.description, d.geo.lat, d.geo.lng, d.altitudeM, d.bestTime, d.distanceNote, d.image, d.cardImage ?? null, d.credit, "published", (i + 1) * 10]),
  ),
);
lines.push(
  insert(
    "destination_items",
    ["destination_id", "type", "text", "position"],
    destinations.flatMap((d, i) => [
      ...d.highlights.map((t, p) => [i + 1, "highlight", t, p]),
      ...d.thingsToDo.map((t, p) => [i + 1, "thing", t, p]),
    ]),
  ),
);
lines.push(
  insert(
    "destination_gallery",
    ["destination_id", "image", "position"],
    destinations.flatMap((d, i) => d.gallery.map((img, p) => [i + 1, img, p])),
  ),
);

// ── Packages ──
lines.push("-- Packages (PRICES ARE PLACEHOLDERS — edit them in the admin panel)");
lines.push(
  insert(
    "packages",
    ["id", "slug", "title", "subtitle", "region", "nights", "days", "price_from", "image", "featured", "status", "sort_order"],
    packages.map((p, i) => [i + 1, p.slug, p.title, p.subtitle, p.region, p.nights, p.days, p.priceFrom, p.image, p.featured ? 1 : 0, "published", (i + 1) * 10]),
  ),
);
lines.push(insert("package_categories", ["package_id", "category", "position"], packages.flatMap((p, i) => p.categories.map((c, pos) => [i + 1, c, pos]))));
lines.push(insert("package_destinations", ["package_id", "destination_slug", "position"], packages.flatMap((p, i) => p.destinations.map((d, pos) => [i + 1, d, pos]))));
lines.push(
  insert(
    "package_items",
    ["package_id", "type", "text", "position"],
    packages.flatMap((p, i) => [
      ...p.highlights.map((t, pos) => [i + 1, "highlight", t, pos]),
      ...p.inclusions.map((t, pos) => [i + 1, "inclusion", t, pos]),
      ...p.exclusions.map((t, pos) => [i + 1, "exclusion", t, pos]),
    ]),
  ),
);
lines.push(
  insert(
    "package_itinerary",
    ["package_id", "day", "title", "description", "stay"],
    packages.flatMap((p, i) => p.itinerary.map((d) => [i + 1, d.day, d.title, d.description, d.stay ?? null])),
  ),
);

// ── Reviews & FAQs ──
lines.push("-- Testimonials (SAMPLE reviews — replace with real ones in the admin panel)");
lines.push(insert("testimonials", ["name", "from_city", "trip", "quote", "rating", "status", "sort_order"], testimonials.map((t, i) => [t.name, t.from, t.trip, t.quote, t.rating, "published", (i + 1) * 10])));
lines.push("-- FAQs");
lines.push(insert("faqs", ["question", "answer", "status", "sort_order"], faqs.map((f, i) => [f.q, f.a, "published", (i + 1) * 10])));

// ── Settings ──
const settings: Record<string, string> = {
  site_name: site.name,
  tagline: site.tagline,
  description: site.description,
  phone_primary: site.phones[0]?.display ?? "",
  phone_secondary: site.phones[1]?.display ?? "",
  whatsapp_number: site.whatsapp,
  email_primary: site.emails[0] ?? "",
  email_secondary: site.emails[1] ?? "",
  address_1_line1: site.addresses[0]?.line1 ?? "",
  address_1_line2: site.addresses[0]?.line2 ?? "",
  address_2_line1: site.addresses[1]?.line1 ?? "",
  address_2_line2: site.addresses[1]?.line2 ?? "",
  map_query: site.mapQuery,
  instagram_url: site.social.instagram,
  facebook_url: site.social.facebook,
  youtube_url: "",
};
lines.push("-- Site settings");
lines.push(insert("settings", ["key", "value"], Object.entries(settings).map(([k, v]) => [k, v ?? ""])));

lines.push("SET FOREIGN_KEY_CHECKS = 1;", "");
writeFileSync(out, lines.join("\n"));
console.log(`Wrote ${out}: ${destinations.length} destinations, ${packages.length} packages, ${testimonials.length} testimonials, ${faqs.length} FAQs, ${Object.keys(settings).length} settings.`);
