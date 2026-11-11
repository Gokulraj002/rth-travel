/**
 * Hero CSS — the deep-space scene, the planet limb, the orbit and every entrance.
 *
 * Rules: animate transform/opacity only (nothing repaints per frame); entrances ≤ 700 ms with
 * `both` fill, staggered via inline `animation-delay`. The h1 parts (`hero-rise`, `hero-earth-in`)
 * are transform-only, so the LCP text is painted immediately. Ambient loops (star drift, glow
 * breathing, orbit) are slow and are switched off under reduced motion.
 *
 * Rendered as a React 19 hoisted <style> (deduped by `href`), so it lands in <head> of the
 * server HTML — no extra request, and no edits to the shared globals.css. `earth-spin-x` lives
 * here (not globals) because `animate-earth` / `earth-spin` is still used, as a
 * background-position slide, by app/not-found.tsx and app/error.tsx.
 */
const css = `
/* ── Entrances ─────────────────────────────────────────────────────────── */
.hero-fade-up {
  animation: hero-fade-up 600ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes hero-fade-up {
  from { opacity: 0; transform: translate3d(0, var(--rise, 16px), 0); }
}

.hero-rise {
  animation: hero-rise 650ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes hero-rise {
  from { transform: translate3d(0, var(--rise, 0.3em), 0); }
}

.hero-earth-in {
  animation: hero-earth-in 700ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes hero-earth-in {
  from { transform: scale(0.55) rotate(-30deg); }
}

.hero-bloom {
  opacity: 0.75;
  animation: hero-bloom 700ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes hero-bloom {
  0% { opacity: 0; transform: scale(0.4); }
  45% { opacity: 1; transform: scale(1.12); }
  100% { opacity: 0.75; transform: scale(1); }
}

.hero-orbit-in {
  animation: hero-orbit-in 700ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes hero-orbit-in {
  from { opacity: 0; transform: scale(0.92); }
}

.hero-planet-in {
  animation: hero-planet-in 700ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes hero-planet-in {
  from { opacity: 0; transform: translate3d(0, 48px, 0); }
}

/* ── Spinning earth "O": a 2-tile texture strip slid by exactly one tile ─ */
.hero-earth-strip {
  will-change: transform;
  animation: earth-spin-x 30s linear infinite;
}
@keyframes earth-spin-x {
  from { transform: translate3d(-50%, 0, 0); }
  to { transform: translate3d(0, 0, 0); }
}

/* ── Starfield: three tiled radial-gradient layers (no canvas, no images) ─ */
.hero-stars-fine {
  position: absolute;
  inset: 0;
  background-image:
    radial-gradient(1px 1px at 8% 14%, rgb(255 255 255 / 0.55), transparent 60%),
    radial-gradient(1px 1px at 27% 61%, rgb(255 255 255 / 0.4), transparent 60%),
    radial-gradient(1px 1px at 43% 27%, rgb(200 225 255 / 0.5), transparent 60%),
    radial-gradient(1px 1px at 58% 83%, rgb(255 255 255 / 0.35), transparent 60%),
    radial-gradient(1px 1px at 71% 9%, rgb(255 255 255 / 0.45), transparent 60%),
    radial-gradient(1px 1px at 86% 49%, rgb(200 225 255 / 0.4), transparent 60%),
    radial-gradient(1px 1px at 93% 76%, rgb(255 255 255 / 0.3), transparent 60%),
    radial-gradient(1px 1px at 16% 92%, rgb(255 255 255 / 0.35), transparent 60%);
  background-size: 240px 240px;
  opacity: 0.8;
}
/* Mid layer drifts down by exactly one tile, so the loop is seamless. */
.hero-stars-drift {
  position: absolute;
  inset: -420px 0 0 0;
  background-image:
    radial-gradient(1.5px 1.5px at 12% 22%, rgb(255 255 255 / 0.7), transparent 60%),
    radial-gradient(1.25px 1.25px at 36% 74%, rgb(184 221 255 / 0.6), transparent 60%),
    radial-gradient(1.5px 1.5px at 63% 41%, rgb(255 255 255 / 0.55), transparent 60%),
    radial-gradient(1.25px 1.25px at 81% 88%, rgb(255 255 255 / 0.5), transparent 60%),
    radial-gradient(1.5px 1.5px at 92% 13%, rgb(184 221 255 / 0.65), transparent 60%);
  background-size: 420px 420px;
  will-change: transform;
  animation: hero-drift 220s linear infinite;
}
@keyframes hero-drift {
  to { transform: translate3d(0, 420px, 0); }
}
/* A few brighter stars that breathe. */
.hero-stars-bright {
  position: absolute;
  inset: 0;
  background-image:
    radial-gradient(2px 2px at 18% 34%, rgb(255 255 255 / 0.9), transparent 60%),
    radial-gradient(1.75px 1.75px at 52% 12%, rgb(220 238 255 / 0.85), transparent 60%),
    radial-gradient(2px 2px at 77% 58%, rgb(255 255 255 / 0.8), transparent 60%),
    radial-gradient(1.75px 1.75px at 34% 86%, rgb(220 238 255 / 0.75), transparent 60%);
  background-size: 640px 560px;
  opacity: 0.85;
  animation: hero-twinkle 5.5s ease-in-out infinite alternate;
}
@keyframes hero-twinkle {
  from { opacity: 0.4; }
  to { opacity: 0.95; }
}

/* ── Planet limb rising from the bottom edge ──────────────────────────── */
/* --hero-cap (visible height of the limb) is set on the section. */
.hero-planet {
  position: absolute;
  left: 50%;
  top: calc(100% - var(--hero-cap, 96px));
  width: clamp(860px, 175vw, 2600px);
  aspect-ratio: 1;
  margin-left: calc(clamp(860px, 175vw, 2600px) / -2);
}
/* Soft atmosphere outside the limb (a ring-shaped radial gradient), slowly breathing. */
.hero-planet-glow {
  position: absolute;
  inset: -9%;
  border-radius: 50%;
  background: radial-gradient(
    closest-side,
    transparent 83%,
    rgb(79 141 255 / 0.3) 85.6%,
    rgb(79 141 255 / 0.12) 89%,
    rgb(79 141 255 / 0.04) 94%,
    transparent 100%
  );
  opacity: 0.9;
  will-change: opacity;
  animation: hero-breathe 7s ease-in-out infinite alternate;
}
@keyframes hero-breathe {
  from { opacity: 0.65; }
  to { opacity: 1; }
}
/* Broad haze rising above the centre of the horizon. */
.hero-planet-haze {
  position: absolute;
  left: 50%;
  top: 0;
  width: min(1100px, 120vw);
  height: min(420px, 60vw);
  transform: translate(-50%, -100%);
  background: radial-gradient(50% 100% at 50% 100%, rgb(79 141 255 / 0.16), rgb(0 84 144 / 0.06) 55%, transparent 100%);
}
/* Night side: faintly lit just inside the limb, deep navy below; inner atmospheric scatter. */
.hero-planet-body {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: radial-gradient(70% 22% at 50% 0%, #0c2856 0%, #061738 45%, #020b1a 100%);
  box-shadow:
    inset 0 22px 48px -18px rgb(79 141 255 / 0.4),
    inset 0 2px 6px -2px rgb(124 192 255 / 0.45);
}
/* Crisp hairline rim: brightest at the top centre, accent → atmosphere towards the sides. */
.hero-planet-rim {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: radial-gradient(42% 18% at 50% 0%, #eaf5ff 0%, #7cc0ff 30%, #4f8dff 70%, rgb(79 141 255 / 0.25) 100%);
  -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 1.75px), #000 calc(100% - 1px));
  mask: radial-gradient(farthest-side, transparent calc(100% - 1.75px), #000 calc(100% - 1px));
}
/* Sunrise glint at the top of the limb: a thin warm-white streak + a soft bloom. */
.hero-glint {
  position: absolute;
  left: 50%;
  top: 0;
  transform: translate(-50%, -50%);
  pointer-events: none;
  animation: hero-glint 7s ease-in-out infinite alternate;
}
.hero-glint-streak {
  width: min(78vw, 760px);
  height: 3px;
  background: radial-gradient(closest-side, #fffaf2 0%, rgb(220 238 255 / 0.75) 30%, rgb(124 192 255 / 0.25) 65%, transparent 100%);
}
.hero-glint-bloom {
  width: min(56vw, 300px);
  height: 90px;
  background: radial-gradient(closest-side, rgb(255 246 232 / 0.4) 0%, rgb(184 221 255 / 0.16) 50%, transparent 100%);
}
@keyframes hero-glint {
  from { opacity: 0.7; }
  to { opacity: 1; }
}

/* ── Orbit ring around the title ──────────────────────────────────────── */
/* The comet's ellipse is two perpendicular sine-like slides (a Lissajous figure): x and y are
   eased back and forth with a quarter-period offset. Transform-only, sized in em, so it scales
   with the title. A third layer dims/shrinks the comet on the far (upper) arc for depth. */
.hero-orbit-x {
  position: absolute;
  left: 0;
  top: 0;
  animation: hero-orbit-x 9s cubic-bezier(0.37, 0, 0.63, 1) infinite alternate;
}
@keyframes hero-orbit-x {
  from { transform: translate3d(calc(var(--orbit-a) * -1), 0, 0); }
  to { transform: translate3d(var(--orbit-a), 0, 0); }
}
.hero-orbit-y {
  animation: hero-orbit-y 9s cubic-bezier(0.37, 0, 0.63, 1) -4.5s infinite alternate;
}
@keyframes hero-orbit-y {
  from { transform: translate3d(0, calc(var(--orbit-b) * -1), 0); }
  to { transform: translate3d(0, var(--orbit-b), 0); }
}
.hero-orbit-depth {
  animation: hero-orbit-depth 18s ease-in-out infinite;
}
@keyframes hero-orbit-depth {
  0%, 100% { opacity: 0.55; transform: scale(0.85); }
  25% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.55; transform: scale(0.85); }
  75% { opacity: 0.12; transform: scale(0.6); }
}

/* ── Small motion details ─────────────────────────────────────────────── */
.hero-bob {
  animation: hero-bob 1.6s ease-in-out infinite alternate;
}
@keyframes hero-bob {
  from { transform: translate3d(0, -1px, 0); }
  to { transform: translate3d(0, 3px, 0); }
}

@media (prefers-reduced-motion: reduce) {
  .hero-fade-up,
  .hero-rise,
  .hero-earth-in,
  .hero-bloom,
  .hero-orbit-in,
  .hero-planet-in,
  .hero-earth-strip,
  .hero-stars-drift,
  .hero-stars-bright,
  .hero-planet-glow,
  .hero-glint,
  .hero-bob {
    animation: none !important;
  }
  .hero-earth-strip,
  .hero-stars-drift,
  .hero-planet-glow { will-change: auto; }
  .hero-earth-strip { transform: translate3d(-25%, 0, 0); }
  /* A frozen comet would sit at the ring's centre (behind the word) — hide it. */
  .hero-comet { display: none; }
}
`;

export function HeroStyles() {
  return (
    <style href="rth-hero-motion" precedence="default">
      {css}
    </style>
  );
}
