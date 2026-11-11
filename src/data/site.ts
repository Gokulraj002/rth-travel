/**
 * Business details — sourced from Reyaan_Trip_Holiday_Text_Information.pdf.
 * Change contact info here only; every component reads from this file.
 */
export const site = {
  name: "Reyaan Trip Holiday",
  shortName: "RTH",
  tagline: "Explore • Travel • Create Memories",
  taglineParts: ["Explore", "Travel", "Create Memories"],
  description:
    "Reyaan Trip Holiday is a Srinagar-based travel company crafting tours across Kashmir, Ladakh, Katra and Jammu — houseboats, snow, high passes and pilgrimages, with our own vehicles and local drivers.",
  url: "https://reyaantripholiday.com", // TODO: replace with the real domain once purchased
  phones: [
    { display: "+91 60060 45211", raw: "+916006045211" },
    { display: "+91 94197 45211", raw: "+919419745211" },
  ],
  /** Number used for WhatsApp deep links (no plus sign) */
  whatsapp: "916006045211",
  emails: ["reyaantripholidaytravel@gmail.com", "reyaanfida10@gmail.com"],
  addresses: [
    { line1: "Near Jammu and Kashmir Bank", line2: "Nowgam, Srinagar" },
    { line1: "Near Nowgam Bypass Chowk", line2: "Srinagar, J&K 190015" },
  ],
  mapQuery: "Nowgam Bypass Chowk, Srinagar 190015",
  destinationsLine: ["Srinagar", "Ladakh", "Katra", "Jammu"],
  logo: "/brand/rth-logo.jpeg",
  banner: "/brand/rth-banner.jpeg",
  social: {
    instagram: "", // TODO: add Instagram profile URL
    facebook: "",
  },
} as const;

export const navLinks = [
  { href: "/", label: "Home" },
  { href: "/destinations", label: "Destinations" },
  { href: "/packages", label: "Packages" },
  { href: "/about", label: "About" },
  { href: "/contact", label: "Contact" },
] as const;
