"use client";

import { useEffect, useRef } from "react";
import { srinagarAirport } from "@/data/origins";
import { distanceKm } from "@/lib/geo";
import { useJourney } from "@/lib/store";
import { formatKm } from "./shared";
import { OriginSelect } from "./OriginSelect";

/**
 * Looping "scroll" tick: one WAAPI transform animation (compositor-only, no motion library).
 * Hidden and not started under reduced motion.
 */
function ScrollCue() {
  const ref = useRef<HTMLSpanElement>(null);
  useEffect(() => {
    const el = ref.current;
    if (!el || typeof el.animate !== "function") return;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    const anim = el.animate([{ transform: "translateY(-100%)" }, { transform: "translateY(200%)" }], {
      duration: 1800,
      iterations: Infinity,
      easing: "ease-in-out",
    });
    return () => anim.cancel();
  }, []);
  return <span ref={ref} className="absolute inset-x-0 top-0 h-1/2 bg-accent [transform:translateY(-100%)] motion-reduce:hidden" />;
}

/**
 * Intro caption (activeIndex −1): origin → SXR, distance and a scroll cue.
 * Enter is a CSS transition from `@starting-style` (opacity + transform); no exit animation.
 */
export function JourneyIntro() {
  const origin = useJourney((s) => s.origin);
  const km = formatKm(distanceKm(origin.geo, srinagarAirport));

  return (
    <div className="pointer-events-none absolute inset-x-0 bottom-[7svh] flex flex-col items-center px-4 text-center transition-[opacity,transform] duration-700 ease-out-expo starting:opacity-0 starting:[transform:translateY(1.5rem)] lg:bottom-[9svh]">
      <p className="label text-accent">00 — The journey</p>
      <p className="mt-3 max-w-[18ch] font-display text-[clamp(2rem,5.5vw,4rem)] font-semibold leading-[1.05] tracking-[-0.01em] text-balance [text-shadow:0_4px_40px_rgb(2_11_26/0.9)]">
        From {origin.city} <span className="text-accent">to the Valley</span>
      </p>
      <p className="mt-4 text-[13px] font-medium tabular-nums tracking-[0.14em] text-muted sm:text-sm">
        {origin.code} <span className="text-accent">→</span> {srinagarAirport.code}
        <span className="text-faint"> · </span>
        {km} km
      </p>

      <OriginSelect className="mt-5 lg:hidden" />

      <div className="mt-6 flex flex-col items-center gap-2">
        <span className="label">Scroll to fly in</span>
        <span aria-hidden className="relative block h-9 w-px overflow-hidden bg-white/15">
          <ScrollCue />
        </span>
      </div>
    </div>
  );
}
