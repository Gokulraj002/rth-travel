"use client";

import Image from "next/image";
import { useCallback, useEffect, useMemo, useRef, useState, type CSSProperties, type RefObject } from "react";
import { ArrowRight, CalendarDays, Mountain } from "lucide-react";
import type { Destination } from "@/types";
import { regionLabels } from "@/data/labels";
import { ButtonLink } from "@/components/ui/Button";
import { formatCoords, pad2 } from "@/lib/utils";
import {
  BASE_VIEWBOX,
  VB_H,
  VB_W,
  WEIGHT,
  estimateGeometry,
  measureWord,
  zoomViewBox,
  type WordGeometry,
} from "./geometry";

/** gsap.matchMedia() instance — type only; GSAP itself is loaded on demand. */
type GsapMatchMedia = ReturnType<(typeof import("gsap"))["gsap"]["matchMedia"]>;

// Oversized cover rect so the navy overlay fills any viewport aspect ratio.
const COVER = { x: -2500, y: -2500, width: 6000, height: 5600 };

/** Height of the sticky site header (CSS var `--header-h`, 64px / 76px lg), in px. */
function headerHeight() {
  const v = parseFloat(getComputedStyle(document.documentElement).getPropertyValue("--header-h"));
  return Number.isFinite(v) ? v : 0;
}

/**
 * Layout size of the overlay box (untransformed), in CSS px. This is the sticky stage
 * (viewport minus the sticky header), not the full viewport — the zoom maths uses it directly.
 */
function measureBox(svg: SVGSVGElement) {
  const box = svg.parentElement ?? svg;
  return { w: box.clientWidth, h: box.clientHeight };
}

/**
 * Becomes true once the element is within ~one viewport of the screen *and* the
 * main thread is idle. Font measurement and the GSAP timeline wait for it, so none
 * of that work competes with the initial page load (GSAP is only downloaded then).
 */
function useArmed(ref: RefObject<HTMLElement | null>) {
  const [armed, setArmed] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    let idle = 0;
    let timer = 0;

    const arm = () => {
      if (typeof window.requestIdleCallback === "function") {
        idle = window.requestIdleCallback(() => setArmed(true), { timeout: 400 });
      } else {
        timer = window.setTimeout(() => setArmed(true), 50);
      }
    };

    if (typeof IntersectionObserver === "undefined") {
      arm();
    } else {
      const io = new IntersectionObserver(
        (entries) => {
          if (!entries.some((e) => e.isIntersecting)) return;
          io.disconnect();
          arm();
        },
        { rootMargin: "100% 0px 100% 0px" },
      );
      io.observe(el);
      return () => {
        io.disconnect();
        if (idle) window.cancelIdleCallback(idle);
        if (timer) window.clearTimeout(timer);
      };
    }
    return () => {
      if (idle) window.cancelIdleCallback(idle);
      if (timer) window.clearTimeout(timer);
    };
  }, [ref]);

  return armed;
}

interface RevealPanelProps {
  destination: Destination;
  index: number;
  total: number;
}

/**
 * One "cinematic reveal" scene: a navy screen with the destination's name knocked out
 * of it (photo visible only through the letters). Scrubbing flies the camera into a
 * letter stroke until the photo fills the screen, then a glass caption rises in.
 *
 * The pin is CSS `position: sticky` inside a wrapper whose extra height (the spacer) is
 * the scroll length — so the page height is final in the server HTML and the GSAP
 * timeline can be created lazily without shifting anything. Under prefers-reduced-motion
 * it renders as a static photo section.
 */
