"use client";

import { useEffect, useRef, useSyncExternalStore } from "react";
import { usePathname } from "next/navigation";
import type Lenis from "lenis";
import type { gsap as GsapCore } from "gsap";
import type { ScrollTrigger as ScrollTriggerClass } from "gsap/ScrollTrigger";
import { ArrowUp } from "lucide-react";
import { cn } from "@/lib/utils";

declare global {
  interface Window {
    /**
     * The site-wide Lenis instance. Only exists on desktop fine-pointer devices without
     * reduced motion (phones/tablets keep native momentum scrolling). Exposed for debugging.
     */
    __lenis?: Lenis;
    /** Pushed by gsap core when it initialises — tells us GSAP was loaded by some chunk. */
    gsapVersions?: string[];
  }
}

/**
 * ScrollTrigger, once loaded. GSAP is never in this module's static graph: the desktop Lenis
 * setup imports it on idle, and phones never download it from here.
 */
let scrollTrigger: typeof ScrollTriggerClass | null = null;

/** Refresh ScrollTrigger only if GSAP is already on the page (never triggers a download). */
function refreshScrollTrigger() {
  if (scrollTrigger) {
    scrollTrigger.refresh();
    return;
  }
  // Loaded by another chunk (reveal panels, globe) → the import resolves from the module cache.
  if (!window.gsapVersions?.length) return;
  void import("gsap/ScrollTrigger").then(({ ScrollTrigger }) => {
    scrollTrigger = ScrollTrigger;
    ScrollTrigger.refresh();
  });
}

/** Space reserved for the fixed navbar when scrolling to an in-page anchor. */
export const NAV_OFFSET = 88;

const REDUCED = "(prefers-reduced-motion: reduce)";
/** Any match → no Lenis: reduced motion, touch / coarse pointer, or narrow (tablet/phone) layouts. */
const NATIVE_SCROLL = `${REDUCED}, (pointer: coarse), (max-width: 1023px)`;

type IdleHandle = { cancel: () => void };
/** requestIdleCallback with a timeout, falling back to setTimeout (Safari). */
function onIdle(cb: () => void, timeout = 1500): IdleHandle {
  if (typeof window.requestIdleCallback === "function") {
    const id = window.requestIdleCallback(cb, { timeout });
    return { cancel: () => window.cancelIdleCallback(id) };
  }
  const id = window.setTimeout(cb, 200);
  return { cancel: () => window.clearTimeout(id) };
}

/** Scroll to a y position or element, via Lenis when active, natively otherwise. */
export function scrollToTarget(target: number | HTMLElement, opts: { immediate?: boolean } = {}) {
  const lenis = window.__lenis;
  if (lenis) {
    lenis.scrollTo(target, { offset: typeof target === "number" ? 0 : -NAV_OFFSET, immediate: opts.immediate });
    return;
  }
  const top =
    typeof target === "number" ? target : target.getBoundingClientRect().top + window.scrollY - NAV_OFFSET;
  const reduce = window.matchMedia(REDUCED).matches;
  window.scrollTo({ top, behavior: opts.immediate || reduce ? "auto" : "smooth" });
}

function findHashTarget(hash: string) {
  if (!hash || hash === "#") return null;
  try {
    return document.getElementById(decodeURIComponent(hash.slice(1)));
  } catch {
    return null;
  }
}

/**
 * Site-wide Lenis smooth scroll, synced to GSAP ScrollTrigger via gsap.ticker — desktop only.
 * Lenis, gsap and ScrollTrigger are all dynamically imported together (never on phones).
 * Not created under reduced motion, on touch / coarse-pointer devices or below 1024px: native
 * (momentum) scrolling is smoother and cheaper there, and ScrollTrigger listens to it directly.
 * On desktop the modules are loaded and Lenis created in an idle callback after hydration.
 * Also handles same-page anchor links (offset for the navbar) and resets scroll on route change —
 * both work with or without Lenis via `scrollToTarget`.
 */
