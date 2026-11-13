import type { NextConfig } from "next";

const YEAR = 60 * 60 * 24 * 365;

type RemotePattern = NonNullable<NonNullable<NextConfig["images"]>["remotePatterns"]>[number];

/**
 * next/image allow-list for CMS admin uploads (e.g. https://admin.yourdomain.com/uploads/…).
 * CMS_MEDIA_URL may be a base URL ("https://admin.yourdomain.com/uploads") or a glob
 * ("https://admin.yourdomain.com/uploads/**"); a trailing "/**" is implied. If it is unset but
 * CMS_API_URL is set, uploads are allowed from `${CMS_API_URL origin}/uploads/**`.
 * Website photos under /public ("/images/…") need no entry.
 */
function cmsMediaPatterns(): RemotePattern[] {
  const fromMedia = process.env.CMS_MEDIA_URL?.trim();
  const fromApi = process.env.CMS_API_URL?.trim();
  const raw = fromMedia || (fromApi ? `${fromApi.replace(/\/+$/, "")}/uploads` : "");
  if (!raw) return [];
  try {
    const url = new URL(raw.replace(/\/\*\*$/, "").replace(/\/+$/, ""));
    const pathname = url.pathname.replace(/\/+$/, "");
    return [
      {
        protocol: url.protocol === "http:" ? "http" : "https",
        hostname: url.hostname,
        port: url.port,
        pathname: `${pathname}/**`,
        search: "",
      },
    ];
  } catch {
    console.warn(`[next.config] Ignoring invalid CMS_MEDIA_URL / CMS_API_URL: "${raw}"`);
    return [];
  }
}

const nextConfig: NextConfig = {
  cacheComponents: true,
  partialPrefetching: true,
  images: {
    // AVIF first (smallest), WebP fallback; the optimizer picks via the Accept header.
    formats: ["image/avif", "image/webp"],
    // Default list minus 2048/3840 — source photos are ~1600px, so anything above 2560 is wasted bytes.
    deviceSizes: [640, 750, 828, 1080, 1200, 1920, 2560],
    // Next 16 requires an allowlist; components omit `quality`, so the default 75 is the only one we serve.
    qualities: [75],
    // Local /public photos rarely change. Note: optimized images can't be invalidated in place —
    // if a photo is replaced, rename the file (or clear `.next/cache/images`).
    minimumCacheTTL: YEAR,
    // CMS uploads (absolute URLs from the API). Upload filenames are unique, so the long TTL above is safe.
    remotePatterns: cmsMediaPatterns(),
  },
  async headers() {
    return [
      {
        // Globe textures: large, never edited in place.
        source: "/textures/:path*",
        headers: [{ key: "Cache-Control", value: `public, max-age=${YEAR}, immutable` }],
      },
      {
        // Logo / banner / OG image: long-lived but revalidated, since brand placeholders may still be swapped.
        source: "/brand/:path*",
        headers: [{ key: "Cache-Control", value: `public, max-age=${60 * 60 * 24 * 30}, stale-while-revalidate=${YEAR}` }],
      },
    ];
  },
};

export default nextConfig;