export function RevealPanel({ destination: d, index, total }: RevealPanelProps) {
  const scope = useRef<HTMLDivElement>(null);
  const spacerRef = useRef<HTMLDivElement>(null);
  const svgRef = useRef<SVGSVGElement>(null);
  const textRef = useRef<SVGTextElement>(null);
  const est = useMemo(() => estimateGeometry(d.displayName), [d.displayName]);
  const geo = useRef<WordGeometry>(est);
  const progress = useRef(0);
  const size = useRef({ w: 0, h: 0 });
  const armed = useArmed(scope);
  const mmRef = useRef<GsapMatchMedia | null>(null);

  const maskId = `rth-reveal-mask-${d.slug}`;
  const headingId = `rth-reveal-${d.slug}`;
  const label = `${pad2(index + 1)} / ${pad2(total)} — ${regionLabels[d.region]}`;
  const bestTimeShort = d.bestTime.split("·")[0].trim();

  /**
   * Push the current zoom progress into the SVG viewBox (crisp vector zoom, no raster
   * scaling). Per scroll frame this only writes one attribute — the box size is cached
   * and re-read on ScrollTrigger refresh (resize), never during scrubbing.
   */
  const applyZoom = useCallback(() => {
    const svg = svgRef.current;
    if (!svg) return;
    if (!size.current.w) size.current = measureBox(svg);
    svg.setAttribute("viewBox", zoomViewBox(progress.current, geo.current, size.current.w, size.current.h));
  }, []);

  // Fit the word to ~83% of the viewBox with the real font (deferred until armed).
  useEffect(() => {
    if (!armed) return;
    let cancelled = false;
    const text = textRef.current;
    if (!text) return;
    // Resolves to the Playfair Display stack (next/font family + its metric-matched fallback).
    const family = getComputedStyle(text).fontFamily;
    const fontSpec = `${WEIGHT} 100px ${family}`;
    const fonts = typeof document !== "undefined" ? document.fonts : undefined;

    const fit = () => {
      if (cancelled || !textRef.current) return;
      // If Playfair is not available yet (slow network / swap period), measure with the fallback
      // now but don't cache it, and re-measure once the webfont finishes loading.
      const loaded = fonts ? fonts.check(fontSpec, d.displayName) : true;
      if (!loaded && fonts) fonts.addEventListener("loadingdone", fit, { once: true });
      const g = measureWord(d.displayName, family, loaded);
      if (!g) return;
      geo.current = g;
      const t = textRef.current;
      t.setAttribute("font-size", g.fontSize.toFixed(2));
      t.setAttribute("x", g.x.toFixed(2));
      t.setAttribute("y", g.y.toFixed(2));
      scope.current?.style.setProperty("--word-half", g.half.toFixed(2));
      applyZoom();
    };

    if (fonts?.load) {
      fonts.load(fontSpec, d.displayName).then(fit, fit);
    } else {
      fit();
    }
    return () => {
      cancelled = true;
      fonts?.removeEventListener("loadingdone", fit);
    };
  }, [armed, d.displayName, applyZoom]);

  // Scrub timeline — GSAP + ScrollTrigger are dynamically imported only once armed, so they
  // never ship in the page's initial JS. Cleanup is manual: the matchMedia instance owns every
  // tween/ScrollTrigger and is reverted on unmount (or when the import resolves too late).
  useEffect(() => {
    if (!armed) return;
    let cancelled = false;

    void (async () => {
      const [{ gsap }, { ScrollTrigger }] = await Promise.all([import("gsap"), import("gsap/ScrollTrigger")]);
      if (cancelled || !scope.current) return;
      gsap.registerPlugin(ScrollTrigger);

      const mm = gsap.matchMedia();
      mmRef.current = mm;
      mm.add(
        {
          // gsap.matchMedia only runs the setup when at least one query matches,
          // so isDesktop must be listed or nothing is created on a normal desktop.
          isDesktop: "(min-width: 768px)",
          isMobile: "(max-width: 767px)",
          reduce: "(prefers-reduced-motion: reduce)",
        },
        (ctx) => {
          const { isMobile, reduce } = ctx.conditions as { isMobile: boolean; reduce: boolean };
          if (reduce) return;

          const q = gsap.utils.selector(scope);
          const proxy = { p: 0 };

          const tl = gsap.timeline({
            defaults: { ease: "none" },
            scrollTrigger: {
              trigger: scope.current,
              // The article sticks below the sticky header, so the scene starts when the
              // wrapper's top reaches the header's bottom edge (re-read on every refresh).
              start: () => `top ${headerHeight()}px`,
              // The sticky article stays put for exactly the spacer's height
              // (+100svh on phones, +150svh from md up).
              end: () => `+=${Math.max(1, spacerRef.current?.offsetHeight ?? window.innerHeight)}`,
              scrub: isMobile ? 0.4 : 0.7,
              invalidateOnRefresh: true,
              onRefresh: () => {
                const svg = svgRef.current;
                if (svg) size.current = measureBox(svg);
                applyZoom();
              },
            },
          });

          tl.fromTo(q("[data-reveal-photo]"), { scale: 1.15 }, { scale: 1, duration: 0.82 }, 0)
            .to(q("[data-reveal-label]"), { autoAlpha: 0, y: -24, duration: 0.14 }, 0.03)
            .to(q("[data-reveal-hint]"), { autoAlpha: 0, duration: 0.08 }, 0)
            .to(
              proxy,
              {
                p: 1,
                duration: 0.62,
                ease: "power1.in",
                onUpdate: () => {
                  progress.current = proxy.p;
                  applyZoom();
                },
              },
              0.06,
            )
            // Safety net: whatever the glyph geometry, the overlay is gone by the end of the dive.
            .to(svgRef.current, { autoAlpha: 0, duration: 0.08 }, 0.6)
            .fromTo(q("[data-reveal-shade]"), { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.14 }, 0.66)
            .fromTo(
              q("[data-reveal-caption]"),
              { autoAlpha: 0, y: 48 },
              { autoAlpha: 1, y: 0, duration: 0.16, ease: "power2.out" },
              0.72,
            )
            .fromTo(
              q("[data-reveal-item]"),
              { autoAlpha: 0, y: 18 },
              { autoAlpha: 1, y: 0, duration: 0.12, stagger: 0.022, ease: "power2.out" },
              0.75,
            )
            .to({}, { duration: 0.1 }); // hold on the finished caption before it scrolls away

          return () => {
            progress.current = 0;
            svgRef.current?.setAttribute("viewBox", BASE_VIEWBOX);
          };
        },
      );
    })();

    return () => {
      cancelled = true;
      mmRef.current?.revert();
      mmRef.current = null;
    };
  }, [armed, applyZoom]);

  return (
    <div
      ref={scope}
      className="theme-dark relative"
      style={{ "--word-half": est.half.toFixed(2) } as CSSProperties}
    >
      <article
        aria-labelledby={headingId}
        className="sticky top-[var(--header-h)] isolate flex h-[calc(100svh-var(--header-h))] flex-col justify-end overflow-hidden bg-night motion-reduce:static motion-reduce:h-auto motion-reduce:min-h-[calc(100svh-var(--header-h))]"
      >
        {/* Photo (lazy — never preloaded; it is at least one screen below the fold) */}
        <div data-reveal-photo className="absolute inset-0 -z-20 will-change-transform motion-reduce:will-change-auto">
          <Image
            src={d.image}
            alt={`${d.name} — ${d.tagline}`}
            fill
            sizes="100vw"
            className="object-cover"
          />
        </div>

        {/* Bottom-left scrim for caption legibility (fades in after the dive) */}
        <div
          data-reveal-shade
          aria-hidden
          className="pointer-events-none absolute inset-0 -z-10 bg-radial-[130%_85%_at_0%_100%] from-scrim/90 via-scrim/55 via-45% to-transparent to-75%"
        >
          <div className="absolute inset-x-0 bottom-0 h-1/2 bg-linear-to-t from-scrim/90 via-scrim/40 to-transparent" />
        </div>

        {/* Knockout overlay: navy everywhere except through the letters */}
        <div aria-hidden className="pointer-events-none absolute inset-0 -z-10 [container-type:size] motion-reduce:hidden">
          <svg
            ref={svgRef}
            viewBox={BASE_VIEWBOX}
            preserveAspectRatio="xMidYMid meet"
            className="absolute inset-0 h-full w-full"
            focusable="false"
          >
            <defs>
              <mask id={maskId} maskUnits="userSpaceOnUse" {...COVER}>
                <rect {...COVER} fill="#fff" />
                <text
                  ref={textRef}
                  x={est.x}
                  y={est.y}
                  fontSize={est.fontSize.toFixed(2)}
                  textAnchor="middle"
                  fontWeight={WEIGHT}
                  fill="#000"
                  className="font-display"
                >
                  {d.displayName}
                </text>
              </mask>
            </defs>
            <rect {...COVER} fill="var(--night)" mask={`url(#${maskId})`} />
          </svg>

          {/* Mono label sits just above the word: half its ink height (user units → px via container units).
              Resolved once by layout; scrubbing never changes the container size. */}
          <p
            data-reveal-label
            className="label absolute inset-x-0 text-center text-ink/75"
            style={{
              top: `calc(50% - var(--word-half) * min(100cqw, ${(VB_W / VB_H).toFixed(4)} * 100cqh) / ${VB_W} - 2.25rem)`,
            }}
          >
            {label}
          </p>

          <div data-reveal-hint className="absolute inset-x-0 bottom-8 flex flex-col items-center gap-3">
            <span className="label">Scroll to enter</span>
            <span className="h-10 w-px bg-linear-to-b from-accent to-transparent" />
          </div>
        </div>

        {/* Static label + heading (shown as real text under reduced motion; sr-only otherwise) */}
        <div className="px-4 pb-6 sm:px-8 lg:px-12">
          <p className="label mb-3 hidden text-ink/80 motion-reduce:block">{label}</p>
          <h3
            id={headingId}
            className="sr-only font-display text-[clamp(2.25rem,7vw,5rem)] font-semibold uppercase leading-[0.95] tracking-[-0.01em] text-ink motion-reduce:not-sr-only motion-reduce:block"
          >
            {d.name}
          </h3>
        </div>

        {/* Caption — hidden until the timeline drives it in (visible as-is under reduced motion).
            Below md: solid translucent navy instead of the costly backdrop blur. */}
        <div className="px-4 pb-6 sm:px-8 sm:pb-10 lg:px-12 lg:pb-12">
          <div
            data-reveal-caption
            className="glass w-full max-w-lg rounded-2xl p-4 shadow-soft max-md:bg-night/85 max-md:[-webkit-backdrop-filter:none] max-md:[backdrop-filter:none] sm:p-6 motion-safe:invisible"
          >
            <p data-reveal-item className="label text-accent">
              {formatCoords(d.geo.lat, d.geo.lng)}
            </p>
            <p
              data-reveal-item
              className="mt-2 font-display text-[22px] font-semibold leading-[1.18] tracking-[-0.01em] text-balance text-ink sm:mt-2.5 sm:text-[25px] lg:text-[28px]"
            >
              {d.tagline}
            </p>
            <p data-reveal-item className="mt-2 line-clamp-2 text-[15px] leading-relaxed text-muted sm:mt-2.5">
              {d.description}
            </p>

            <ul data-reveal-item className="mt-3.5 flex flex-wrap gap-2 max-sm:hidden" aria-label={`${d.name} highlights`}>
              {d.highlights.slice(0, 3).map((h, i) => (
                <li
                  key={h}
                  className={`rounded-full border border-line-strong bg-white/[0.06] px-3 py-1 text-xs font-medium text-ink/90${i >= 2 ? " max-md:hidden" : ""}`}
                >
                  {h}
                </li>
              ))}
            </ul>

            <dl data-reveal-item className="mt-3 flex flex-wrap gap-2 text-xs font-semibold tabular-nums sm:mt-2.5">
              <div className="inline-flex items-center gap-1.5 rounded-full border border-accent/40 px-3 py-1 text-accent-soft">
                <Mountain className="size-3.5 shrink-0 text-accent" aria-hidden />
                <dt className="sr-only">Altitude</dt>
                <dd>{d.altitudeM.toLocaleString("en-IN")} m</dd>
              </div>
              <div className="inline-flex items-center gap-1.5 rounded-full border border-leaf/40 px-3 py-1 text-accent-soft">
                <CalendarDays className="size-3.5 shrink-0 text-leaf" aria-hidden />
                <dt className="sr-only">Best time</dt>
                <dd>{bestTimeShort}</dd>
              </div>
            </dl>

            <div data-reveal-item className="mt-4">
              {/* h-11 = 44px tap target; bg-primary text-white */}
              <ButtonLink href={`/destinations/${d.slug}`} variant="primary" size="md">
                Discover {d.name}
                <ArrowRight className="size-4" aria-hidden />
              </ButtonLink>
            </div>
          </div>
        </div>
      </article>

      {/* Scroll length of the sticky scene: ≈ +100% of the viewport on phones, +150% from md up. */}
      <div ref={spacerRef} aria-hidden className="h-svh md:h-[150svh] motion-reduce:hidden" />
    </div>
  );
}