export function SmoothScroll() {
  const pathname = usePathname();

  // Lenis lifecycle (re-evaluated when the pointer / width / reduced-motion conditions change).
  useEffect(() => {
    const mq = window.matchMedia(NATIVE_SCROLL);
    let lenis: Lenis | null = null;
    let gsap: typeof GsapCore | null = null;
    let idle: IdleHandle | null = null;
    let disposed = false;
    const tick = (time: number) => lenis?.raf(time * 1000);

    const create = async () => {
      idle = null;
      const [{ default: LenisCtor }, gsapMod, { ScrollTrigger }] = await Promise.all([
        import("lenis"),
        import("gsap"),
        import("gsap/ScrollTrigger"),
      ]);
      if (disposed || lenis || mq.matches) return;
      gsap = gsapMod.gsap;
      gsap.registerPlugin(ScrollTrigger);
      scrollTrigger = ScrollTrigger;
      lenis = new LenisCtor({ duration: 1.1, smoothWheel: true });
      window.__lenis = lenis;
      lenis.on("scroll", ScrollTrigger.update);
      gsap.ticker.add(tick);
      gsap.ticker.lagSmoothing(0);
    };
    const setup = () => {
      if (lenis || idle || mq.matches) return;
      idle = onIdle(() => void create());
    };
    const teardown = () => {
      idle?.cancel();
      idle = null;
      if (!lenis) return;
      gsap?.ticker.remove(tick);
      gsap?.ticker.lagSmoothing(500, 33); // GSAP defaults
      lenis.destroy();
      lenis = null;
      delete window.__lenis;
    };
    const onChange = () => {
      if (mq.matches) teardown();
      else setup();
    };

    setup();
    mq.addEventListener("change", onChange);
    return () => {
      disposed = true;
      mq.removeEventListener("change", onChange);
      teardown();
    };
  }, []);

  // Same-page anchor links (#journey, /#packages when on "/") → smooth scroll with navbar offset.
  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      const anchor = (e.target as Element | null)?.closest?.("a[href]");
      if (!(anchor instanceof HTMLAnchorElement)) return;
      if (anchor.target && anchor.target !== "_self") return;
      if (anchor.hasAttribute("download")) return;
      const raw = anchor.getAttribute("href") ?? "";
      if (!raw.includes("#")) return;
      const url = new URL(anchor.href, window.location.href);
      if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) return;
      if (url.search !== window.location.search) return;
      const el = findHashTarget(url.hash);
      if (!el) return;
      e.preventDefault(); // Next <Link> skips its own navigation when defaultPrevented.
      scrollToTarget(el);
      // Move focus to the target too (skip link, keyboard users) without a second jump.
      if (!el.hasAttribute("tabindex")) el.setAttribute("tabindex", "-1");
      el.focus({ preventScroll: true });
      if (url.hash !== window.location.hash) window.history.replaceState(window.history.state, "", url.hash);
    };
    document.addEventListener("click", onClick);
    return () => document.removeEventListener("click", onClick);
  }, []);

  // On route change: jump to top (or to the URL hash target), then refresh triggers for the new page
  // (only when ScrollTrigger is already loaded).
  // The very first run (hard load / reload) keeps the browser's restored position unless there is a hash.
  const firstRun = useRef(true);
  useEffect(() => {
    const isFirst = firstRun.current;
    firstRun.current = false;
    let raf2 = 0;
    const raf1 = requestAnimationFrame(() => {
      const el = findHashTarget(window.location.hash);
      if (el) scrollToTarget(el, { immediate: true });
      else if (!isFirst) scrollToTarget(0, { immediate: true });
      raf2 = requestAnimationFrame(refreshScrollTrigger);
    });
    return () => {
      cancelAnimationFrame(raf1);
      cancelAnimationFrame(raf2);
    };
  }, [pathname]);

  return null;
}

/* ------------------------------------------------------------------ */
/* Small client islands used by the (server) Footer.                    */
/* ------------------------------------------------------------------ */

const noopSubscribe = () => () => {};

/** Current year, rendered only on the client (server/prerender output is empty → no Date in RSC). */
export function CurrentYear() {
  const year = useSyncExternalStore(
    noopSubscribe,
    () => new Date().getFullYear(),
    () => null,
  );
  return <span suppressHydrationWarning>{year ?? ""}</span>;
}

/** Back-to-top button (Lenis-aware; native smooth scroll on touch devices). */
export function BackToTop({ className }: { className?: string }) {
  return (
    <button
      type="button"
      onClick={() => scrollToTarget(0)}
      className={cn(
        "group inline-flex min-h-11 items-center gap-2 rounded-full border border-line-strong px-5 py-2 font-mono text-[11px] uppercase tracking-[0.18em] text-muted transition-colors duration-300 hover:border-accent hover:text-accent",
        className,
      )}
    >
      Back to top
      <ArrowUp aria-hidden="true" className="size-3.5 transition-transform duration-300 group-hover:-translate-y-0.5" />
    </button>
  );
}
