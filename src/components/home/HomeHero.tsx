import { ArrowDown, ArrowRight } from "lucide-react";
import { site } from "@/data/site";
import { Container } from "@/components/ui/primitives";
import { HeroTitle } from "./hero/HeroTitle";
import { HeroPlanner } from "./hero/HeroPlanner";
import { HeroFadeUp } from "./hero/HeroFadeUp";
import { HeroStyles } from "./hero/HeroStyles";
import { HeroSpace } from "./hero/HeroSpace";

/**
 * Home hero — deep-space navy: a fine starfield, an Earth limb rising from the bottom edge,
 * "H🌍LIDAY" circled by an orbit ring, and a frosted-glass trip planner.
 *
 * Server component; only the trip planner is a client component. All entrances are pure CSS
 * (HeroStyles) and everything is in the server HTML, so nothing above the fold waits for
 * hydration. No images beyond the 22 KB earth texture used by the "O".
 *
 * The site header is sticky and in flow, so no top padding is needed to clear it. From md up the
 * hero fills the rest of the first screen; on phones it is content height. `--hero-cap` is the
 * visible height of the planet limb; the content's bottom padding keeps the CTAs above it.
 */
export function HomeHero() {
  return (
    <section
      aria-labelledby="home-hero-title"
      className="theme-dark relative isolate flex flex-col overflow-hidden bg-night [--hero-cap:clamp(60px,10vw,150px)] md:min-h-[calc(100svh-var(--header-h))]"
    >
      <HeroStyles />
      <HeroSpace />

      <Container className="flex flex-1 flex-col pb-[calc(var(--hero-cap)+1.75rem)] pt-4 sm:pt-5">
        {/* Meta row — condensed to the centre item on phones */}
        <HeroFadeUp
          delay={0}
          y={0}
          className="flex items-center justify-center gap-6 border-b border-line pb-3 md:justify-between"
        >
          <p className="label hidden md:block">
            34.0837° N, 74.7973° E
          </p>
          <p className="label inline-flex items-center gap-2 text-muted">
            <span aria-hidden="true" className="size-1.5 rounded-full bg-leaf shadow-[0_0_8px_rgb(76_193_61/0.6)]" />
            Srinagar · Kashmir
          </p>
          <p className="label hidden md:block">Kashmir · Ladakh · Katra · Jammu</p>
        </HeroFadeUp>

        <div className="flex flex-1 flex-col items-center justify-center pt-10 sm:pt-14 md:pt-10">
          <HeroTitle id="home-hero-title" />

          <HeroFadeUp delay={180} y={10}>
            <p className="mt-5 flex flex-wrap items-center justify-center gap-x-2.5 gap-y-1 text-base text-muted sm:mt-8 sm:text-lg md:mt-10 lg:mt-12">
              <span className="sr-only">{site.tagline}</span>
              {site.taglineParts.map((part, i) => (
                <span key={part} aria-hidden="true" className="inline-flex items-center gap-2.5">
                  {i > 0 && <span className="size-1 rounded-full bg-accent/60" />}
                  {part}
                </span>
              ))}
            </p>
          </HeroFadeUp>

          <HeroFadeUp delay={240} className="mt-8 w-full sm:mt-10">
            <HeroPlanner />
          </HeroFadeUp>

          <HeroFadeUp
            delay={320}
            y={8}
            className="mt-5 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 sm:mt-7"
          >
            <a
              href="#packages"
              className="group inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-full border border-line-strong bg-white/[0.04] px-5 text-sm font-semibold text-ink shadow-[inset_0_1px_0_rgb(255_255_255/0.06)] transition-colors duration-300 hover:border-accent/60 hover:bg-white/[0.08] md:backdrop-blur-md"
            >
              Explore packages
              <ArrowRight
                className="size-4 transition-transform duration-300 group-hover:translate-x-0.5"
                aria-hidden="true"
              />
            </a>
            <a
              href="#journey"
              className="inline-flex min-h-11 items-center gap-1.5 rounded-full px-2 text-sm font-semibold text-accent transition-colors hover:text-ink"
            >
              See the journey
              <ArrowDown className="hero-bob size-4" aria-hidden="true" />
            </a>
          </HeroFadeUp>
        </div>
      </Container>
    </section>
  );
}
