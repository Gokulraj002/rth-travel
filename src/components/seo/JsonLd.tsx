import { getSite } from "@/lib/cms";
import { absoluteUrl } from "@/lib/utils";

/**
 * Site-wide schema.org TravelAgency structured data.
 * Rendered once from the root layout. Values come from the CMS settings (`getSite()`).
 */
export async function JsonLd() {
  const site = await getSite();
  const data = {
    "@context": "https://schema.org",
    "@type": "TravelAgency",
    "@id": `${site.url}/#organization`,
    name: site.name,
    alternateName: site.shortName,
    description: site.description,
    slogan: site.tagline,
    url: site.url,
    logo: absoluteUrl(site.logo, site.url),
    image: [absoluteUrl("/brand/og-default.jpg", site.url), absoluteUrl(site.banner, site.url)],
    telephone: site.phones.map((p) => p.raw),
    email: [...site.emails],
    address: {
      "@type": "PostalAddress",
      streetAddress: "Near Nowgam Bypass Chowk, Nowgam",
      addressLocality: "Srinagar",
      addressRegion: "Jammu and Kashmir",
      postalCode: "190015",
      addressCountry: "IN",
    },
    areaServed: site.destinationsLine.map((name) => ({ "@type": "Place", name })),
    contactPoint: site.phones.map((p) => ({
      "@type": "ContactPoint",
      telephone: p.raw,
      contactType: "customer service",
      areaServed: "IN",
    })),
    sameAs: [site.social.instagram, site.social.facebook, site.social.youtube].filter(Boolean),
  };

  return (
    <script
      type="application/ld+json"
      // Escape "<" so the payload can never close the script tag early.
      dangerouslySetInnerHTML={{ __html: JSON.stringify(data).replace(/</g, "\\u003c") }}
    />
  );
}
