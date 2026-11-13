import type { Metadata } from "next";
import { MessageCircle, Phone } from "lucide-react";
import { ContactCards } from "@/components/contact/ContactCards";
import { EnquiryForm } from "@/components/contact/EnquiryForm";
import { MapEmbed } from "@/components/contact/MapEmbed";
import { FaqList } from "@/components/shared/FaqList";
import { PageHero } from "@/components/shared/PageHero";
import { ButtonLink } from "@/components/ui/Button";
import { Container, Eyebrow, Section, SectionHeading } from "@/components/ui/primitives";
import { Reveal } from "@/components/ui/Reveal";
import { getDestinations, getFaqs, getPackages, getSite } from "@/lib/cms";
import { whatsappLink } from "@/lib/utils";

export async function generateMetadata(): Promise<Metadata> {
  const site = await getSite();
  const description = `Plan your Kashmir, Ladakh, Katra or Jammu trip with ${site.name}. Send an enquiry on WhatsApp, call ${site.phones[0].display} or visit us near Nowgam Bypass Chowk, Srinagar.`;
  return {
    title: "Contact",
    description,
    alternates: { canonical: "/contact" },
    openGraph: {
      type: "website",
      siteName: site.name,
      locale: "en_IN",
      title: `Contact · ${site.name}`,
      description,
      url: "/contact",
      images: [{ url: "/images/destinations/srinagar-2.jpg", width: 1600, height: 1067 }],
    },
  };
}

export default async function ContactPage() {
  const [site, faqs, destinations, packages] = await Promise.all([
    getSite(),
    getFaqs(),
    getDestinations(),
    getPackages(),
  ]);
  return (
    <>
      <PageHero
        eyebrow="WE'RE IN NOWGAM, SRINAGAR"
        title="CONTACT"
        subtitle="Tell us where you'd like to go and when. A local from our Srinagar team will help shape the trip — on WhatsApp, on a call or over email."
        image="/images/destinations/srinagar-2.jpg"
        imageAlt="A shikara gliding across Dal Lake in Srinagar at first light"
      >
        <div className="flex flex-col gap-3 sm:flex-row">
          <ButtonLink href={whatsappLink(site.whatsapp, `Hello ${site.name}! I'd like to plan a trip.`)} size="lg">
            <MessageCircle className="size-5" aria-hidden="true" />
            WhatsApp us
          </ButtonLink>
          <ButtonLink href={`tel:${site.phones[0].raw}`} variant="glass" size="lg">
            <Phone className="size-5" aria-hidden="true" />
            {site.phones[0].display}
          </ButtonLink>
        </div>
      </PageHero>

      <Section className="overflow-hidden bg-[radial-gradient(60%_50%_at_50%_0%,rgb(0_84_144/0.06),transparent_70%)]">
        <Container className="relative">
          <div className="grid items-start gap-10 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)] lg:gap-12">
            <Reveal>
              <div
                id="enquiry"
                className="scroll-mt-[calc(var(--header-h)+1.5rem)] rounded-2xl border border-line bg-surface p-5 shadow-lift sm:p-8 lg:p-10"
              >
                <Eyebrow>01 — Trip enquiry</Eyebrow>
                <h2 className="mt-3 font-display text-2xl font-semibold text-balance text-ink sm:text-[2rem]">
                  Plan your trip with us
                </h2>
                <p className="mt-3 mb-8 max-w-xl text-[15px] leading-relaxed text-muted text-pretty sm:text-base">
                  A few details are all we need to start. Hit send and your enquiry opens in WhatsApp, ready to go — or
                  send it by email if you prefer.
                </p>
                <EnquiryForm
                  // Only the fields the form needs, to keep the client payload small.
                  destinations={destinations.map(({ slug, name, region }) => ({ slug, name, region }))}
                  packages={packages.map(({ slug, title, region, nights, days, destinations: stops }) => ({
                    slug,
                    title,
                    region,
                    nights,
                    days,
                    destinations: stops,
                  }))}
                />
              </div>
            </Reveal>

            <Reveal delay={0.1} className="lg:sticky lg:top-[calc(var(--header-h)+1.5rem)]">
              <Eyebrow>02 — Reach us directly</Eyebrow>
              <h2 className="mt-3 mb-6 font-display text-2xl font-semibold text-balance text-ink sm:text-[1.75rem]">
                Talk to a local
              </h2>
              <ContactCards />
            </Reveal>
          </div>
        </Container>
      </Section>

      <Section className="pt-0 sm:pt-0">
        <Container>
          <Reveal>
            <SectionHeading
              eyebrow="03 — Find us"
              title="Drop by the office"
              lead="We're on the Nowgam side of Srinagar, close to the bypass — easy to reach from the airport and the city."
              className="mb-10"
            />
          </Reveal>
          <Reveal>
            <MapEmbed />
          </Reveal>
        </Container>
      </Section>

      {faqs.length > 0 && (
        <Section className="pt-0 sm:pt-0">
          <Container className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] lg:gap-16">
            <Reveal>
              <SectionHeading
                eyebrow="04 — Good to know"
                title="Questions, answered"
                lead="Can't find what you're looking for? Ask us on WhatsApp — we're happy to help."
              />
            </Reveal>
            <Reveal delay={0.1}>
              <FaqList items={faqs} />
            </Reveal>
          </Container>
        </Section>
      )}
    </>
  );
}
