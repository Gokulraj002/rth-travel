"use client";

import { useCallback, useEffect, useRef, useState, useSyncExternalStore } from "react";
import dynamic from "next/dynamic";
import { cn } from "@/lib/utils";
import StaticGlobe from "./StaticGlobe";
import type { GlobeTier } from "./globeShared";

/**
 * Cheap CSS stand-in (no image, no JS) sized like the WebGL intro globe: a dark sphere with a
 * blue atmospheric rim. Shown until the canvas has compiled and faded in, so the section never
 * looks empty when someone scrolls fast.
 */
function SceneFallback({ hide }: { hide: boolean }) {
  return (
    <div
      // Size container: the sphere scales with the stage height (viewport minus sticky header),
      // like the WebGL globe does.
      className="absolute inset-0 flex items-center justify-center transition-opacity duration-[1400ms] ease-out-expo [container-type:size]"
      style={{ opacity: hide ? 0 : 1 }}
      aria-hidden
    >
      <div
        className="aspect-square w-[min(82vw,82cqh)] rounded-full"
        style={{
          background:
            "radial-gradient(circle at 36% 34%, rgb(36 132 214 / 0.32) 0%, rgb(11 28 58 / 0.9) 46%, rgb(2 11 26 / 0.96) 74%)",
          boxShadow:
            "0 0 60px 6px rgb(79 141 255 / 0.28), 0 0 160px 30px rgb(79 141 255 / 0.10), inset 0 0 40px 4px rgb(79 141 255 / 0.35)",
        }}
      />
    </div>
  );
}

const loadScene = () => import("./GlobeScene");
// The fallback is rendered by GlobeStage itself (it must outlive the chunk load until the
// canvas is ready), so next/dynamic shows nothing of its own.
const GlobeScene = dynamic(loadScene, { ssr: false, loading: () => null });

type Tier = GlobeTier | "static";

let webglSupport: boolean | null = null;
function hasWebGL() {
  if (webglSupport !== null) return webglSupport;
  try {
    const c = document.createElement("canvas");
    const ctx = (c.getContext("webgl2") ?? c.getContext("webgl")) as WebGLRenderingContext | null;
    webglSupport = !!ctx;
    ctx?.getExtension("WEBGL_lose_context")?.loseContext();
  } catch {
    webglSupport = false;
  }
  return webglSupport;
}

const REDUCED_MOTION = "(prefers-reduced-motion: reduce)";
const SMALL = "(max-width: 768px)";

function getTier(): Tier {
  if (window.matchMedia(REDUCED_MOTION).matches || !hasWebGL()) return "static";
  const cores = navigator.hardwareConcurrency ?? 4;
  if (window.matchMedia(SMALL).matches || cores <= 4) return "reduced";
  return "full";
}

function subscribeTier(cb: () => void) {
  const queries = [window.matchMedia(REDUCED_MOTION), window.matchMedia(SMALL)];
  queries.forEach((q) => q.addEventListener("change", cb));
  return () => queries.forEach((q) => q.removeEventListener("change", cb));
}

/** null on the server / first hydration pass, then the device tier. */
function useTier(): Tier | null {
  return useSyncExternalStore<Tier | null>(subscribeTier, getTier, () => null);
}

/** true while the tab is in the background — rendering is paused. */
function subscribeVisibility(cb: () => void) {
  document.addEventListener("visibilitychange", cb);
  return () => document.removeEventListener("visibilitychange", cb);
}
function usePageHidden() {
  return useSyncExternalStore(subscribeVisibility, () => document.hidden, () => false);
}

/**
 * Sticky "the user has scrolled at least once". The journey section starts exactly one
 * viewport down (below a 100svh hero), so a one-viewport rootMargin already intersects at
 * page load — without this gate the globe would still compile inside the load window.
 * Restored scroll positions / #journey links count as scrolled immediately.
 */
