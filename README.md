# Reyaan Trip Holiday — Website

Cinematic marketing site for **Reyaan Trip Holiday** (Srinagar · Kashmir · Ladakh · Katra · Jammu).
Next.js 16 (App Router) · TypeScript · Tailwind CSS v4 · React Three Fiber globe · GSAP + Lenis scroll.

## Run it

```bash
npm install
npm run dev        # http://localhost:3000
npm run build      # production build
npm start          # serve the production build
```

## Edit content (no code knowledge needed)

| What | File |
|---|---|
| Phone numbers, emails, addresses, WhatsApp number, domain | `src/data/site.ts` |
| Destinations (text, highlights, photos) | `src/data/destinations.ts` |
| Tour packages, **prices**, itineraries | `src/data/packages.ts` |
| Why-us points, vehicles, testimonials, FAQs | `src/data/content.ts` |
| Photos | `public/images/destinations/` |
| Logo / banner | `public/brand/` |

**Before going live:** confirm package prices in `packages.ts` (they are placeholders), replace the
sample testimonials in `content.ts` with real guest reviews, and set the real domain in `site.ts`.

## Structure

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the full folder structure, design tokens and conventions.

```
src/app/            routes: / · /destinations · /destinations/[slug] · /packages · /packages/[slug] · /about · /contact
src/components/     ui · layout · home · three · destinations · packages · contact · shared · seo
src/data/           all site content
src/lib/            helpers (WhatsApp links, INR formatting, geo maths, globe state)
```

## Deploy

Works on Vercel out of the box (`vercel` or import the repo), or any Node host via `npm run build && npm start`.

Photography: Unsplash (free licence). Earth textures: NASA Blue Marble via three-globe examples.
