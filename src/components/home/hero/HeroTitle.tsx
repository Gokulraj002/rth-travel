import type { CSSProperties } from "react";
import { EarthO } from "./EarthO";
import { HeroOrbit } from "./HeroOrbit";

/** Visual letters of the display word; `null` marks the slot taken by the earth. */
const WORD: (string | null)[] = ["H", null, "L", "I", "D", "A", "Y"];

/** ms — letters rise in quick succession; the whole lockup settles in < 1 s. */
const LETTERS_START = 60;
const LETTER_STAGGER = 40;
const EARTH_DELAY = 120;
const ORBIT_DELAY = 260;

const delay = (ms: number): CSSProperties => ({ animationDelay: `${ms}ms` });

/**
 * Brand lockup: a small tracked "REYAAN TRIP" eyebrow over "H🌍LIDAY" in Playfair Display,
 * circled by a slow orbit ring (HeroOrbit). Server component. Screen readers get the plain
 * heading text; all visual parts are aria-hidden.
 *
 * Entrance is pure CSS (see HeroStyles) and transform-only: the text is fully opaque in the
 * server HTML, so it paints (and counts for LCP) immediately; it just rises into place.
 */
export function HeroTitle({ id }: { id?: string }) {
  return (
    <h1 id={id} className="relative flex flex-col items-center text-center">
      <span className="sr-only">Reyaan Trip Holiday</span>

      {/* Eyebrow — hairlines either side, like the logo's wordmark rule */}
      <span
        aria-hidden="true"
        className="hero-rise label flex items-center gap-3 leading-none tracking-[0.38em] text-accent sm:gap-4 sm:text-xs"
        style={{ ...delay(0), "--rise": "0.6em" } as CSSProperties}
      >
        <span className="h-px w-8 bg-linear-to-r from-transparent to-accent/70 sm:w-12" />
        {/* trailing tracking would push the text off-centre — pull it back with a negative margin */}
        <span className="-mr-[0.38em]">Reyaan Trip</span>
        <span className="h-px w-8 bg-linear-to-l from-transparent to-accent/70 sm:w-12" />
      </span>

      <span
        aria-hidden="true"
        className="relative isolate mt-4 -mr-[0.04em] block whitespace-nowrap font-display text-[clamp(3rem,10vw,8rem)] font-semibold uppercase leading-[1.04] tracking-[0.04em] text-ink [text-shadow:0_0_0.35em_rgb(79_141_255/0.18)] sm:mt-5"
      >
        <HeroOrbit delay={ORBIT_DELAY} />

        {WORD.map((char, i) => {
          if (char === null) {
            return (
              <span key="earth" className="relative mx-[0.015em] inline-block">
                {/* Soft blue halo behind the globe; blooms as it lands, then rests */}
                <span
                  className="hero-bloom pointer-events-none absolute -inset-[0.24em] rounded-full bg-[radial-gradient(circle,rgb(79_141_255/0.45)_0%,rgb(79_141_255/0.14)_42%,transparent_68%)]"
                  style={delay(EARTH_DELAY + 30)}
                />
                <span className="hero-earth-in relative inline-block align-baseline" style={delay(EARTH_DELAY)}>
                  <EarthO />
                </span>
              </span>
            );
          }

          return (
            <span key={char} className="hero-rise inline-block" style={delay(LETTERS_START + i * LETTER_STAGGER)}>
              {char}
            </span>
          );
        })}
      </span>
    </h1>
  );
}
