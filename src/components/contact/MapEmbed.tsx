import { ArrowUpRight, MapPin } from "lucide-react";
import { getSite } from "@/lib/cms";
import { cn } from "@/lib/utils";

/** Lazy Google Maps embed (normal colours) in a rounded, hairline-bordered frame. */
export async function MapEmbed({ className }: { className?: string }) {
  const site = await getSite();
  const embedSrc = `https://www.google.com/maps?q=${encodeURIComponent(site.mapQuery)}&output=embed`;
  const openHref = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(site.mapQuery)}`;
  return (
    <figure className={cn("relative overflow-hidden rounded-2xl border border-line bg-surface shadow-soft", className)}>
      <div className="relative bg-surface-2">
        <iframe
          title={`Map showing ${site.name} near ${site.mapQuery}`}
          src={embedSrc}
          loading="lazy"
          referrerPolicy="no-referrer-when-downgrade"
          allowFullScreen
          className="block h-[300px] w-full border-0 sm:h-[440px]"
        />
      </div>
      <figcaption className="relative border-t border-line bg-surface p-5 sm:absolute sm:bottom-5 sm:left-5 sm:max-w-sm sm:rounded-2xl sm:border sm:shadow-lift sm:p-6">
        <p className="label text-accent">Our base</p>
        <div className="mt-2 flex items-start gap-3">
          <MapPin className="mt-0.5 size-5 shrink-0 text-accent" aria-hidden="true" />
          <div className="text-sm leading-relaxed text-muted">
            {site.addresses.map((a) => (
              <p key={a.line1}>
                <span className="font-medium text-ink">{a.line1}</span>, {a.line2}
              </p>
            ))}
          </div>
        </div>
        <a
          href={openHref}
          target="_blank"
          rel="noopener noreferrer"
          className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-accent underline-offset-4 hover:underline"
        >
          Open in Google Maps
          <ArrowUpRight className="size-4" aria-hidden="true" />
          <span className="sr-only">(opens in a new tab)</span>
        </a>
      </figcaption>
    </figure>
  );
}
