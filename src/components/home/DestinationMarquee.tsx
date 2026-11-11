"use client";

import { Fragment, useEffect, useRef } from "react";
import { destinations } from "@/data/destinations";
import { cn } from "@/lib/utils";

/** Logo light-blue (#84a8cc) outline word — reads clearly on the dark navy home band. */
const OUTLINE_SKY = "text-transparent [-webkit-text-stroke:1px_var(--color-sky)]";

function Track({
  words,
  outlineClass,
  solidClass,
  starClass,
  offset = 0,
}: {
  words: string[];
  outlineClass: string;
  solidClass: string;
  starClass: string;
  offset?: number;
}) {
  return (
    <div className="flex shrink-0 items-center">
      {words.map((word, i) => (
        <Fragment key={`${word}-${i}`}>
          <span className={cn("px-[0.18em]", (i + offset) % 2 === 0 ? solidClass : outlineClass)}>{word}</span>
          <span className={cn("px-[0.3em] text-[0.32em]", starClass)}>✦</span>
        </Fragment>
      ))}
    </div>
  );
}

/**
 * Full-bleed infinite marquee of destination names in Playfair (≈2.5–4.5rem — a quiet band, not a
 * giant headline) on a navy band: ink / light-blue-outline words alternating with accent-blue words, leaf ✦ separators. Decorative (aria-hidden) with an sr-only list.
 *
 * Perf: the `marquee` keyframes animate `transform` only. Rows are paused by default and only run
 * (with `will-change: transform`) while an IntersectionObserver sees the band near the viewport,
 * so offscreen it costs no compositor frames or layer memory. Pauses on hover; never runs under
 * prefers-reduced-motion.
 */
export function DestinationMarquee() {
  const stageRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = stageRef.current;
    if (!el || typeof IntersectionObserver === "undefined") return;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    // Toggle a data attribute directly — no React re-render per visibility change.
    const io = new IntersectionObserver(([entry]) => el.toggleAttribute("data-playing", entry.isIntersecting), {
      rootMargin: "120px 0px",
    });
    io.observe(el);
    return () => {
      io.disconnect();
      el.removeAttribute("data-playing");
    };
  }, []);

  const names = destinations.map((d) => d.name);
  if (names.length === 0) return null;
  // Keep each track an even length so solid/outline alternation is seamless across the loop.
  const words = names.length % 2 === 0 ? names : [...names, ...names];

  // Parent-attribute selectors (specificity 0,2,0+) so the paused state beats the `animation`
  // shorthand from `animate-marquee`, which would otherwise reset play-state to running.
  const rowBase =
    "flex w-max select-none font-display font-medium leading-[1.15] whitespace-nowrap animate-marquee [:not([data-playing])>&]:[animation-play-state:paused] [[data-playing]:hover>&]:[animation-play-state:paused] [[data-playing]>&]:will-change-transform motion-reduce:animate-none";

  return (
    <section aria-label="Destinations we cover" className="relative overflow-hidden border-y border-line bg-night-2 py-8 sm:py-10">
      <ul className="sr-only">
        {destinations.map((d) => (
          <li key={d.slug}>
            {d.name} — {d.tagline}
          </li>
        ))}
      </ul>

      <div
        ref={stageRef}
        aria-hidden
        className="relative [mask-image:linear-gradient(to_right,transparent,#000_8%,#000_92%,transparent)]"
      >
        {/* Row 1 — solid ink / accent words, leaf stars (logo leaves) */}
        <div className={cn(rowBase, "text-[clamp(2.5rem,6vw,4.5rem)] text-ink")}>
          <Track words={words} solidClass="text-ink" outlineClass="text-accent" starClass="text-leaf" />
          <Track words={words} solidClass="text-ink" outlineClass="text-accent" starClass="text-leaf" />
        </div>

        {/* Row 2 — smaller, reversed: light-blue outline / accent words, leaf stars */}
        <div
          className={cn(rowBase, "mt-1 text-[clamp(1.75rem,3.6vw,2.75rem)] text-accent sm:mt-2")}
          style={{ animationDirection: "reverse", animationDuration: "55s" }}
        >
          <Track words={words} solidClass="text-accent" outlineClass={OUTLINE_SKY} starClass="text-leaf" offset={1} />
          <Track words={words} solidClass="text-accent" outlineClass={OUTLINE_SKY} starClass="text-leaf" offset={1} />
        </div>
      </div>
    </section>
  );
}
