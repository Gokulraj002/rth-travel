"use client";

import { useSyncExternalStore } from "react";

const QUERY = "(min-width: 1024px)";

// One shared MediaQueryList — getSnapshot runs on every render, so don't re-create it each time.
let mql: MediaQueryList | null = null;
const getMql = () => (mql ??= window.matchMedia(QUERY));

function subscribe(onChange: () => void) {
  const mq = getMql();
  mq.addEventListener("change", onChange);
  return () => mq.removeEventListener("change", onChange);
}

/**
 * True at Tailwind's `lg` breakpoint and up. Server + hydration snapshot is `false` (mobile-first),
 * so there is never a hydration mismatch. Only behaviour (hover vs tap, ARIA wiring) should depend on
 * this — visual active states use `lg:` / `max-lg:` classes so nothing jumps when it flips after hydration.
 */
export function useIsDesktop() {
  return useSyncExternalStore(
    subscribe,
    () => getMql().matches,
    () => false,
  );
}
