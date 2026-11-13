"use client";

import { useEffect, useRef, type CSSProperties } from "react";
import type { JourneyStop } from "@/types";
import GlobeStage from "@/components/three/GlobeStage";
import { useJourney } from "@/lib/store";
import { stepId } from "./journey/shared";
import { OriginSelect } from "./journey/OriginSelect";
import { JourneyIntro } from "./journey/JourneyIntro";
import { DesktopRail, MobileChips } from "./journey/JourneyRail";
import { DesktopPanel, MobileSheet } from "./journey/JourneyPanel";

/**
 * Overlay UI — the only part that subscribes to `activeIndex`, so the WebGL
 * stage never re-renders when the journey advances.
 */
function JourneyOverlay({ stops }: { stops: JourneyStop[] }) {
  const activeIndex = useJourney((s) => s.activeIndex);

  return (
    <div className="pointer-events-none absolute inset-0">
      {/* Readability: soft gradients on the edges */}
      <div aria-hidden className="absolute inset-x-0 top-0 h-36 bg-linear-to-b from-night/85 via-night/40 to-transparent" />
      <div
        aria-hidden
        className="absolute inset-x-0 bottom-0 h-[55%] bg-linear-to-t from-night via-night/70 to-transparent lg:h-56 lg:via-night/40"
      />
      <div
        aria-hidden
        className="absolute inset-y-0 left-0 hidden w-[34%] bg-linear-to-r from-night/90 via-night/45 to-transparent lg:block"
      />
      <div
        aria-hidden
        className="absolute inset-y-0 right-0 hidden w-[48%] bg-linear-to-l from-night/90 via-night/50 to-transparent lg:block"
      />

      {/* Top-right origin chip (desktop; mobile shows it inside the intro caption).
          The stage already starts below the sticky header, so only a small inset is needed. */}
      <div className="absolute right-6 top-5 hidden lg:block xl:right-10">
        <OriginSelect />
      </div>

      <DesktopRail stops={stops} activeIndex={activeIndex} />
      <MobileChips stops={stops} activeIndex={activeIndex} />

      {activeIndex < 0 && <JourneyIntro key="intro" />}

      <DesktopPanel stops={stops} activeIndex={activeIndex} />
      <MobileSheet stops={stops} activeIndex={activeIndex} />
    </div>
  );
}

/**
 * The signature scroll-driven globe journey (always an immersive dark section: `theme-dark`).
 * A sticky 3D earth fills the viewport below the sticky site header while invisible
 * full-height steps below it drive `useJourney.activeIndex` (−1 intro → 0…n−1 destinations).
 * Sticky CSS handles the pinning; the step divs are only scroll targets for the rail / chips
 * (`scrollToStep`, which lands each step just under the header via `scroll-padding-top`).
 * `stops` (one per destination, in journey order) comes from the server parent (CMS).
 */
