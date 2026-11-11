import Image from "next/image";
import { Expand } from "lucide-react";
import { cn, pad2 } from "@/lib/utils";
import { DestinationLightbox } from "@/components/destinations/DestinationLightbox";

/** Repeating aspect pattern gives the CSS-columns grid a masonry rhythm. */
const ASPECTS = ["aspect-[4/5]", "aspect-[4/3]", "aspect-square", "aspect-[3/4]", "aspect-[16/11]"];

/*
 * Mirrors the column layout below inside Container (max-w-7xl, px-4 / sm:px-6 / lg:px-8):
 * 1 column → sm: 2 (gap-4) → lg: 3 (gap-5), one column capped at 392px.
 */
const THUMB_SIZES =
  "(min-width: 1280px) 392px, (min-width: 1024px) calc((100vw - 104px) / 3), (min-width: 640px) calc(50vw - 32px), calc(100vw - 32px)";

/**
 * Masonry-ish photo grid. Server component: the thumbnails are plain server-rendered buttons
 * with lazy images; the client `DestinationLightbox` only wires the click (event delegation via
 * `data-gallery-index`) and owns the <dialog>, which loads a full-size image only once opened.
 */
export function DestinationGallery({ images, name }: { images: string[]; name: string }) {
  const count = images.length;
  if (count === 0) return null;

  return (
    <DestinationLightbox images={images} name={name}>
      <ul className="columns-1 gap-4 sm:columns-2 lg:columns-3 lg:gap-5" role="list">
        {images.map((src, i) => (
          <li key={src} className="mb-4 break-inside-avoid lg:mb-5">
            <button
              type="button"
              data-gallery-index={i}
              className={cn(
                "group relative block w-full overflow-hidden rounded-2xl border border-line bg-surface-2 shadow-soft",
                ASPECTS[i % ASPECTS.length],
              )}
              aria-label={`Open photo ${i + 1} of ${count} of ${name} in full screen`}
            >
              <Image
                src={src}
                alt=""
                fill
                sizes={THUMB_SIZES}
                className="object-cover transition-transform duration-[1200ms] ease-out-expo group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100"
              />
              <span
                aria-hidden
                className="absolute inset-0 bg-linear-to-t from-scrim/55 via-transparent to-transparent opacity-70 transition-opacity duration-500 group-hover:opacity-100"
              />
              <span aria-hidden className="label absolute bottom-3 left-4 text-white">
                {pad2(i + 1)}
              </span>
              <span
                aria-hidden
                className="absolute right-3 bottom-3 flex size-9 items-center justify-center rounded-full border border-line bg-surface/90 text-ink shadow-soft opacity-0 transition-opacity duration-500 group-hover:opacity-100 group-focus-visible:opacity-100"
              >
                <Expand className="size-4" />
              </span>
            </button>
          </li>
        ))}
      </ul>
    </DestinationLightbox>
  );
}
