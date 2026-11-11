import type { Faq, Testimonial, Vehicle } from "@/types";

/**
 * Marketing copy shared across pages.
 * (Stub — filled in by the data agent.)
 */

export interface WhyUsItem {
  title: string;
  description: string;
  /** lucide-react icon name, e.g. "MapPin" */
  icon: "MapPin" | "Car" | "ShieldCheck" | "Headphones" | "BedDouble" | "Wallet";
}

export const whyUs: WhyUsItem[] = [];

export const fleet: Vehicle[] = [];

/** SAMPLE testimonials — replace with real guest reviews before going live. */
export const testimonials: Testimonial[] = [];

export const faqs: Faq[] = [];