export function GlobeJourney({ stops }: { stops: JourneyStop[] }) {
  /** Intro step (−1) followed by one step per destination. */
  const stepCount = stops.length + 1;
  const sectionRef = useRef<HTMLElement>(null);
  const stageRef = useRef<HTMLDivElement>(null);
  const barRef = useRef<HTMLDivElement>(null);

  // One rAF-throttled scroll listener drives everything (no GSAP): the active step (written to
  // the store only when it changes — no per-frame React state) and the progress bar (direct DOM
  // transform). It is attached only while the section is near the viewport.
  useEffect(() => {
    const section = sectionRef.current;
    if (!section) return;
    const steps = stepCount;
    let lastIndex: number | null = null;
    let lastBar = -1;
    let frame = 0;
    let listening = false;
    // Sticky header offset + stage height, measured on enter / resize (not per frame).
    let headerH = 0;
    let stageH = 0;
    const measure = () => {
      const stage = stageRef.current;
      if (!stage) return;
      headerH = parseFloat(getComputedStyle(stage).top) || 0;
      stageH = stage.offsetHeight;
    };

    const update = () => {
      frame = 0;
      const rect = section.getBoundingClientRect();
      const vh = window.innerHeight;
      // The centre of the visible area (below the sticky header) travels through the section;
      // each 100svh step owns 1/steps of it.
      const total = Math.max(1, rect.height);
      const progress = ((vh + headerH) / 2 - rect.top) / total;

      let index: number;
      let bar: number;
      if (progress < 0) {
        // Above the section → whole-earth intro.
        index = -1;
        bar = 0;
      } else {
        // Past the end the clamp keeps the last stop (no reset).
        // Step k (0 = intro) → activeIndex k − 1.
        index = Math.min(steps - 1, Math.max(0, Math.floor(progress * steps))) - 1;
        // Bar fills while the sticky stage is pinned (section top at the header → section bottom
        // at the stage bottom).
        bar = Math.min(1, Math.max(0, (headerH - rect.top) / Math.max(1, total - (stageH || vh - headerH))));
      }

      if (index !== lastIndex) {
        lastIndex = index;
        const s = useJourney.getState();
        if (s.activeIndex !== index) s.setActiveIndex(index);
      }
      if (bar !== lastBar && barRef.current) {
        lastBar = bar;
        barRef.current.style.transform = `scaleX(${bar})`;
      }
    };
    const onScroll = () => {
      if (!frame) frame = requestAnimationFrame(update);
    };
    const onResize = () => {
      measure();
      onScroll();
    };
    const listen = (on: boolean) => {
      if (on === listening) return;
      listening = on;
      if (on) {
        window.addEventListener("scroll", onScroll, { passive: true });
        window.addEventListener("resize", onResize, { passive: true });
      } else {
        window.removeEventListener("scroll", onScroll);
        window.removeEventListener("resize", onResize);
      }
    };

    let io: IntersectionObserver | null = null;
    measure();
    if (typeof IntersectionObserver === "undefined") {
      listen(true);
      onScroll();
    } else {
      io = new IntersectionObserver(
        (entries) => {
          const near = entries.some((e) => e.isIntersecting);
          listen(near);
          measure();
          // One sync pass on every enter/leave, so a jump (anchor, back-to-top) that skips the
          // section entirely still lands on the right state (−1 above, last stop below).
          if (frame) cancelAnimationFrame(frame);
          update();
        },
        { rootMargin: "50% 0px 50% 0px" },
      );
      io.observe(section);
    }

    return () => {
      io?.disconnect();
      listen(false);
      if (frame) cancelAnimationFrame(frame);
    };
  }, [stepCount]);

  // Reset the globe to the intro view when the section unmounts (route change).
  useEffect(() => () => useJourney.getState().setActiveIndex(-1), []);

  return (
    <section
      ref={sectionRef}
      id="journey"
      aria-label="Journey across Kashmir, Ladakh and Jammu"
      className="theme-dark relative h-[calc(var(--journey-steps)*100svh)] bg-night"
      style={{ "--journey-steps": stepCount } as CSSProperties}
    >
      <h2 className="sr-only">Journey across Kashmir, Ladakh and Jammu</h2>

      {/* Pinned below the sticky header (in flow, height var(--header-h)). */}
      <div ref={stageRef} className="sticky top-[var(--header-h)] h-[calc(100svh-var(--header-h))] overflow-hidden">
        <GlobeStage className="absolute inset-0" stops={stops} />
        <JourneyOverlay stops={stops} />

        {/* Journey progress */}
        <div aria-hidden className="absolute inset-x-0 bottom-0 h-0.5 bg-line">
          <div ref={barRef} className="h-full origin-left bg-primary" style={{ transform: "scaleX(0)" }} />
        </div>
      </div>

      {/* Invisible scroll steps: intro + one per destination */}
      <div aria-hidden className="pointer-events-none absolute inset-x-0 top-0">
        {Array.from({ length: stepCount }, (_, k) => k - 1).map((i) => (
          <div key={i} id={stepId(i, stops[i]?.slug)} data-journey-step={i} className="h-[100svh]" />
        ))}
      </div>
    </section>
  );
}

export default GlobeJourney;
