import { MessageCircle, Sparkles } from "lucide-react";
import { ButtonLink } from "@/components/ui/Button";
import { site } from "@/data/site";
import { whatsappLink } from "@/lib/utils";

/** Shown when there are no packages in the data yet. */
export function EmptyState({ originCity }: { originCity?: string }) {
  const from = originCity ? ` I'll be flying in from ${originCity}.` : "";
  return (
    <div className="relative overflow-hidden rounded-2xl border border-line bg-surface p-6 shadow-lift sm:p-10">
      <div
        aria-hidden
        className="pointer-events-none absolute -right-24 -top-24 size-72 rounded-full bg-radial from-accent/10 to-transparent to-70%"
      />
      <div className="relative max-w-xl">
        <span className="inline-flex size-11 items-center justify-center rounded-full border border-accent/25 bg-accent/10 text-accent">
          <Sparkles aria-hidden className="size-5" />
        </span>
        <p className="label mt-6 text-accent">Made to measure</p>
        <h3 className="mt-3 font-display text-2xl font-semibold leading-tight text-ink sm:text-[1.75rem]">
          Custom itineraries on request
        </h3>
        <p className="mt-4 text-[15px] leading-relaxed text-muted text-pretty sm:text-base">
          Tell us your dates, who&apos;s travelling and what you dream of — houseboats, snow, high passes or a
          pilgrimage — and we&apos;ll plan a trip across {site.destinationsLine.join(", ")} around you.
        </p>
        <ButtonLink
          href={whatsappLink(
            `Hi ${site.name}! I'd like a custom itinerary for Kashmir / Ladakh.${from} Can you help me plan it?`,
          )}
          variant="primary"
          className="mt-7"
        >
          <MessageCircle aria-hidden className="size-4" />
          Plan my trip on WhatsApp
        </ButtonLink>
      </div>
    </div>
  );
}
