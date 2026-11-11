import type { Metadata } from "next";
import { packages } from "@/data/packages";
import { PageHero } from "@/components/shared/PageHero";
import { CtaBand } from "@/components/shared/CtaBand";
import { PackageGrid } from "@/components/packages/PackageGrid";
import { Container, Section } from "@/components/ui/primitives";

export const metadata: Metadata = {
  title: "Tour Packages — Kashmir, Ladakh, Katra & Jammu",
  description:
    "Kashmir, Ladakh, Katra and Jammu tour packages from a local Srinagar team — honeymoon, family, snow, adventure and pilgrimage trips with our own vehicles and drivers. Every itinerary can be customised.",
  alternates: { canonical: "/packages" },
  openGraph: {
    title: "Tour Packages · Reyaan Trip Holiday",
    description:
      "Honeymoon, family, snow, adventure and pilgrimage trips across Kashmir, Ladakh, Katra and Jammu — crafted in Srinagar.",
    images: [{ url: "/images/destinations/sonamarg.jpg", width: 1600, height: 1067 }],
  },
};

export default function PackagesPage() {
  return (
    <>
      <PageHero
        eyebrow="Tour packages · Kashmir · Ladakh · Jammu"
        title="PACKAGES"
        subtitle="Houseboats and high passes, snow days and pilgrimages — itineraries we run with our own vehicles and drivers. Pick one as it is, or let us shape it around you."
        image="/images/destinations/sonamarg.jpg"
        imageAlt="Snow-capped peaks above the Sindh valley meadows at Sonamarg"
      />

      <Section className="pt-12 sm:pt-16">
        <Container>
          <h2 className="sr-only">Browse packages</h2>
          <PackageGrid packages={packages} />
        </Container>
      </Section>

      <CtaBand
        title="Don't see your trip?"
        subtitle="Tell us your dates, group and pace — our Srinagar team will build an itinerary around you."
      />
    </>
  );
}
