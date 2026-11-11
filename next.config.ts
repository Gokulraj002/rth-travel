import type { NextConfig } from "next";

const YEAR = 60 * 60 * 24 * 365;

const nextConfig: NextConfig = {
  cacheComponents: true,
  partialPrefetching: true,
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
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