let hasScrolled = false;
function subscribeScrolled(cb: () => void) {
  if (hasScrolled) return () => {};
  const onScroll = () => {
    hasScrolled = true;
    window.removeEventListener("scroll", onScroll);
    cb();
  };
  window.addEventListener("scroll", onScroll, { passive: true });
  return () => window.removeEventListener("scroll", onScroll);
}
function getScrolled() {
  if (!hasScrolled && window.scrollY > 0) hasScrolled = true;
  return hasScrolled;
}
function useHasScrolled() {
  return useSyncExternalStore(subscribeScrolled, getScrolled, () => false);
}

/** requestIdleCallback with a setTimeout fallback (Safari). Returns a cancel function. */
function onIdle(cb: () => void, timeout = 1000) {
  if (typeof window.requestIdleCallback === "function") {
    const id = window.requestIdleCallback(cb, { timeout });
    return () => window.cancelIdleCallback(id);
  }
  const id = window.setTimeout(cb, 200);
  return () => window.clearTimeout(id);
}

/**
 * Full-bleed 3D globe stage. Fills its (positioned, e.g. sticky h-[100svh]) parent and never
 * captures pointer events. Picks a tier at mount — static (reduced motion / no WebGL),
 * reduced (≤768px or ≤4 cores) or full.
 *
 * Loading is deferred: three.js is neither imported nor mounted until the stage is within one
 * viewport of the screen AND the user has scrolled, and then only on an idle callback so it
 * never competes with first paint / hydration. Desktop ("full") additionally prefetches the
 * chunk on idle after window load. Rendering pauses while offscreen or the tab is hidden.
 */
export default function GlobeStage({ className }: { className?: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const tier = useTier();
  const webgl = tier === "full" || tier === "reduced";
  const scrolled = useHasScrolled();
  const hidden = usePageHidden();
  const [near, setNear] = useState(false);
  const [visible, setVisible] = useState(false);
  const [load, setLoad] = useState(false);
  const [sceneReady, setSceneReady] = useState(false);
  const handleReady = useCallback(() => setSceneReady(true), []);

  // `near` (sticky): within ~one viewport → may start loading. `visible`: drives the frameloop.
  useEffect(() => {
    const el = ref.current;
    if (!el || !webgl) return;
    const nearIo = new IntersectionObserver(
      ([entry]) => {
        if (!entry.isIntersecting) return;
        setNear(true);
        nearIo.disconnect();
      },
      { rootMargin: "100% 0px" },
    );
    const visibleIo = new IntersectionObserver(([entry]) => setVisible(entry.isIntersecting), {
      rootMargin: "120px 0px",
    });
    nearIo.observe(el);
    visibleIo.observe(el);
    return () => {
      nearIo.disconnect();
      visibleIo.disconnect();
    };
  }, [webgl]);

  // Start the dynamic import + mount on idle once near and the user is actually scrolling.
  useEffect(() => {
    if (load || !webgl || !near || !scrolled) return;
    return onIdle(() => setLoad(true));
  }, [load, webgl, near, scrolled]);

  // Desktop only: warm the chunk after the page has fully loaded (never before).
  useEffect(() => {
    if (tier !== "full") return;
    let cancelIdle: (() => void) | undefined;
    const start = () => {
      cancelIdle = onIdle(() => void loadScene(), 4000);
    };
    if (document.readyState === "complete") start();
    else window.addEventListener("load", start, { once: true });
    return () => {
      window.removeEventListener("load", start);
      cancelIdle?.();
    };
  }, [tier]);

  return (
    <div ref={ref} className={cn("pointer-events-none absolute inset-0 isolate h-full w-full", className)} aria-hidden>
      {tier === "static" ? (
        <StaticGlobe />
      ) : (
        <>
          <SceneFallback hide={sceneReady} />
          {webgl && load ? <GlobeScene tier={tier} active={visible && !hidden} onReady={handleReady} /> : null}
        </>
      )}
    </div>
  );
}
