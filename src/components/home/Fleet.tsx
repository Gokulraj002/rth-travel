import Image from "next/image";
import { ArrowUpRight, Check, MessageCircle, Phone, Users } from "lucide-react";
import { fleet } from "@/data/content";
import { site as localSite } from "@/data/site";
import { getSite } from "@/lib/cms";
import { ButtonLink } from "@/components/ui/Button";
import { Container, Section, SectionHeading } from "@/components/ui/primitives";
import { Reveal } from "@/components/ui/Reveal";
import { whatsappLink } from "@/lib/utils";

const BOOK_MESSAGE = "Hi, I'd like to book a vehicle for my Kashmir trip";

/**
 * "On the road" — the branded Tempo Traveller (cropped from the company banner)
 * beside the vehicle list from `fleet`. Server component; entrance via Reveal.
 */
export async function Fleet() {
  const site = await getSite();
  const bookHref = whatsappLink(site.whatsapp, BOOK_MESSAGE);

  return (
    <Section id="fleet" className="overflow-hidden bg-night-2">
      <div
        aria-hidden
        className="pointer-events-none absolute -right-40 top-1/3 h-[420px] w-[min(640px,110vw)] bg-[radial-gradient(closest-side,color-mix(in_oklab,var(--accent)_8%,transparent),transparent)]"
      />

      <Container className="relative">
        <div className="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
          <Reveal>
            <SectionHeading
              eyebrow="ON THE ROAD"
              title={
                <>
                  Comfortable rides, <span className="text-accent">local drivers.</span>
                </>
              }
              lead="Our own vehicles, driven by people who know the valley roads, mountain passes and the best chai stops in between."
            />
          </Reveal>
          <Reveal delay={0.1} className="shrink-0">
            <ButtonLink href={bookHref}>
              <MessageCircle aria-hidden className="size-4" />
              Book a vehicle
            </ButtonLink>
          </Reveal>
        </div>

        <div className="mt-10 grid gap-6 sm:mt-12 lg:grid-cols-12 lg:gap-8">
          {/* Branded Tempo Traveller — cropped from the right side of the banner */}
          <Reveal className="lg:sticky lg:top-[calc(var(--header-h)+24px)] lg:col-span-6 lg:self-start xl:col-span-5">
            <figure className="theme-dark group relative isolate aspect-[4/5] overflow-hidden rounded-2xl shadow-soft">
              <Image
                src={localSite.banner}
                alt="Reyaan Trip Holiday's white branded Tempo Traveller van on a mountain road in Kashmir"
                fill
                sizes="(min-width: 1280px) 40vw, (min-width: 1024px) 50vw, 100vw"
                className="-z-20 origin-[100%_38%] scale-[2] object-cover object-right transition-transform duration-[1.6s] ease-out-expo group-hover:scale-[2.08]"
              />
              {/* hide the banner lettering at the left edge and seat the label */}
              <div aria-hidden className="absolute inset-0 -z-10 bg-linear-to-r from-scrim/85 via-scrim/15 to-transparent" />
              <div aria-hidden className="absolute inset-0 -z-10 bg-linear-to-t from-scrim/90 via-scrim/10 to-transparent" />

              <span className="label absolute left-5 top-5 text-white/80 sm:left-6 sm:top-6">RTH · Fleet</span>

              <figcaption className="absolute inset-x-4 bottom-4 sm:inset-x-6 sm:bottom-6">
                <div className="flex items-center justify-between gap-4 rounded-xl border border-white/15 bg-scrim/70 p-4 sm:p-5">
                  <div>
                    <p className="label text-accent-soft">Our Tempo Traveller</p>
                    <p className="mt-1.5 font-display text-lg font-semibold leading-tight text-white sm:text-xl">
                      Our own van, local drivers.
                    </p>
                  </div>
                  <span
                    aria-hidden
                    className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-white transition-transform duration-500 ease-out-expo group-hover:rotate-45"
                  >
                    <ArrowUpRight className="size-5" />
                  </span>
                </div>
              </figcaption>
            </figure>
          </Reveal>

          {/* Vehicle list */}
          <div className="lg:col-span-6 xl:col-span-7">
            {fleet.length > 0 ? (
              <ul className="grid gap-4">
                {fleet.map((v, i) => (
                  <Reveal as="li" key={v.name} delay={0.08 * i}>
                    <article className="group relative overflow-hidden rounded-2xl border border-line bg-surface p-5 shadow-soft inset-shadow-[0_1px_0_rgb(255_255_255/0.05)] transition-[box-shadow,transform,border-color] duration-500 ease-out-expo hover:-translate-y-0.5 hover:border-line-strong hover:shadow-lift sm:p-6">
                      <div className="flex flex-wrap items-start justify-between gap-3">
                        <h3 className="min-w-0 font-display text-xl font-semibold leading-snug text-balance text-ink">
                          {v.name}
                        </h3>
                        <span className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-accent/10 px-3 py-1 text-xs font-semibold text-accent ring-1 ring-accent/15">
                          <Users aria-hidden className="size-3.5" />
                          {v.seats}
                        </span>
                      </div>

                      <p className="mt-2.5 text-[15px] leading-relaxed text-muted text-pretty">{v.description}</p>

                      <p className="mt-3 text-sm">
                        <span className="label mr-2">Ideal for</span>
                        <span className="text-ink">{v.idealFor}</span>
                      </p>

                      {v.features.length > 0 && (
                        <ul className="mt-4 grid gap-x-6 gap-y-2 border-t border-line pt-4 sm:grid-cols-2">
                          {v.features.map((f) => (
                            <li key={f} className="flex items-start gap-2.5 text-sm text-muted">
                              <span className="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full bg-leaf/15 text-leaf">
                                <Check aria-hidden className="size-3" strokeWidth={3} />
                              </span>
                              <span>{f}</span>
                            </li>
                          ))}
                        </ul>
                      )}
                    </article>
                  </Reveal>
                ))}
              </ul>
            ) : (
              <Reveal>
                <div className="flex h-full flex-col justify-center rounded-2xl border border-line bg-surface p-6 shadow-soft inset-shadow-[0_1px_0_rgb(255_255_255/0.05)]">
                  <h3 className="font-display text-xl font-semibold text-ink">
                    The right vehicle for your group
                  </h3>
                  <p className="mt-2.5 text-[15px] leading-relaxed text-muted text-pretty">
                    Tell us how many are travelling and where you&apos;re headed, and we&apos;ll suggest the right
                    vehicle and driver for your route.
                  </p>
                </div>
              </Reveal>
            )}

            <Reveal delay={0.1}>
              <div className="mt-5 flex flex-col gap-4 rounded-2xl border border-dashed border-line-strong bg-surface/60 p-5 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-[15px] text-muted text-pretty">
                  Day trips, sightseeing or the full tour — message us your dates and group size, or call
                  us directly.
                </p>
                <ButtonLink href={`tel:${site.phones[0].raw}`} variant="outline" className="self-start sm:self-auto">
                  <Phone aria-hidden className="size-4" />
                  {site.phones[0].display}
                </ButtonLink>
              </div>
            </Reveal>
          </div>
        </div>
      </Container>
    </Section>
  );
}
