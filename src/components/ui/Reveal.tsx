"use client";

import type { CSSProperties, ReactNode } from "react";

/*
 * Fade-and-rise on scroll into view — zero animation-library cost.
 *
 * - Server HTML is always fully visible (no JS-gated opacity). The hidden state is
 *   applied only after mount, and only to elements that are still BELOW the viewport,
 *   so nothing on screen ever blinks out and LCP is never delayed.
 * - One IntersectionObserver (module-level) serves every <Reveal> on the page.
 * - The observer flips `data-rth-reveal` hidden → shown; CSS does the transition
 *   (opacity + transform only), then the attribute is removed so it leaves no residue.
 * - prefers-reduced-motion: never hidden, never animated.
 */

const CSS = `
[data-rth-reveal="hidden"]{opacity:0;transform:translate3d(0,var(--rth-reveal-y,28px),0)}
[data-rth-reveal="shown"]{transition:opacity .9s cubic-bezier(.16,1,.3,1) var(--rth-reveal-delay,0s),transform .9s cubic-bezier(.16,1,.3,1) var(--rth-reveal-delay,0s)}
@media (prefers-reduced-motion:reduce){[data-rth-reveal]{opacity:1!important;transform:none!important;transition:none!important}}
`;

const ATTR = "data-rth-reveal";

let observer: IntersectionObserver | null = null;
let reducedMotion: MediaQueryList | null = null;
/** Elements whose first (initial-position) observer callback has been handled. */
const classified = new WeakSet<Element>();

function finish(el: HTMLElement) {
  const done = (e: TransitionEvent) => {
    if (e.target !== el || e.propertyName !== "opacity") return;
    el.removeEventListener("transitionend", done);
    el.removeAttribute(ATTR);
  };
  el.addEventListener("transitionend", done);
  el.setAttribute(ATTR, "shown");
}

function onEntries(entries: IntersectionObserverEntry[]) {
  const vh = window.innerHeight;
  for (const entry of entries) {
    const el = entry.target as HTMLElement;

    if (!classified.has(el)) {
      classified.add(el);
      const { top, bottom } = entry.boundingClientRect;
      // Already on screen (or scrolled past): leave it exactly as server-rendered.
      if (bottom <= 0 || top < vh) {
        observer?.unobserve(el);
        continue;
      }
      // Below the fold: hide now (off-screen, so invisible to the user) and wait.
      el.setAttribute(ATTR, "hidden");
      continue;
    }

    if (entry.isIntersecting) {
      observer?.unobserve(el);
      if (el.getAttribute(ATTR) === "hidden") finish(el);
    }
  }
}

/** Ref callback (stable, module-level). Returns a cleanup — React 19 ref semantics. */
function track(el: HTMLElement | null) {
  if (!el || typeof IntersectionObserver === "undefined") return;
  reducedMotion ??= window.matchMedia("(prefers-reduced-motion: reduce)");
  if (reducedMotion.matches) return;
  observer ??= new IntersectionObserver(onEntries, { rootMargin: "0px 0px -10% 0px" });
  const io = observer;
  io.observe(el);
  return () => {
    io.unobserve(el);
    classified.delete(el);
    if (el.getAttribute(ATTR) === "hidden") el.removeAttribute(ATTR);
  };
}

export function Reveal({
  children,
  delay = 0,
  y = 28,
  className,
  as = "div",
}: {
  children: ReactNode;
  /** Seconds. */
  delay?: number;
  /** Pixels of rise. */
  y?: number;
  className?: string;
  as?: "div" | "li" | "section" | "article";
}) {
  const Tag = as;
  const style = {
    "--rth-reveal-y": `${y}px`,
    ...(delay ? { "--rth-reveal-delay": `${delay}s` } : null),
  } as CSSProperties;

  return (
    <>
      <style href="rth-reveal" precedence="default">
        {CSS}
      </style>
      <Tag ref={track} className={className} style={style}>
        {children}
      </Tag>
    </>
  );
}
