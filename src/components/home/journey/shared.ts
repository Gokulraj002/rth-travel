/** Motion easing used across the journey overlay. */
export const EASE = [0.16, 1, 0.3, 1] as const;

/** DOM id of the invisible scroll step for an index (-1 = intro); `slug` is that stop's destination. */
export function stepId(i: number, slug?: string) {
  return i < 0 ? "journey-intro" : `journey-${slug ?? i}`;
}

/** Smooth-scroll the page so the given step's trigger sits at the top of the viewport. */
export function scrollToStep(i: number) {
  const el = document.querySelector<HTMLElement>(`[data-journey-step="${i}"]`);
  if (!el) return;
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  el.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
}

const nf = new Intl.NumberFormat("en-IN");

/** 1585 → "1,585 m" */
export function formatAltitude(m: number) {
  return `${nf.format(m)} m`;
}

/** 1234.6 → "1,235" */
export function formatKm(km: number) {
  return nf.format(Math.round(km));
}
