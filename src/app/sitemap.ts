import type { MetadataRoute } from "next";
import { getDestinations, getPackages, getSite } from "@/lib/cms";
import { absoluteUrl } from "@/lib/utils";

/** Fixed date — cacheComponents forbids request-time `new Date()`. Bump on content releases. */
const LAST_MODIFIED = "2026-11-11";

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [site, destinations, packages] = await Promise.all([getSite(), getDestinations(), getPackages()]);
  // Also handles absolute image URLs (CMS uploads).
  const url = (path: string) => absoluteUrl(path, site.url);

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
