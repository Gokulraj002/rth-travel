# Phase 2 brief — logo colours · speed · mobile

Read `docs/ARCHITECTURE.md` first (stack, Next 16 rules, folder structure). This phase changes three things.

## 1. Colours now come from the RTH logo (already applied globally)

Logo: navy `#003060`, blue `#005490`, light blue `#84a8cc`, leaf green `#3ca830`. Gold is gone.

| Token (Tailwind) | Value | Use |
|---|---|---|
| `night` / `night-2` | `#020b1a` / `#061430` | page / raised section backgrounds (logo navy, deepened) |
| `surface` / `surface-2` | `#0b1c3a` / `#11264c` | cards / inputs |
| `line` / `line-strong` | logo light-blue hairlines | borders |
| `ink` / `muted` / `faint` | text tiers | |
| `brand` / `brand-deep` / `sky` | exact logo blue / navy / light blue | logo-exact accents, gradients |
| **`primary`** / `primary-bright` | `#0f6cbd` / `#2484d6` | **solid CTA buttons — always `text-white`** |
| **`accent`** / `accent-soft` | `#7cc0ff` / `#b8ddff` | highlighted words, labels, active states, borders on dark |
| **`leaf`** / `leaf-deep` | `#4cc13d` / `#3ca830` | logo green — ticks, success, "live" dots, small eco accents (use sparingly) |

Glow shadows use `rgb(15 108 189 / x)` (primary). Every `gold` class was renamed mechanically to
`primary` (solid bg) or `accent` (text/border/tint). **Review your files**: fix leftover hard-coded colours
(e.g. `rgb(31_95_191…)`, `#04060d`, `rgb(4_6_13…)` → night is now `rgb(2_11_26…)`), stale "gold" comments,
and any place where the mechanical rename reads badly (contrast, a tint that should be leaf, etc.).
Keep contrast ≥ 4.5:1 for body text, ≥ 3:1 for large text.

## 2. Speed (measured — Lighthouse mobile, home page, before)

Performance **47** · FCP 2.0s · **LCP 5.6s** · **TBT 3,480 ms** · CLS 0 · 730 KB.
Root causes found:
1. **WebGL globe mounts on page load** (it sits right below the hero) → a 3.3 s long task (shader compile + texture upload). Must be deferred until the journey section approaches the viewport.
2. **Hero text starts at `opacity:0` and waits for JS (motion)** → LCP is the tiny navbar wordmark at 5.6 s. Above-the-fold content must be visible in the server HTML; entrance animations must be pure CSS that do not delay first paint.
3. **Hero earth "O" animates `background-position`** → repaints every frame. Animate `transform` only.
4. Fonts ≈ 220 KB (Unbounded 115 KB, Manrope, JetBrains Mono). ~180 KB unused JS (motion + gsap on first load).
5. Heavy `backdrop-filter` blur on many layers costs a lot on mobile GPUs.

**Rules for this phase**
- Above the fold: no JS-gated visibility. Prefer CSS animations (`@keyframes` + `animation` with `both`) or `motion` with `initial={false}` for first paint.
- Animate only `transform` / `opacity`. No animating `background-position`, `width`, `top`, `filter: blur` on large areas.
- Heavy client code (three, gsap timelines, motion layout animations) should load/run only when near the viewport.
- Prefer CSS + `IntersectionObserver` over `motion` for simple reveal effects (smaller JS).
- `next/image`: always correct `sizes`; only the single hero image per page gets `preload`; everything else lazy.
- Use `content-visibility: auto` with `contain-intrinsic-size` on big below-the-fold sections where safe (not on sections with pinned ScrollTriggers or sticky children).
- Under `(max-width: 767px)` reduce `backdrop-blur` (e.g. `backdrop-blur-sm` or a solid `bg-surface/90`).

## 3. Mobile (target: 360–430 px phones, mid-range Android)
- No horizontal overflow at 360 px. 16 px side gutters.
- Tap targets ≥ 44 × 44 px. Spacing between adjacent tap targets.
- Form inputs `font-size ≥ 16px` (prevents iOS zoom), correct `inputMode` / `autoComplete` / `type`.
- Respect `env(safe-area-inset-*)` for fixed bottom UI.
- Use `svh`/`dvh` units for full-screen sections (not `vh`).
- Everything must still honour `prefers-reduced-motion`.

## Ground rules for agents
- Only edit the files you are assigned. Use the Edit tool for small targeted changes (other agents may edit
  neighbouring files at the same time). Never rewrite shared files you were not given.
- Do NOT run `next dev`, `next build`, `next start` or any server — the integrator measures with Lighthouse.
  You may run `npx tsc --noEmit` and `npx eslint <your files>`; ignore errors in files that aren't yours.
- No new npm packages.
- Final report (< 200 words): what you changed and why, with any integration notes.
