"use client";

import { useId, useState, type FormEvent, type ReactNode } from "react";
import { ChevronDown, Minus, Plus, Send } from "lucide-react";
import { originCities } from "@/data/origins";
import { useJourney } from "@/lib/store";
import { cn, whatsappLink } from "@/lib/utils";
import { Button } from "@/components/ui/Button";
import { useSite } from "@/components/layout/SiteProvider";

const MONTHS = [
  "January",
  "February",
  "March",
  "April",
  "May",
  "June",
  "July",
  "August",
  "September",
  "October",
  "November",
  "December",
] as const;

const MIN_TRAVELLERS = 1;
const MAX_TRAVELLERS = 20;
const DEFAULT_DESTINATION = "srinagar";

/** Shared field shell: tinted tile in the mobile/tablet grid, flat segment in the desktop row. */
function Field({
  label,
  labelFor,
  labelId,
  className,
  children,
}: {
  label: string;
  labelFor?: string;
  labelId?: string;
  className?: string;
  children: ReactNode;
}) {
  const labelCls = "label block";
  return (
    <div
      className={cn(
        "relative flex min-w-0 flex-col justify-center gap-0.5 rounded-xl border border-line bg-surface-2/70 px-3 py-2 transition-colors sm:px-4",
        "focus-within:border-accent/60 focus-within:bg-surface-2 hover:border-line-strong",
        "lg:gap-0 lg:border-transparent lg:bg-transparent lg:px-5 lg:py-1.5 lg:hover:border-transparent lg:hover:bg-surface-2/60 lg:focus-within:border-accent/40",
        className,
      )}
    >
      {labelFor ? (
        <label htmlFor={labelFor} className={labelCls}>
          {label}
        </label>
      ) : (
        <span id={labelId} className={labelCls}>
          {label}
        </span>
      )}
      {children}
    </div>
  );
}

function Select({
  id,
  value,
  onChange,
  children,
}: {
  id: string;
  value: string;
  onChange: (value: string) => void;
  children: ReactNode;
}) {
  return (
    <div className="relative">
      <select
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className={cn(
          // 16px text stops iOS zooming on focus; 44px tall tap target at every size.
          "min-h-11 w-full min-w-0 cursor-pointer appearance-none truncate rounded-md bg-transparent py-0.5 pr-6 text-base font-semibold text-ink",
        )}
      >
        {children}
      </select>
      <ChevronDown
        aria-hidden="true"
        className="pointer-events-none absolute right-0 top-1/2 size-4 -translate-y-1/2 text-faint"
      />
    </div>
  );
}

const optionCls = "bg-surface text-ink";

/**
 * Flight-search-style trip planner on frosted navy glass. Composes the selections into a WhatsApp message.
 * FROM is bound to the shared journey store, so the globe's flight arc follows it.
 * `destinations` (slug + name) comes from the server parent (CMS).
 *
 * Enquiry saving: this planner only collects month / travellers / destination — no name or phone —
 * and the CMS enquiries API requires a phone number, so it does NOT post a lead; it only opens WhatsApp.
 * Only the contact-page EnquiryForm (which has name + phone) saves enquiries.
 */
