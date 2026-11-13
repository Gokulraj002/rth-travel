import type { ComponentType, ReactNode } from "react";
import { ArrowUpRight, Mail, MapPin, MessageCircle, Navigation, Phone } from "lucide-react";
import { getSite } from "@/lib/cms";
import { cn, pad2, whatsappLink } from "@/lib/utils";

function Card({
  index,
  label,
  title,
  icon: Icon,
  children,
}: {
  index: number;
  label: string;
  title: string;
  icon: ComponentType<{ className?: string; "aria-hidden"?: boolean }>;
  children: ReactNode;
}) {
  return (
    <li className="group relative min-w-0 rounded-2xl border border-line bg-surface p-5 shadow-soft transition-all duration-300 ease-out-expo hover:-translate-y-0.5 hover:shadow-lift sm:p-6">
      <div className="relative flex items-start gap-4">
        <span className="grid size-11 shrink-0 place-items-center rounded-full bg-primary/10 text-primary transition-colors duration-300 group-hover:bg-primary group-hover:text-white">
          <Icon className="size-5" aria-hidden />
        </span>
        <div className="min-w-0 flex-1">
          <p className="label">
            {pad2(index)} — {label}
          </p>
          <h3 className="mt-1 font-display text-lg font-semibold text-ink">{title}</h3>
          <div className="mt-3 space-y-2 text-[15px] leading-relaxed text-muted">{children}</div>
        </div>
      </div>
    </li>
  );
}

const linkCls =
  "inline-flex items-center gap-1.5 font-medium text-ink underline-offset-4 transition-colors hover:text-accent hover:underline [overflow-wrap:anywhere]";

/** Call / WhatsApp / Email / Visit cards. All details come from the CMS settings (`getSite()`). */
export async function ContactCards({ className }: { className?: string }) {
  const site = await getSite();
  const directionsHref = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(site.mapQuery)}`;
  const whatsappDisplay =
    site.phones.find((p) => p.raw.replace(/\D/g, "") === site.whatsapp)?.display ?? `+${site.whatsapp}`;

  return (
    <ul className={cn("grid gap-4 sm:grid-cols-2 lg:grid-cols-1", className)}>
      <Card index={1} label="Call" title="Speak to us" icon={Phone}>
        {site.phones.map((p) => (
          <p key={p.raw}>
            <a href={`tel:${p.raw}`} className={cn(linkCls, "text-base tabular-nums")}>
              {p.display}
            </a>
          </p>
        ))}
      </Card>

      <Card index={2} label="WhatsApp" title="Message us" icon={MessageCircle}>
        <p>Share your dates and plans — the quickest way to get an itinerary going.</p>
        <p>
          <a
            href={whatsappLink(site.whatsapp, `Hello ${site.name}! I'd like to plan a trip.`)}
            target="_blank"
            rel="noopener noreferrer"
            className={cn(linkCls, "text-accent")}
          >
            Chat on {whatsappDisplay}
            <ArrowUpRight className="size-4 shrink-0" aria-hidden="true" />
            <span className="sr-only">(opens WhatsApp in a new tab)</span>
          </a>
        </p>
      </Card>

      <Card index={3} label="Email" title="Write to us" icon={Mail}>
        {site.emails.map((email) => (
          <p key={email}>
            <a href={`mailto:${email}`} className={linkCls}>
              {email}
            </a>
          </p>
        ))}
      </Card>

      <Card index={4} label="Visit us" title="Nowgam, Srinagar" icon={MapPin}>
        {site.addresses.map((a) => (
          <address key={a.line1} className="not-italic leading-relaxed">
            {a.line1}
            <br />
            {a.line2}
          </address>
        ))}
        <p className="pt-1">
          <a href={directionsHref} target="_blank" rel="noopener noreferrer" className={cn(linkCls, "text-accent")}>
            <Navigation className="size-4 shrink-0" aria-hidden="true" />
            Get directions
            <span className="sr-only">(opens Google Maps in a new tab)</span>
          </a>
        </p>
      </Card>
    </ul>
  );
}
