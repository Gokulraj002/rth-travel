# Phase 3 brief — light premium theme · professional sticky header · normal sizes

Read first: `docs/ARCHITECTURE.md`, `docs/OPTIMIZATION_BRIEF.md` (speed + mobile rules still apply), `AGENTS.md`,
and `src/app/globals.css` (the new tokens). The owner's feedback: *"make it professional, sticky header,
sizes are too big — make them normal, check all fonts, make it premium, use a light background."*

## 1. Theme — light by default, dark only where it's immersive (already set up centrally)

Same token names, two scopes (see globals.css):
- `:root` = **light**: page `bg-night` (#f6f8fb off-white), `bg-night-2` (#edf2f8 alternate band), `bg-surface` (white cards),
  `bg-surface-2` (inputs/hover), `text-ink` (deep navy #0c1f3b), `text-muted`, `text-faint`, `border-line(-strong)` (navy hairlines),
  `bg-primary` (exact logo blue #005490, white text), `text-accent` (#0a63a6), `text-leaf` (logo green), `shadow-soft`, `shadow-lift`.
- `.theme-dark` = **dark navy** — add `theme-dark` to the root element of a section and every token inside flips to the old dark values.
- `scrim` (`from-scrim`, `bg-scrim/60` …) is **always dark navy** — use it for gradients/overlays on photos that carry white text.
  Do NOT use `night` for photo overlays any more (in light scope it is off-white).

Which sections are dark (`theme-dark`): **GlobeJourney** (space + 3D globe), **CinematicReveal** (navy knockout),
**PageHero** (photo hero with white text), **CtaBand** (photo band), **Footer**. Everything else is light.

Light-theme hygiene — fix in your files:
- `text-white` is only correct on `bg-primary`, on photos/scrims, or inside `theme-dark`.
- Replace dark-only tints (`bg-white/[0.03]`, `bg-white/5`, `border-white/10`, `text-white/70`, `stars-bg` on light sections,
  `rgb(2_11_26…)` glows, blurred navy glows) with light equivalents: `bg-surface`, `bg-surface-2`, `border-line`, `shadow-soft`,
  soft logo-blue tints like `bg-primary/5`, `bg-accent/10`, or subtle radial gradients of `rgb(0 84 144 / 0.06)`.
- Cards on light: `bg-surface border border-line rounded-2xl shadow-soft`, hover `shadow-lift` + `-translate-y-0.5`. No glass on light
  backgrounds unless content sits over an image.
- Images inside light cards should not need a scrim; put text BELOW the image (white card body), not on top, unless it's a hero.

## 2. Typography — premium, calm (fonts already swapped centrally)
- `font-display` = **Playfair Display** (elegant high-contrast serif). Use weights **500–600** (`font-medium`/`font-semibold`).
  Remove `font-black` / `font-extrabold` from display text — heavy serif looks cheap. Prefer **sentence case** for headings;
  uppercase only for tiny `label`s and the few signature giant words (hero "HOLIDAY", reveal words, destination names on photo heroes).
- `font-sans` = Manrope (body/UI). `font-mono` now maps to Manrope too; `label` = 11px Manrope semibold, tracked, uppercase, tabular nums.
- Remove `tracking-tight`/negative tracking on serif display text larger than 2rem except `tracking-[-0.01em]`.

## 3. Size scale — "normal, not too big" (apply everywhere)
| Element | Size |
|---|---|
| Body text | 15–16px (`text-[15px] sm:text-base`), muted lead 17px max |
| Section heading (`SectionHeading`, already updated) | 30px → 36px → 44px (`text-[1.875rem] sm:text-4xl lg:text-[2.75rem]`) |
| Card title | `text-lg`–`text-xl` serif semibold |
| Home hero title | max ≈ `clamp(2.75rem, 9vw, 7rem)` (was 14rem) |
| Inner PageHero title | `clamp(2.25rem, 6.5vw, 4.75rem)`, hero height `min-h-[46svh]`–`52svh` (was 78svh) |
| Giant display words (journey destination name, reveal word) | ≤ `clamp(2.5rem, 7vw, 5.5rem)` for journey; reveal word stays an effect but never taller than ~45% of viewport |
| Section vertical padding | `py-16 sm:py-24` (primitives `Section` already) — don't exceed `py-28` |
| Buttons | sm h-9, md h-11, lg h-12 (Button already) — no custom h-13/h-14 except the floating WhatsApp button |
| Package / destination cards | moderate: image `aspect-[4/3]` (or `[3/2]`), white body below with title, meta, price, CTA; grid gap-5/6 |
| Icons | 16–20px in UI, 40–48px icon circles max |

## 4. Professional sticky header (Navbar agent)
- `sticky top-0` (in normal flow — NOT fixed/overlaying content), height = `var(--header-h)` (64px mobile / 76px lg),
  always visible (no hide-on-scroll), `bg-surface/90 backdrop-blur-md border-b border-line`, a soft shadow once scrolled.
- Because the header is in flow now, sections no longer need ~128px top padding to clear it — reduce top paddings accordingly.
  Sticky children (e.g. journey stage, sticky sidebars) should use `top-[var(--header-h)]` and heights like `h-[calc(100svh-var(--header-h))]`.

## 5. Ground rules
- Edit only your assigned files (Edit tool, small targeted changes). Keep exports/props stable unless told.
- Keep all speed work: no new heavy libs, above-the-fold visible without JS, transform/opacity animations only, lazy three/gsap/motion.
- Keep accessibility (contrast ≥ 4.5:1 for text on the new light backgrounds!, 44px targets, 16px inputs).
- Do NOT run `next dev/build/start` or servers. Run `npx tsc --noEmit` and `npx eslint <your files>`; ignore other agents' errors.
- Final report < 180 words.
