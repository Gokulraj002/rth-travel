import { Car, Headphones, MapPin, MessageCircle, Phone, SlidersHorizontal } from "lucide-react";
import type { TourPackage } from "@/types";
import { site } from "@/data/site";
import { ButtonLink } from "@/components/ui/Button";
import { cn, formatINR, whatsappLink } from "@/lib/utils";
import { MobileEnquiryBar } from "./MobileEnquiryBar";

const trust = [
  { icon: MapPin, text: "Local Srinagar team who know every road" },
  { icon: Car, text: "Our own vehicles & local drivers" },
  { icon: Headphones, text: "24×7 on-trip support" },
];

const CARD_ID = "enquire";

/**
 * Enquiry card for a package detail page. Server component.
 * lg+: sticky sidebar column. Below lg: a compact card after the content, plus a fixed bottom
 * "Enquire on WhatsApp" bar (MobileEnquiryBar) that hides once this card is reached.
 * A solid white card (no backdrop blur); sticks just below the sticky site header on lg+.
 */
export function BookingSidebar({ pkg, className }: { pkg: TourPackage; className?: string }) {
  const message = `Hi ${site.name}! I'm interested in the "${pkg.title}" package (${pkg.nights}N / ${pkg.days}D). Could you share availability and a quote?`;
  const href = whatsappLink(message);
  const price = formatINR(pkg.priceFrom);

  return (
    <>
      <aside
        id={CARD_ID}
        aria-label="Enquire about this package"
        className={cn(
          "min-w-0 scroll-mt-[calc(var(--header-h)+24px)] lg:sticky lg:top-[calc(var(--header-h)+24px)] lg:self-start",
          className,
        )}
      >
        <div className="rounded-2xl border border-line bg-surface p-5 shadow-lift sm:p-6">
          <div className="flex items-start justify-between gap-4">
            <div className="min-w-0">
              <p className="label">From · per person</p>
              <p className="mt-2 text-[1.75rem] font-semibold leading-none tabular-nums text-ink sm:text-[2rem]">
                {price}
                <span className="align-super text-sm text-faint">*</span>
              </p>
            </div>
            <div className="shrink-0 rounded-xl border border-line bg-surface-2 px-3 py-2 text-center">
              <p className="text-base font-semibold leading-none tabular-nums text-ink">
                {pkg.nights}N<span className="text-faint"> / </span>
                {pkg.days}D
              </p>
              <p className="label mt-1.5 text-[10px]">Duration</p>
            </div>
          </div>
          <p className="mt-3 text-xs leading-relaxed text-faint">
            *Indicative, confirm on enquiry. Final price depends on dates, hotel category and group size.
          </p>

          <div className="mt-5 flex flex-col gap-3">
            <ButtonLink href={href} variant="primary" size="lg" className="w-full">
              <MessageCircle className="size-5" aria-hidden />
              Enquire on WhatsApp
            </ButtonLink>

            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 [&>*]:min-w-0">
              {site.phones.map((p) => (
                <ButtonLink
                  key={p.raw}
                  href={`tel:${p.raw}`}
                  variant="outline"
                  size="md"
                  className="w-full px-3 text-[13px]"
                >
                  <Phone className="size-4 shrink-0" aria-hidden />
                  {p.display}
                </ButtonLink>
              ))}
            </div>

            <ButtonLink href={`/contact?package=${encodeURIComponent(pkg.slug)}`} variant="secondary" size="md" className="w-full">
              <SlidersHorizontal className="size-4" aria-hidden />
              Customise this trip
            </ButtonLink>
          </div>

          {/* Trust points — full sidebar only; the compact mobile card keeps to price + actions. */}
          <ul className="mt-6 hidden space-y-3 border-t border-line pt-5 lg:block">
            {trust.map(({ icon: Icon, text }) => (
              <li key={text} className="flex items-start gap-3 text-sm text-muted">
                <span className="grid size-7 shrink-0 place-items-center rounded-full bg-primary/10">
                  <Icon className="size-3.5 text-accent" aria-hidden />
                </span>
                <span className="pt-1 leading-snug">{text}</span>
              </li>
            ))}
          </ul>
        </div>
      </aside>

      <MobileEnquiryBar href={href} targetId={CARD_ID} priceLabel={`${price}*`} />
    </>
  );
}
