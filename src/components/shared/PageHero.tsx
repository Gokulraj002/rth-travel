import Image from "next/image";
import type { CSSProperties, ReactNode } from "react";
import { Container } from "@/components/ui/primitives";

/**
 * Inner-page hero: full-bleed photo under a dark navy scrim, Playfair title bottom-left.
 * Always `theme-dark` (tokens flip to the navy scope) — the photo carries white text.
 *
 * Server component. The subtle scale-in uses a CSS keyframe gated with `motion-safe:`,
 * so it costs no client JS and is fully static under prefers-reduced-motion. The photo is
 * the LCP element on inner pages: it is `preload`ed and fully opaque from the first frame —
 * only `transform: scale` animates.
 *
 * The site header is sticky and in normal flow, so the hero only needs modest top padding.
 * Title size is `clamp(2.25rem, 6.5vw, 4.75rem)`, additionally capped by the longest word so a
 * single long word (e.g. "DESTINATIONS") always fits on one line at 360px; `overflow-wrap:anywhere`
 * is the last-resort fallback.
 */
export function PageHero({
  eyebrow,
  title,
  subtitle,
  image,
  imageAlt,
  children,
}: {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  image: string;
  imageAlt?: string;
  children?: ReactNode;
}) {
  const longestWord = Math.max(4, ...title.split(/\s+/).map((w) => w.length));
  // Playfair semibold uppercase averages ~0.74em per glyph; container gutters ≤ 4rem.
  const titleStyle: CSSProperties = {
    fontSize: `min(clamp(2.25rem, 6.5vw, 4.75rem), calc((min(100vw, 80rem) - 4rem) / ${(longestWord * 0.76).toFixed(2)}))`,
  };

  const crumbs = eyebrow
    ? eyebrow
        .split("/")
        .map((c) => c.trim())
        .filter(Boolean)
    : [];

  return (
    <section className="theme-dark relative isolate flex min-h-[46svh] flex-col justify-end overflow-hidden bg-night text-white sm:min-h-[52svh]">
      <style href="rth-page-hero" precedence="default">{`
        @keyframes rth-hero-zoom { from { transform: scale(1.08); } to { transform: scale(1); } }
      `}</style>

      {/* Photo */}
      <div
        aria-hidden={imageAlt ? undefined : true}
        className="absolute inset-0 -z-20 will-change-transform motion-safe:animate-[rth-hero-zoom_2.2s_cubic-bezier(0.16,1,0.3,1)_both]"
      >
        <Image
          src={image}
          alt={imageAlt ?? ""}
          fill
          preload
          sizes="100vw"
          className="object-cover"
        />
      </div>

      {/* Scrims (always dark navy): soft top edge, bottom (title), left (copy column) */}
      <div aria-hidden className="absolute inset-x-0 top-0 -z-10 h-24 bg-linear-to-b from-scrim/40 to-transparent" />
      <div aria-hidden className="absolute inset-x-0 bottom-0 -z-10 h-4/5 bg-linear-to-t from-scrim/85 via-scrim/40 to-transparent" />
      <div aria-hidden className="absolute inset-0 -z-10 bg-linear-to-r from-scrim/55 via-scrim/10 to-transparent" />

      <Container className="relative pt-16 pb-16 sm:pt-20 sm:pb-20">
        {crumbs.length > 0 && (
          <p className="label mb-4 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[0.75rem] text-white/85 [text-shadow:0_1px_10px_rgb(2_11_26/0.85)]">
            <span aria-hidden className="h-px w-8 bg-accent" />
            {crumbs.map((c, i) => (
              <span key={`${c}-${i}`} className="flex items-center gap-2.5">
                {i > 0 && (
                  <span aria-hidden className="text-white/50">
                    /
                  </span>
                )}
                <span className={i === crumbs.length - 1 ? "text-white" : undefined}>{c}</span>
              </span>
            ))}
          </p>
        )}

        <h1
          style={titleStyle}
          className="max-w-4xl font-display font-semibold uppercase leading-[1.05] text-balance text-white [overflow-wrap:anywhere] drop-shadow-[0_4px_24px_rgb(2_11_26/0.45)]"
        >
          {title}
        </h1>

        {subtitle && (
          <p className="mt-4 max-w-2xl text-base leading-relaxed text-white/85 text-pretty sm:mt-5 sm:text-lg">
            {subtitle}
          </p>
        )}

        {children && <div className="mt-6 sm:mt-8">{children}</div>}
      </Container>
    </section>
  );
}
