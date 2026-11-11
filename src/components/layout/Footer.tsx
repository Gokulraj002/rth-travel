import type { ReactNode } from "react";
import Image from "next/image";
import Link from "next/link";
import { ArrowUpRight, Mail, MapPin, Phone } from "lucide-react";
import { site } from "@/data/site";
import { destinations } from "@/data/destinations";
import { packages } from "@/data/packages";
import { whatsappLink } from "@/lib/utils";
import { ButtonLink } from "@/components/ui/Button";
import { Container } from "@/components/ui/primitives";
import { BackToTop, CurrentYear } from "@/components/layout/SmoothScroll";
import { WhatsAppIcon } from "@/components/layout/WhatsAppFab";

/** Footer links: ≥44px tall tap rows on touch layouts, compact on desktop; hover/focus in accent. */
const linkCls =
  "inline-flex min-h-11 items-center text-[15px] text-muted transition-colors duration-300 hover:text-accent lg:min-h-9";

function ColumnTitle({ children }: { children: ReactNode }) {
  return <h2 className="label mb-2 text-accent lg:mb-4">{children}</h2>;
}

export function Footer() {
  const topPackages = packages.slice(0, 5);

  return (
    // `theme-dark` flips every token inside to the dark navy palette — a premium closing band on light pages.
    <footer className="theme-dark relative overflow-hidden border-t border-line bg-night-2 text-ink">
      <div aria-hidden="true" className="pointer-events-none absolute inset-0 stars-bg opacity-25" />
      <div
        aria-hidden="true"
        className="pointer-events-none absolute left-1/2 top-0 h-px w-2/3 -translate-x-1/2 bg-gradient-to-r from-transparent via-accent/50 to-transparent"
      />

      {/* CTA strip */}
      <Container className="relative">
        <div className="flex flex-col gap-8 border-b border-line py-12 sm:py-16 md:flex-row md:items-end md:justify-between">
          <div className="max-w-xl">
            <p className="label mb-3 text-accent">Kashmir · Ladakh · Katra · Jammu</p>
            <h2 className="font-display text-[2rem] font-semibold leading-[1.15] tracking-[-0.01em] text-balance sm:text-[2.25rem] lg:text-[2.5rem]">
              Ready when <span className="text-accent">you are.</span>
            </h2>
            <p className="mt-4 text-[15px] leading-relaxed text-muted text-pretty sm:text-base">
              Tell us your dates and who&apos;s travelling — we&apos;ll shape the route, the stays and the drives.
            </p>
          </div>
          <div className="flex flex-col gap-3 sm:flex-row">
            <ButtonLink href={whatsappLink("Hi Reyaan Trip Holiday, I'd like to plan a trip")} variant="primary" size="lg">
              <WhatsAppIcon className="size-5" />
              Chat on WhatsApp
              <span className="sr-only">(opens in a new tab)</span>
            </ButtonLink>
            <ButtonLink href={`tel:${site.phones[0].raw}`} variant="glass" size="lg">
              <Phone aria-hidden="true" className="size-4" />
              {site.phones[0].display}
            </ButtonLink>
          </div>
        </div>
      </Container>

      {/* Columns */}
      <Container className="relative">
        <div className="grid gap-10 py-12 sm:grid-cols-2 sm:gap-12 sm:py-16 lg:grid-cols-12 lg:gap-8">
          <div className="lg:col-span-4">
            <Link href="/" className="group inline-flex items-center gap-3 rounded-xl">
              {/* Small white rounded tile — the logo JPEG has its own light ground, so the image fills the tile edge to edge. */}
              <span className="flex shrink-0 overflow-hidden rounded-xl bg-white shadow-soft ring-1 ring-white/20">
                <Image src={site.logo} alt="" width={627} height={497} sizes="64px" className="h-12 w-auto object-contain" />
              </span>
              <span className="flex flex-col leading-none">
                <span className="font-display text-lg font-semibold text-ink transition-colors duration-300 group-hover:text-accent">
                  REYAAN TRIP
                </span>
                <span className="mt-1.5 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-accent-soft">
                  Holiday
                  <span aria-hidden="true" className="size-1 shrink-0 rounded-full bg-leaf" />
                  <span className="sr-only">·</span>
                  Srinagar
                </span>
              </span>
            </Link>
            <p className="label mt-6 text-accent">{site.tagline}</p>
            <p className="mt-3 max-w-sm text-[15px] leading-relaxed text-muted text-pretty">
              A Srinagar-based travel company crafting tours across Kashmir, Ladakh, Katra and Jammu — with our own
              vehicles and local drivers.
            </p>
          </div>

          <nav aria-label="Destinations" className="lg:col-span-2">
            <ColumnTitle>Destinations</ColumnTitle>
            <ul className="space-y-0.5 lg:space-y-1">
              {destinations.map((d) => (
                <li key={d.slug}>
                  <Link href={`/destinations/${d.slug}`} className={linkCls}>
                    {d.name}
                  </Link>
                </li>
              ))}
              <li>
                <Link href="/destinations" className={`${linkCls} gap-1 text-ink`}>
                  All destinations <ArrowUpRight aria-hidden="true" className="size-3.5" />
                </Link>
              </li>
            </ul>
          </nav>

          <nav aria-label="Packages" className="lg:col-span-3">
            <ColumnTitle>Packages</ColumnTitle>
            <ul className="space-y-0.5 lg:space-y-1">
              {topPackages.map((p) => (
                <li key={p.slug}>
                  <Link href={`/packages/${p.slug}`} className={linkCls}>
                    {p.title}
                  </Link>
                </li>
              ))}
              <li>
                <Link href="/packages" className={`${linkCls} gap-1 text-ink`}>
                  All packages <ArrowUpRight aria-hidden="true" className="size-3.5" />
                </Link>
              </li>
            </ul>
          </nav>

          <div className="lg:col-span-3">
            <ColumnTitle>Contact</ColumnTitle>
            <ul className="space-y-0.5 lg:space-y-1">
              {site.phones.map((p) => (
                <li key={p.raw}>
                  <a href={`tel:${p.raw}`} className={`${linkCls} min-h-12 gap-2.5 text-ink lg:min-h-10`}>
                    <Phone aria-hidden="true" className="size-4 shrink-0 text-accent" />
                    {p.display}
                  </a>
                </li>
              ))}
              {site.emails.map((email) => (
                <li key={email}>
                  <a href={`mailto:${email}`} className={`${linkCls} min-h-12 gap-2.5 py-1 [overflow-wrap:anywhere] lg:min-h-10`}>
                    <Mail aria-hidden="true" className="size-4 shrink-0 text-accent" />
                    {email}
                  </a>
                </li>
              ))}
            </ul>
            <div className="mt-6 space-y-4">
              {site.addresses.map((a) => (
                <address key={a.line1} className="flex items-start gap-2.5 text-[15px] not-italic leading-relaxed text-muted">
                  <MapPin aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-accent" />
                  <span>
                    {a.line1}
                    <br />
                    {a.line2}
                  </span>
                </address>
              ))}
            </div>
          </div>
        </div>
      </Container>

      {/* Quiet wordmark — narrower (max-w-4xl ≈ 7vw glyph height), medium weight, 6% opacity.
          SVG textLength guarantees it never overflows the container. */}
      <Container className="relative">
        <svg
          aria-hidden="true"
          focusable="false"
          viewBox="0 0 1000 64"
          className="mx-auto block h-auto w-full max-w-4xl select-none pb-2 font-display text-sky/[0.06]"
        >
          <text
            x="500"
            y="56"
            textAnchor="middle"
            textLength="980"
            lengthAdjust="spacingAndGlyphs"
            fill="currentColor"
            fontSize="64"
            fontWeight="500"
          >
            REYAAN TRIP HOLIDAY
          </text>
        </svg>
      </Container>

      {/* Bottom bar */}
      <Container className="relative">
        <div className="flex flex-col gap-4 border-t border-line py-8 pb-[calc(2rem+env(safe-area-inset-bottom))] sm:flex-row sm:items-center sm:justify-between">
          <div className="space-y-1.5 text-[11px] font-medium uppercase leading-relaxed tracking-[0.14em] text-faint">
            <p>
              © <CurrentYear /> Reyaan Trip Holiday · Srinagar, Jammu &amp; Kashmir
            </p>
            <p>
              Photography:{" "}
              <a
                href="https://unsplash.com"
                target="_blank"
                rel="noopener noreferrer"
                className="-my-3 inline-block py-3 transition-colors hover:text-accent"
              >
                Unsplash
              </a>
            </p>
          </div>
          <BackToTop className="self-start sm:self-auto" />
        </div>
      </Container>
    </footer>
  );
}
