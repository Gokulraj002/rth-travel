import Image from "next/image";
import Link from "next/link";
import { ArrowRight } from "lucide-react";
import type { TourPackage } from "@/types";
import { categoryLabels } from "@/data/labels";
import { cn, formatINR } from "@/lib/utils";

/** Human destination names in visit order, e.g. ["Srinagar", "Gulmarg"]. `names`: slug → name (see `nameMap`). */
export function destinationChain(pkg: TourPackage, names: Record<string, string>) {
  return pkg.destinations.map((slug) => names[slug] ?? slug);
}

/**
 * `sizes` for a card inside `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6` within <Container>
 * (max-w-7xl, px-4 / sm:px-6 / lg:px-8). This is the standard package grid everywhere
 * (packages page, "You may also like", destination pages).
 */
export const PACKAGE_CARD_SIZES =
  "(min-width: 1280px) 389px, (min-width: 1024px) calc(33.3vw - 37px), (min-width: 640px) calc(50vw - 36px), calc(100vw - 32px)";

/** Same grid as PACKAGE_CARD_SIZES (kept for existing imports). */
export const PACKAGE_CARD_SIZES_SM2_LG3 = PACKAGE_CARD_SIZES;

/**
 * Standard travel card for a tour package: 4:3 photo on top (only a duration chip over it),
 * white body below with trip types, title, route and price. The whole card links to /packages/{slug}.
 * Works in both server and client trees (no hooks). Images are always lazy — only the page hero preloads.
 * `names` is a slug → name lookup of the destinations (`nameMap(destinations)`), used for the route line.
 */
export function PackageCard({
  pkg,
  names,
  sizes = PACKAGE_CARD_SIZES,
}: {
  pkg: TourPackage;
  names: Record<string, string>;
  sizes?: string;
}) {
  const chain = destinationChain(pkg, names);
  const categories = pkg.categories.slice(0, 3).map((c) => categoryLabels[c]);

  return (
    <Link
      href={`/packages/${pkg.slug}`}
      className={cn(
        "group flex h-full w-full min-w-0 flex-col overflow-hidden rounded-2xl border border-line bg-surface shadow-soft",
        "transition-[transform,box-shadow,border-color] duration-300 ease-out-expo",
        "hover:-translate-y-0.5 hover:border-line-strong hover:shadow-lift",
        "focus-visible:-translate-y-0.5 motion-reduce:hover:translate-y-0",
      )}
    >
      <div className="relative aspect-[4/3] w-full overflow-hidden bg-surface-2">
        <Image
          src={pkg.image}
          alt=""
          fill
          loading="lazy"
          sizes={sizes}
          className="object-cover transition-transform duration-700 ease-out-expo group-hover:scale-[1.04] motion-reduce:group-hover:scale-100"
        />
        <span className="absolute left-3 top-3 rounded-full bg-surface/95 px-2.5 py-1 text-xs font-semibold tabular-nums text-ink shadow-soft">
          {pkg.nights}N / {pkg.days}D
        </span>
      </div>

      <div className="flex min-w-0 flex-1 flex-col p-5">
        {categories.length > 0 && (
          <p className="label truncate text-accent">
            <span className="sr-only">Trip type: </span>
            {categories.join(" · ")}
          </p>
        )}

        <h3 className="mt-2 line-clamp-2 font-display text-lg font-semibold leading-snug text-ink text-balance sm:text-xl">
          {pkg.title}
        </h3>

        {chain.length > 0 && (
          <p className="mt-1.5 truncate text-[13px] text-muted">
            <span className="sr-only">Route: </span>
            {chain.join(" → ")}
          </p>
        )}

        {/* Spacer keeps the price row pinned to the bottom when cards in a row differ in height. */}
        <div aria-hidden className="min-h-4 flex-1" />

        <div className="flex items-end justify-between gap-3 border-t border-line pt-4">
          <p className="min-w-0 text-[13px] leading-tight text-muted">
            from{" "}
            <span className="font-sans text-lg font-semibold text-ink sm:text-xl">{formatINR(pkg.priceFrom)}</span>
            <span className="whitespace-nowrap"> / person</span>
          </p>
          <span
            aria-hidden
            className="inline-flex shrink-0 items-center gap-1 pb-0.5 text-[13px] font-semibold text-primary"
          >
            View
            <ArrowRight className="size-3.5 transition-transform duration-300 group-hover:translate-x-0.5 motion-reduce:group-hover:translate-x-0" />
          </span>
        </div>
      </div>
    </Link>
  );
}
