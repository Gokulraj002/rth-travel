import type { MetadataRoute } from "next";
import { site } from "@/data/site";
import { destinations } from "@/data/destinations";
import { packages } from "@/data/packages";

/** Fixed date — cacheComponents forbids request-time `new Date()`. Bump on content releases. */
const LAST_MODIFIED = "2026-11-11";

export default function sitemap(): MetadataRoute.Sitemap {
  const url = (path: string) => `${site.url}${path}`;

  const staticRoutes: MetadataRoute.Sitemap = [
    { url: url("/"), changeFrequency: "weekly", priority: 1 },
    { url: url("/destinations"), changeFrequency: "monthly", priority: 0.9 },
    { url: url("/packages"), changeFrequency: "weekly", priority: 0.9 },
    { url: url("/about"), changeFrequency: "yearly", priority: 0.5 },
    { url: url("/contact"), changeFrequency: "yearly", priority: 0.7 },
  ];

  const destinationRoutes: MetadataRoute.Sitemap = destinations.map((d) => ({
    url: url(`/destinations/${d.slug}`),
    changeFrequency: "monthly",
    priority: 0.8,
    images: [url(d.image)],
  }));

  const packageRoutes: MetadataRoute.Sitemap = packages.map((p) => ({
    url: url(`/packages/${p.slug}`),
    changeFrequency: "monthly",
    priority: 0.8,
    images: [url(p.image)],
  }));

  return [...staticRoutes, ...destinationRoutes, ...packageRoutes].map((entry) => ({
    lastModified: LAST_MODIFIED,
    ...entry,
  }));
}
