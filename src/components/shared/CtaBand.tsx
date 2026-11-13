import Image from "next/image";
import { Mail, Phone } from "lucide-react";
import { ButtonLink } from "@/components/ui/Button";
import { Container, Eyebrow, Section } from "@/components/ui/primitives";
import { Reveal } from "@/components/ui/Reveal";
import { WhatsAppIcon } from "@/components/layout/WhatsAppFab";
import { getSite } from "@/lib/cms";
import { whatsappLink } from "@/lib/utils";

/**
 * Closing conversion band: a dark-scoped photo card (`theme-dark`) under an always-dark `scrim`,
 * logo-blue glow with a leaf hint, WhatsApp + call CTAs. The outer wrapper sits in light scope so
 * `shadow-lift` keeps its soft navy tint on light pages.
 */
export async function CtaBand({
  title = "Your Kashmir story starts with one message.",
  subtitle = "Tell us your dates and who's travelling — we'll craft the route, stays and rides.",
}: {
  title?: string;
  subtitle?: string;
}) {
  const site = await getSite();
  const phone = site.phones[0];
  const email = site.emails[0];

  return (
    <Section>
      <Container>
        <Reveal>
          <div className="rounded-3xl shadow-lift">
            <div className="theme-dark relative isolate overflow-hidden rounded-3xl border border-line px-5 py-12 sm:px-12 sm:py-16 lg:px-16 lg:py-20">
              <Image
                src="/images/destinations/pahalgam.jpg"
                alt=""
                fill
                sizes="(min-width: 1280px) 1216px, 100vw"
                className="-z-20 object-cover"
              />
              <div aria-hidden className="absolute inset-0 -z-10 bg-linear-to-t from-scrim/95 via-scrim/75 to-scrim/40" />
              <div aria-hidden className="absolute inset-0 -z-10 bg-linear-to-r from-scrim/85 via-scrim/45 to-transparent" />
              {/* Logo-blue glow + a faint leaf-green hint (radial gradients — no costly filter blur) */}
              <div
                aria-hidden
                className="absolute -bottom-40 -left-32 -z-10 size-[30rem] bg-[radial-gradient(closest-side,rgb(15_108_189/0.4),transparent)] sm:size-[40rem]"
              />
              <div
                aria-hidden
                className="absolute -top-32 -right-16 -z-10 size-[24rem] bg-[radial-gradient(closest-side,rgb(76_193_61/0.14),transparent)]"
              />

              <div className="max-w-3xl">
                <Eyebrow className="mb-4">Plan your trip</Eyebrow>
                <h2 className="font-display text-[2rem] font-semibold leading-[1.12] tracking-[-0.01em] text-balance text-ink sm:text-[2.5rem] lg:text-[2.75rem]">
                  {title}
                </h2>
                <p className="mt-4 max-w-xl text-[15px] leading-relaxed text-muted text-pretty sm:text-[17px]">
                  {subtitle}
                </p>

                <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                  <ButtonLink
                    href={whatsappLink(site.whatsapp, "Hi Reyaan Trip Holiday! I'd like help planning a trip.")}
                    variant="primary"
                    size="lg"
                    className="w-full sm:w-auto"
                  >
                    <WhatsAppIcon className="size-5" />
                    Chat on WhatsApp
                    <span className="sr-only">(opens in a new tab)</span>
                  </ButtonLink>
                  <ButtonLink
                    href={`tel:${phone.raw}`}
                    variant="glass"
                    size="lg"
                    className="w-full max-md:bg-night/70 max-md:backdrop-blur-none sm:w-auto"
                  >
                    <Phone aria-hidden className="size-4" />
                    Call {phone.display}
                  </ButtonLink>
                </div>

                <p className="mt-4 flex flex-wrap items-center gap-x-3 text-sm text-muted">
                  <span className="label flex items-center gap-2">
                    <Mail aria-hidden className="size-3.5 text-accent" />
                    Or email
                  </span>
                  <a
                    href={`mailto:${email}`}
                    className="inline-block break-all py-3 text-ink underline decoration-line-strong underline-offset-4 transition-colors hover:text-accent hover:decoration-accent"
                  >
                    {email}
                  </a>
                </p>
              </div>
            </div>
          </div>
        </Reveal>
      </Container>
    </Section>
  );
}
