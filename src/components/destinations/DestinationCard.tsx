import Image from "next/image";
import Link from "next/link";
import { ArrowRight, Mountain } from "lucide-react";
import type { Destination } from "@/types";
import { regionLabels } from "@/data/destinations";
import { cn, pad2 } from "@/lib/utils";

const altitudeFormat = new Intl.NumberFormat("en-IN");

/*
 * `sizes` mirror the /destinations grid: Container max-w-7xl (80rem) with px-4 / sm:px-6 / lg:px-8,
 * 1 → sm:2 (gap-6) → lg:3 columns (gap-6). One column ≈ (100vw − 112px) / 3, capped at 389px.
 */
const SIZES_MD =
  "(min-width: 1280px) 389px, (min-width: 1024px) calc((100vw - 112px) / 3), (min-width: 640px) calc(50vw - 36px), calc(100vw - 32px)";
/*
 * The feature card spans two rows on lg (≈ 389 × 900px). A 3:2 photo has to be drawn ~2.3× the box
 * width to `object-cover` that, so request ~2 column widths there to avoid a blurry upscale.
 */
const SIZES_LG =
  "(min-width: 1280px) 778px, (min-width: 1024px) calc((100vw - 112px) / 1.5), (min-width: 640px) calc(50vw - 36px), calc(100vw - 32px)";

/** Arrow circle with a slide-through hover (transform only; static under reduced motion). */
function ArrowBadge({ onPhoto }: { onPhoto?: boolean }) {
  return (
    <span
      aria-hidden
      className={cn(
        "relative flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full border transition-colors duration-500 ease-out-expo group-hover:border-primary group-hover:bg-primary group-hover:text-white",
        onPhoto ? "border-white/35 bg-scrim/40 text-white" : "border-line bg-surface text-accent",
      )}
    >
      <ArrowRight className="size-4 transition-transform duration-500 ease-out-expo group-hover:translate-x-[150%] motion-reduce:group-hover:translate-x-0" />
      <ArrowRight className="absolute size-4 -translate-x-[150%] transition-transform duration-500 ease-out-expo group-hover:translate-x-0 motion-reduce:hidden" />
    </span>
  );
}

/**
 * Destination card; the whole card links to /destinations/{slug}.
 * - `md` (default): light premium card — 4:3 photo on top, white body with label, name, tagline,
 *   altitude pill and arrow.
 * - `lg`: featured photo card (theme-dark, scrim, white text) that fills its grid area
 *   (use with `lg:row-span-2` on the wrapper).
 * Images are lazy (default) — only the PageHero image is preloaded.
 */
export function DestinationCard({
  destination,
  index,
  size = "md",
}: {
  destination: Destination;
  index?: number;
  size?: "lg" | "md";
}) {
  const { slug, name, region, tagline, image, altitudeM } = destination;
  const label = (
    <>
      {typeof index === "number" && <span className="text-accent">{pad2(index + 1)}</span>}
      {typeof index === "number" && <span aria-hidden> · </span>}
      {regionLabels[region]}
    </>
  );
  const altitude = (
    <>
      <Mountain aria-hidden className="size-3.5 text-accent" />
      {altitudeFormat.format(altitudeM)} m
    </>
  );

  if (size === "lg") {
    return (
      <Link
        href={`/destinations/${slug}`}
        className="theme-dark group relative isolate flex h-full min-h-[26rem] flex-col justify-end overflow-hidden rounded-2xl bg-night shadow-soft transition-[box-shadow,transform] duration-500 ease-out-expo hover:-translate-y-0.5 hover:shadow-lift motion-reduce:hover:translate-y-0"
      >
        <Image
          src={image}
          alt=""
          fill
          sizes={SIZES_LG}
          className="-z-10 object-cover transition-transform duration-[1200ms] ease-out-expo group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100"
        />
        <div aria-hidden className="absolute inset-0 -z-10 bg-linear-to-t from-scrim/90 via-scrim/35 to-transparent" />

        <span className="absolute top-5 left-5 inline-flex items-center gap-1.5 rounded-full bg-scrim/55 px-2.5 py-1 text-xs font-medium text-white sm:top-6 sm:left-6">
          {altitude}
        </span>

        <div className="flex items-end justify-between gap-4 p-5 sm:p-6 lg:p-7">
          <div className="min-w-0">
            <p className="label mb-2 text-white/80">{label}</p>
            <h3 className="font-display text-2xl leading-tight font-semibold break-words text-white sm:text-[1.75rem] lg:text-[2rem]">
              {name}
            </h3>
            <p className="mt-2 max-w-xs text-[15px] leading-snug text-white/85 text-pretty">{tagline}</p>
          </div>
          <ArrowBadge onPhoto />
        </div>
      </Link>
    );
  }

  return (
    <Link
      href={`/destinations/${slug}`}
      className="group flex h-full flex-col overflow-hidden rounded-2xl border border-line bg-surface shadow-soft transition-[box-shadow,transform] duration-500 ease-out-expo hover:-translate-y-0.5 hover:shadow-lift motion-reduce:hover:translate-y-0"
    >
      <div className="relative aspect-[4/3] w-full overflow-hidden bg-surface-2">
        <Image
          src={image}
          alt=""
          fill
          sizes={SIZES_MD}
          className="object-cover transition-transform duration-[1200ms] ease-out-expo group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100"
        />
      </div>

      <div className="flex flex-1 flex-col p-5 sm:p-6">
        <p className="label truncate">{label}</p>
        <h3 className="mt-2 font-display text-xl leading-tight font-semibold break-words text-ink sm:text-[1.375rem]">
          {name}
        </h3>
        <p className="mt-1.5 text-sm leading-snug text-muted text-pretty">{tagline}</p>
        <div className="mt-auto flex items-center justify-between gap-3 pt-5">
          <span className="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface-2 px-2.5 py-1 text-xs font-medium text-muted tabular-nums">
            {altitude}
          </span>
          <ArrowBadge />
        </div>
      </div>
    </Link>
  );
}
