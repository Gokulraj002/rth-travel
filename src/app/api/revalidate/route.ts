import { timingSafeEqual } from "node:crypto";
import { revalidateTag } from "next/cache";
import { CMS_TAG } from "@/lib/cms";

/**
 * POST /api/revalidate — called by the PHP admin (Revalidator::ping) after every content save.
 *
 *   Header  x-revalidate-secret: <REVALIDATE_SECRET>
 *   Body    { "tags"?: string[] }   (optional — the whole site reads one `cms` cache entry anyway)
 *
 * Always expires the `cms` tag immediately (`{ expire: 0 }`), so the next visit to any page re-fetches
 * the CMS and re-renders. 401 on a wrong/missing secret, 500 if REVALIDATE_SECRET is not configured.
 *
 * Never cached: only POST is exported, and non-GET Route Handlers are always run at request time
 * (with Cache Components a route-segment `dynamic` export is neither needed nor allowed).
 */

function secretMatches(given: string, expected: string) {
  const a = Buffer.from(given);
  const b = Buffer.from(expected);
  // timingSafeEqual needs equal lengths; compare against itself to keep the timing uniform.
  if (a.length !== b.length) {
    timingSafeEqual(b, b);
    return false;
  }
  return timingSafeEqual(a, b);
}

export async function POST(request: Request) {
  const expected = process.env.REVALIDATE_SECRET;
  if (!expected) {
    return Response.json({ error: "REVALIDATE_SECRET is not configured" }, { status: 500 });
  }

  const given = request.headers.get("x-revalidate-secret") ?? "";
  if (!secretMatches(given, expected)) {
    return Response.json({ error: "Unauthorized" }, { status: 401 });
  }

  // Optional body { tags?: string[] } (e.g. ["cms", "packages"]). Every page reads the same single
  // `cms` cache entry, so the tag list doesn't change what is refreshed; an empty/invalid body is fine.
  await request.json().catch(() => null);

  revalidateTag(CMS_TAG, { expire: 0 });

  return Response.json({ revalidated: true, now: Date.now() }, { headers: { "Cache-Control": "no-store" } });
}
