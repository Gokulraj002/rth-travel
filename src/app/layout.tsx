import type { Metadata, Viewport } from "next";
import { Playfair_Display, Manrope } from "next/font/google";
import { getSite } from "@/lib/cms";
import { Suspense } from "react";
import { Navbar, NavbarFallback } from "@/components/layout/Navbar";
import { Footer } from "@/components/layout/Footer";
import { SmoothScroll } from "@/components/layout/SmoothScroll";
import { WhatsAppFab } from "@/components/layout/WhatsAppFab";
import { JsonLd } from "@/components/seo/JsonLd";
import { SiteProvider } from "@/components/layout/SiteProvider";
import "./globals.css";

// Premium pairing: Playfair Display (high-contrast serif) for headings, Manrope for UI/body.
// Both are variable fonts — omitting `weight` loads one latin woff2 per family with the full weight axis.
// Labels/numbers use Manrope with tabular figures (no monospace webfont — see globals.css).
const display = Playfair_Display({
  variable: "--font-display",
  subsets: ["latin"],
  display: "swap",
  preload: true,
  adjustFontFallback: true,
});

const body = Manrope({
  variable: "--font-body",
  subsets: ["latin"],
  display: "swap",
  preload: true,
  adjustFontFallback: true,
});

export async function generateMetadata(): Promise<Metadata> {
  const site = await getSite();
  return {
    metadataBase: new URL(site.url),
    title: {
      default: `${site.name} — Kashmir, Ladakh, Katra & Jammu Tours`,
      template: `%s · ${site.name}`,
    },
    description: site.description,
    keywords: [
      "Kashmir tour packages",
      "Srinagar travel agency",
      "Ladakh tour",
      "Vaishno Devi package",
      "Gulmarg",
      "Pahalgam",
      "houseboat Dal Lake",
      "Reyaan Trip Holiday",
    ],
    openGraph: {
      type: "website",
      siteName: site.name,
      title: `${site.name} — ${site.tagline}`,
      description: site.description,
      images: [{ url: "/brand/og-default.jpg", width: 1200, height: 630, alt: site.name }],
      locale: "en_IN",
    },
    twitter: { card: "summary_large_image" },
  };
}

export const viewport: Viewport = {
  themeColor: "#ffffff",
  colorScheme: "light",
  viewportFit: "cover",
};

export default async function RootLayout({ children }: LayoutProps<"/">) {
  const site = await getSite();
  // Only the contact slice goes to the client (Navbar, mobile menu, WhatsApp CTAs, enquiry forms).
  const contact = {
    name: site.name,
    whatsapp: site.whatsapp,
    phones: site.phones,
    emails: site.emails,
    addresses: site.addresses,
  };

  return (
    <html lang="en-IN" className={`${display.variable} ${body.variable} antialiased`}>
      <body className="min-h-dvh bg-night text-ink">
        <SiteProvider value={contact}>
          <JsonLd />
          <Suspense fallback={null}>
            <SmoothScroll />
          </Suspense>
          <Suspense fallback={<NavbarFallback />}>
            <Navbar />
          </Suspense>
          <main id="main">{children}</main>
          <Footer />
          <WhatsAppFab />
        </SiteProvider>
      </body>
    </html>
  );
}