export function HeroPlanner({ destinations }: { destinations: { slug: string; name: string }[] }) {
  const site = useSite();
  const uid = useId();
  const ids = {
    from: `${uid}-from`,
    to: `${uid}-to`,
    month: `${uid}-month`,
    travellers: `${uid}-travellers`,
    count: `${uid}-count`,
  };

  const origin = useJourney((s) => s.origin);
  const setOrigin = useJourney((s) => s.setOrigin);
  const [to, setTo] = useState(
    destinations.some((d) => d.slug === DEFAULT_DESTINATION) ? DEFAULT_DESTINATION : (destinations[0]?.slug ?? ""),
  );
  const [month, setMonth] = useState("");
  const [travellers, setTravellers] = useState(2);

  const destinationName = destinations.find((d) => d.slug === to)?.name ?? "Srinagar";

  const changeTravellers = (delta: number) =>
    setTravellers((n) => Math.min(MAX_TRAVELLERS, Math.max(MIN_TRAVELLERS, n + delta)));

  const onSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const message = [
      `Hi ${site.name}! I'd like to plan a trip.`,
      `From: ${origin.city} (${origin.code})`,
      `To: ${destinationName}`,
      `Month: ${month || "Flexible"}`,
      `Travellers: ${travellers}`,
      "Please share the best options. Thank you!",
    ].join("\n");
    window.open(whatsappLink(site.whatsapp, message), "_blank", "noopener,noreferrer");
  };

  return (
    <form
      onSubmit={onSubmit}
      aria-label="Plan your trip"
      className={cn(
        // Frosted navy glass: solid-ish below md (no backdrop blur on phones — it's costly on mobile
        // GPUs), blurred glass from md. Hairline border + a faint top inner highlight.
        // 2-col grid on phones/tablets, one row from lg.
        "mx-auto grid w-full max-w-md grid-cols-2 gap-2 rounded-2xl border border-line bg-surface/90 p-2.5 text-left sm:max-w-xl sm:gap-3 sm:p-3",
        "shadow-[inset_0_1px_0_rgb(255_255_255/0.06),0_1px_2px_rgb(0_0_0/0.3),0_28px_60px_-24px_rgb(0_0_0/0.75)]",
        "md:bg-surface/85",
        "lg:flex lg:max-w-5xl lg:items-center lg:gap-1 lg:p-2.5",
      )}
    >
      <Field label="From" labelFor={ids.from} className="lg:flex-[1.2]">
        <Select id={ids.from} value={origin.code} onChange={setOrigin}>
          {originCities.map((c) => (
            <option key={c.code} value={c.code} className={optionCls}>
              {c.city} · {c.code}
            </option>
          ))}
        </Select>
      </Field>

      <Divider />

      <Field label="To" labelFor={ids.to} className="lg:flex-[1.2]">
        <Select id={ids.to} value={to} onChange={setTo}>
          {destinations.map((d) => (
            <option key={d.slug} value={d.slug} className={optionCls}>
              {d.name}
            </option>
          ))}
        </Select>
      </Field>

      <Divider />

      <Field label="Month" labelFor={ids.month} className="lg:flex-1">
        <Select id={ids.month} value={month} onChange={setMonth}>
          <option value="" className={optionCls}>
            Flexible
          </option>
          {MONTHS.map((m) => (
            <option key={m} value={m} className={optionCls}>
              {m}
            </option>
          ))}
        </Select>
      </Field>

      <Divider />

      <Field label="Travellers" labelId={ids.travellers} className="lg:flex-1">
        <div
          role="group"
          aria-labelledby={ids.travellers}
          className="flex items-center justify-between gap-1 lg:gap-2"
        >
          <StepButton
            label="Remove a traveller"
            disabled={travellers <= MIN_TRAVELLERS}
            onClick={() => changeTravellers(-1)}
          >
            <Minus className="size-3.5" aria-hidden="true" />
          </StepButton>
          <output
            id={ids.count}
            aria-live="polite"
            className="min-w-[2ch] text-center text-base font-semibold tabular-nums text-ink"
          >
            {travellers}
            <span className="sr-only">{travellers === 1 ? " traveller" : " travellers"}</span>
          </output>
          <StepButton
            label="Add a traveller"
            disabled={travellers >= MAX_TRAVELLERS}
            onClick={() => changeTravellers(1)}
          >
            <Plus className="size-3.5" aria-hidden="true" />
          </StepButton>
        </div>
      </Field>

      <Button type="submit" size="lg" className="col-span-2 mt-0.5 lg:ml-2 lg:mt-0 lg:shrink-0 lg:px-7">
        Plan my trip
        <Send className="size-4" aria-hidden="true" />
        <span className="sr-only">(opens WhatsApp in a new tab)</span>
      </Button>
    </form>
  );
}

function Divider() {
  return <span aria-hidden="true" className="hidden h-10 w-px shrink-0 bg-line lg:block" />;
}

function StepButton({
  label,
  disabled,
  onClick,
  children,
}: {
  label: string;
  disabled: boolean;
  onClick: () => void;
  children: ReactNode;
}) {
  return (
    <button
      type="button"
      aria-label={label}
      disabled={disabled}
      onClick={onClick}
      // 44×44 hit area; the visible ring is the smaller inner circle.
      className="group/step grid size-11 shrink-0 place-items-center rounded-full text-ink transition-colors hover:text-accent disabled:cursor-not-allowed disabled:opacity-35 disabled:hover:text-ink"
    >
      <span className="grid size-8 place-items-center rounded-full border border-line-strong bg-surface-2/60 transition-colors group-hover/step:border-accent group-disabled/step:border-line-strong">
        {children}
      </span>
    </button>
  );
}
