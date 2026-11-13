import type { Metadata } from "next";
import Link from "next/link";
import { ArrowLeft, Compass, Plane } from "lucide-react";
import { ButtonLink } from "@/components/ui/Button";
import { Container } from "@/components/ui/primitives";
import { getDestinations } from "@/lib/cms";

export const metadata: Metadata = {
  title: "Page not found",
  robots: { index: false },
};

/**
 * Spinning CSS earth used as the "0" in 404. Compositor-only: a strip 4× the circle's width holds the
 * 2:1 texture tiled twice and slides by exactly one tile with `transform` (reusing the `marquee`
 * keyframes, reversed so the surface moves west → east). The inset shading sits on a fixed overlay.
 */
function EarthZero() {
  return (
    <span aria-hidden className="relative mx-[0.04em] inline-block size-[0.74em] shrink-0 align-middle">
      {/* orbit ring + plane */}
      <span className="absolute -inset-[0.16em] animate-spin-slow rounded-full border border-dashed border-primary/25">
        <Plane className="absolute -top-[0.06em] left-1/2 size-[0.12em] -translate-x-1/2 rotate-90 text-accent" />
      </span>
      {/* atmosphere glow */}
      <span className="absolute -inset-[0.05em] rounded-full bg-[radial-gradient(circle,rgb(0_84_144/0.18)_55%,transparent_72%)]" />
      <span
        className="absolute inset-0 overflow-hidden rounded-full bg-brand-deep"
        style={{ boxShadow: "0 0.08em 0.24em rgb(0 48 96 / 0.3)" }}
      >
        <span
          className="absolute inset-y-0 left-0 w-[400%] bg-repeat-x will-change-transform motion-safe:animate-[marquee_30s_linear_infinite_reverse] motion-reduce:-translate-x-1/4"
          style={{ backgroundImage: "url(/textures/earth-blue-marble-640.webp)", backgroundSize: "50% 100%" }}
        />
        <span
          className="absolute inset-0 rounded-full"
          style={{
            boxShadow:
              "inset -0.12em -0.05em 0.2em rgb(0 20 50 / 0.6), inset 0.05em 0.03em 0.12em rgb(255 255 255 / 0.3)",
          }}
        />
      </span>
    </span>
  );
}

export default async function NotFound() {
  const popular = (await getDestinations()).slice(0, 4);

  return (
    <section className="relative isolate flex min-h-[calc(100svh-var(--header-h))] items-center overflow-hidden bg-night py-16 sm:py-20">
      {/* soft sky: pale blue at the top fading into the page, with a faint logo-blue horizon */}
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(70%_55%_at_50%_0%,rgb(132_168_204/0.28),transparent_70%),radial-gradient(60%_45%_at_50%_105%,rgb(0_84_144/0.07),transparent_70%),linear-gradient(180deg,#e6effa_0%,var(--night)_65%)]"
      />

      <Container className="flex flex-col items-center text-center">
        <p className="label mb-6 inline-flex items-center gap-2 text-accent">
          <Compass className="size-3.5" aria-hidden />
          Error 404 — Off the map
        </p>

        <h1
          className="flex items-center justify-center font-display leading-none font-semibold text-brand-deep tabular-nums"
          style={{ fontSize: "clamp(5.5rem, 24vw, 8rem)" }}
        >
          <span className="sr-only">404 — </span>
          <span aria-hidden>4</span>
          <EarthZero />
          <span aria-hidden>4</span>
        </h1>

        <h2 className="mt-8 font-display text-[1.875rem] font-semibold leading-[1.15] tracking-[-0.01em] text-balance text-ink sm:text-4xl">
          Lost above the clouds
        </h2>
        <p className="mt-4 max-w-xl text-[15px] leading-relaxed text-muted text-pretty sm:text-[17px]">
          The page you were looking for has drifted off our flight path. It may have moved, or the link may be
          mistyped — let&apos;s get you back on course to the valleys.
        </p>

        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
          <ButtonLink href="/" variant="primary" size="lg">
            <ArrowLeft className="size-4" aria-hidden />
            Back to home
          </ButtonLink>
          <ButtonLink href="/destinations" variant="secondary" size="lg">
            See destinations
          </ButtonLink>
        </div>

        {popular.length > 0 && (
          <nav aria-label="Popular destinations" className="mt-12 flex flex-col items-center gap-4">
            <p className="label">Or land somewhere beautiful</p>
            <ul className="flex flex-wrap justify-center gap-2">
              {popular.map((d) => (
                <li key={d.slug}>
                  <Link
                    href={`/destinations/${d.slug}`}
                    className="inline-flex min-h-10 items-center rounded-full border border-line bg-surface px-4 text-sm font-medium text-muted shadow-soft transition-colors duration-300 hover:border-primary/40 hover:text-primary"
                  >
                    {d.name}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        )}
      </Container>
    </section>
  );
}
