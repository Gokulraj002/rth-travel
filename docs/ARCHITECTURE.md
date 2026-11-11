# Reyaan Trip Holiday — Architecture & Build Brief

Front-end only marketing site for **Reyaan Trip Holiday (RTH)**, a Srinagar travel company
(Kashmir · Ladakh · Katra · Jammu). Design is inspired by a cinematic "concept travel agency"
reel: a night-sky canvas, a 3D globe that flies to each destination as you scroll, giant
ultra-bold display words revealed over photography, mono micro-labels, logo-blue pill CTAs and
frosted-glass info panels.

## Stack

| Concern | Choice |
|---|---|
| Framework | **Next.js 16.4 App Router**, TypeScript, `src/` dir, Turbopack |
| Styling | **Tailwind CSS v4** (tokens in `src/app/globals.css` via `@theme inline`) |
| 3D | three + @react-three/fiber v9 + @react-three/drei v10 |
| Scroll / motion | CSS animations first; GSAP + ScrollTrigger and Lenis (desktop only) loaded lazily; `motion` only in lazy/secondary UI |
| State | zustand (`src/lib/store.ts` — globe ⇄ scroll story) |
| Icons | lucide-react |

## Next.js 16 rules (this is NOT older Next.js — read `node_modules/next/dist/docs/` when unsure)

- `cacheComponents: true` is on. **Server components must not read `Date.now()`, `new Date()`, `Math.random()`**
  or other request-time values — anything time-based (clocks, weather) goes in a `"use client"` component.
- Dynamic route `params` is a **Promise**: `export default async function Page({ params }: PageProps<"/packages/[slug]">) { const { slug } = await params; }`.
  `PageProps<...>` / `LayoutProps<...>` are global types — no import needed.
  Every dynamic route needs `generateStaticParams()` returning **at least one** entry, plus `generateMetadata`.
  Call `notFound()` from `next/navigation` for unknown slugs.
- `next/dynamic(..., { ssr: false })` is only allowed **inside a `"use client"` file**.
- `next/image`: images are local under `/public` — no remote config needed. Use default quality (omit `quality`). Always pass `sizes` with `fill`.
- Default to Server Components; add `"use client"` only to leaves that need state/effects/browser APIs.

## Folder structure

```
src/
  app/
    layout.tsx              # fonts, metadata, Navbar/Footer/SmoothScroll/WhatsAppFab/JsonLd
    page.tsx                # Home — composes components/home/*
    globals.css             # design tokens + utilities (label, glass, stars-bg)
    destinations/page.tsx            + [slug]/page.tsx
    packages/page.tsx                + [slug]/page.tsx
    about/page.tsx
    contact/page.tsx
    not-found.tsx  error.tsx  sitemap.ts  robots.ts  manifest.ts  icon.png  apple-icon.png
  components/
    ui/            Button(ButtonLink), primitives (Container, Section, Eyebrow, SectionHeading, Pill), Reveal
    layout/        Navbar, Footer, SmoothScroll, WhatsAppFab
    seo/           JsonLd
    three/         GlobeStage (client wrapper, lazy), GlobeScene (R3F), Globe parts
    home/          HomeHero, GlobeJourney, CinematicReveal, PackageExplorer, WhyUs, Fleet, Testimonials
    destinations/  DestinationCard, LiveConditions, DestinationGallery …
    packages/      PackageCard, PackageFilters, ItineraryTimeline, BookingSidebar …
    contact/       EnquiryForm, ContactCards
    shared/        CtaBand, FaqList, PageHero
  data/            site.ts, destinations.ts, packages.ts, content.ts, origins.ts   ← all copy lives here
  lib/             utils.ts (cn, formatINR, whatsappLink, formatCoords, pad2), geo.ts, store.ts
  types/           index.ts (Destination, TourPackage, Vehicle, Testimonial, Faq, OriginCity …)
public/
  brand/           rth-logo.jpeg (logo on light-grey bg), rth-banner.jpeg
  images/destinations/<slug>.jpg + <slug>-2..7.jpg   (1600px Unsplash photos)
  textures/        earth-blue-marble.jpg, earth-night.jpg (4096×2048 equirectangular)
```

## Design tokens (Tailwind utilities) — all derived from the RTH logo

Logo: navy `#003060`, blue `#005490`, light blue `#84a8cc`, leaf green `#3ca830`.

Colours: `night` (#020b1a page bg) / `night-2`, `surface` / `surface-2`, `line` / `line-strong`,
`ink` / `muted` / `faint` (text), `brand` / `brand-deep` / `sky` (exact logo colours),
**`primary`** (#0f6cbd — solid CTAs, always with `text-white`) / `primary-bright`,
**`accent`** (#7cc0ff — highlighted words, labels, active states) / `accent-soft`,
**`leaf`** (#4cc13d — ticks, live dots, small eco accents) / `leaf-deep`.

Fonts: `font-display` (Unbounded), `font-sans` (Manrope), `font-mono` (system monospace stack).
A tiny "RTH Rupee" @font-face renders ₹ with the system font so the 117 KB latin-ext font file never loads.

Utilities: `label`, `glass` (frosted panel — avoid below md over moving content), `stars-bg`, `cv-auto`
(content-visibility for far-below-the-fold sections). Easing: `ease-out-expo`.

See `docs/OPTIMIZATION_BRIEF.md` for the performance and mobile rules (above-the-fold content must be
visible without JS, transform/opacity-only animation, three.js/gsap/motion loaded lazily, 44 px tap targets,
16 px form inputs).

## Shared state (`src/lib/store.ts`)
`useJourney` — `{ activeIndex, setActiveIndex, origin, setOrigin }`. `activeIndex = -1` is the
intro/whole-earth view; `0..n` focuses `destinations[i]`. DOM writes it, the WebGL globe reads it
with `useJourney.getState()` inside `useFrame` (no subscriptions inside the Canvas).

## Smooth scroll
`components/layout/SmoothScroll.tsx` creates one Lenis instance, wires
`lenis.on("scroll", ScrollTrigger.update)`, drives it from `gsap.ticker`, sets `gsap.ticker.lagSmoothing(0)`,
and is disabled under reduced motion. Other components just use GSAP ScrollTrigger normally
(`gsap.registerPlugin(ScrollTrigger)` inside a client component, `useGSAP` for cleanup).

## Content rules
- All copy/data comes from `src/data/*`. Contact info only from `site.ts` (from the owner's PDF).
- **No invented facts about the business**: no fake awards, years in business, traveller counts or ratings.
  Package prices are placeholders flagged in `packages.ts`. Testimonials are flagged as samples.
- Primary conversion = WhatsApp enquiry (`whatsappLink(message)`) and phone call (`tel:`).
