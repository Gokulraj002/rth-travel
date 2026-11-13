"use client";

import {
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  useSyncExternalStore,
  type FormEvent,
  type ReactNode,
} from "react";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import { ArrowLeft, ChevronDown, CircleCheck, Mail, MessageCircle, Minus, Phone, Plus } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { useSite } from "@/components/layout/SiteProvider";
import { regionLabels } from "@/data/labels";
import { enquirySavingEnabled, saveEnquiry } from "@/lib/enquiry";
import { cn, whatsappLink } from "@/lib/utils";
import type { Destination, Region, TourPackage } from "@/types";

/** The destination / package fields this form needs (passed from the server page, from the CMS). */
export type EnquiryDestination = Pick<Destination, "slug" | "name" | "region">;
export type EnquiryPackage = Pick<TourPackage, "slug" | "title" | "region" | "nights" | "days" | "destinations">;

/* ------------------------------------------------------------------ */
/* Options                                                             */
/* ------------------------------------------------------------------ */

const HOTEL_OPTIONS = ["Budget", "Standard", "Deluxe", "Houseboat", "Luxury"] as const;
type Hotel = (typeof HOTEL_OPTIONS)[number];

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

const ADULTS = { min: 1, max: 30 };
const CHILDREN = { min: 0, max: 20 };

const REGION_ORDER: Region[] = ["kashmir", "ladakh", "jammu"];

/* ------------------------------------------------------------------ */
/* Query-string reading (client only, no searchParams on the server)   */
/* ------------------------------------------------------------------ */

function subscribeToLocation(onChange: () => void) {
  window.addEventListener("popstate", onChange);
  return () => window.removeEventListener("popstate", onChange);
}
const getSearch = () => window.location.search;
const getServerSearch = () => "";

function findPackage(packages: EnquiryPackage[], value?: string | null): EnquiryPackage | undefined {
  if (!value) return undefined;
  const v = value.trim().toLowerCase();
  return packages.find((p) => p.slug === v || p.title.toLowerCase() === v);
}

function findDestinationSlug(destinations: EnquiryDestination[], value?: string | null): string | undefined {
  if (!value) return undefined;
  const v = value.trim().toLowerCase();
  return destinations.find((d) => d.slug === v || d.name.toLowerCase() === v)?.slug;
}

/* ------------------------------------------------------------------ */
/* Validation                                                          */
/* ------------------------------------------------------------------ */

interface Values {
  name: string;
  phone: string;
  email: string;
  month: string;
  adults: string;
  children: string;
  hotel: Hotel | "";
  message: string;
}

type FieldKey = "name" | "phone" | "email" | "adults" | "children";
const FIELD_ORDER: FieldKey[] = ["name", "phone", "email", "adults", "children"];

/** Indian mobile: 10 digits starting 6–9, optionally prefixed with +91 / 91 / 0. Spaces, dashes and brackets ignored. */
const PHONE_RE = /^(?:\+?91|0)?[6-9]\d{9}$/;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

function validate(v: Values): Partial<Record<FieldKey, string>> {
  const errors: Partial<Record<FieldKey, string>> = {};
  if (v.name.trim().length < 2) errors.name = "Please tell us your name.";

  const phone = v.phone.replace(/[\s\-().]/g, "");
  if (!phone) errors.phone = "Please add a phone number so we can reach you.";
  else if (!PHONE_RE.test(phone))
    errors.phone = "Enter a 10-digit Indian mobile number, e.g. 98765 43210 or +91 98765 43210.";

  if (v.email.trim() && !EMAIL_RE.test(v.email.trim())) errors.email = "That email doesn't look quite right.";

  const adults = Number(v.adults);
  if (!Number.isInteger(adults) || adults < ADULTS.min || adults > ADULTS.max)
    errors.adults = `Adults should be between ${ADULTS.min} and ${ADULTS.max}.`;

  const children = Number(v.children === "" ? 0 : v.children);
  if (!Number.isInteger(children) || children < CHILDREN.min || children > CHILDREN.max)
    errors.children = `Children should be between ${CHILDREN.min} and ${CHILDREN.max}.`;

  return errors;
}

/* ------------------------------------------------------------------ */
/* Message composition                                                 */
/* ------------------------------------------------------------------ */

function plural(n: number, one: string, many: string) {
  return `${n} ${n === 1 ? one : many}`;
}

function composeMessage(
  v: Values,
  siteName: string,
  destination: string | undefined,
  pkg: EnquiryPackage | undefined,
  channel: "whatsapp" | "email",
) {
  const bold = (s: string) => (channel === "whatsapp" ? `*${s}*` : s);
  const adults = Number(v.adults);
  const children = Number(v.children || 0);
  const travellers =
    plural(adults, "adult", "adults") + (children > 0 ? `, ${plural(children, "child", "children")}` : "");

  const lines: (string | null)[] = [
    `Hello ${siteName}! I'd like to plan a trip.`,
    "",
    bold("Trip enquiry"),
    `Name: ${v.name.trim()}`,
    `Phone: ${v.phone.trim()}`,
    v.email.trim() ? `Email: ${v.email.trim()}` : null,
    `Destination: ${destination ?? "Not sure yet"}`,
    pkg ? `Package: ${pkg.title} (${pkg.nights}N / ${pkg.days}D)` : null,
    `Travel month: ${v.month || "Flexible"}`,
    `Travellers: ${travellers}`,
    `Hotel category: ${v.hotel || "No preference"}`,
  ];

  if (v.message.trim()) lines.push("", bold("Message"), v.message.trim());
  lines.push("", "Sent from the website enquiry form.");

  return lines.filter((l): l is string => l !== null).join("\n");
}

/* ------------------------------------------------------------------ */
/* Styling helpers                                                     */
/* ------------------------------------------------------------------ */

const control =
  "block w-full rounded-xl border border-line bg-surface-2 px-4 text-base text-ink placeholder:text-faint " +
  "transition-[border-color,box-shadow,background-color] duration-200 hover:border-line-strong " +
  "focus:border-primary focus:bg-surface focus:outline-none focus:ring-4 focus:ring-primary/30 focus-visible:outline-none " +
  "aria-[invalid=true]:border-red-600 aria-[invalid=true]:focus:ring-red-600/20";

function Field({
  id,
  label,
  required,
  hint,
  error,
  className,
  children,
}: {
  id: string;
  label: string;
  required?: boolean;
  hint?: string;
  error?: string;
  className?: string;
  children: ReactNode;
}) {
  return (
    <div className={cn("min-w-0", className)}>
      <label htmlFor={id} className="mb-2 flex items-baseline gap-1 text-[13px] font-semibold text-ink">
        {label}
        {required ? (
          <span aria-hidden="true" className="text-accent">
            *
          </span>
        ) : (
          <span className="text-xs font-normal text-faint">(optional)</span>
        )}
      </label>
      {children}
      {hint && !error && (
        <p id={`${id}-hint`} className="mt-1.5 text-xs text-faint">
          {hint}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} className="mt-1.5 text-xs font-medium text-red-700">
          {error}
        </p>
      )}
    </div>
  );
}

function describedBy(id: string, error?: string, hint?: string) {
  if (error) return `${id}-error`;
  if (hint) return `${id}-hint`;
  return undefined;
}

function SelectShell({ children }: { children: ReactNode }) {
  return (
    <div className="relative">
      {children}
      <ChevronDown
        aria-hidden="true"
        className="pointer-events-none absolute top-1/2 right-4 size-4 -translate-y-1/2 text-muted"
      />
    </div>
  );
}

function Stepper({
  id,
  label,
  value,
  min,
  max,
  error,
  onChange,
}: {
  id: string;
  label: string;
  value: string;
  min: number;
  max: number;
  error?: string;
  onChange: (v: string) => void;
}) {
  const n = Number(value);
  const safe = Number.isFinite(n) ? n : min;
  const step = (delta: number) => onChange(String(Math.min(max, Math.max(min, Math.round(safe) + delta))));
  const btn =
    "grid size-12 shrink-0 place-items-center text-muted transition-colors hover:text-primary disabled:opacity-30 disabled:hover:text-muted";

  return (
    <Field id={id} label={label} required={min > 0} error={error}>
      <div
        className={cn(
          "flex items-center overflow-hidden rounded-xl border bg-surface-2 transition-[border-color,box-shadow,background-color] focus-within:border-primary focus-within:bg-surface focus-within:ring-4 focus-within:ring-primary/30",
          error ? "border-red-600" : "border-line hover:border-line-strong",
        )}
      >
        <button
          type="button"
          className={btn}
          onClick={() => step(-1)}
          disabled={safe <= min}
          aria-label={`Fewer ${label.toLowerCase()}`}
        >
          <Minus className="size-4" aria-hidden="true" />
        </button>
        <input
          id={id}
          name={id}
          type="number"
          inputMode="numeric"
          pattern="[0-9]*"
          autoComplete="off"
          enterKeyHint="next"
          min={min}
          max={max}
          step={1}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy(id, error)}
          className="h-12 w-full min-w-0 [appearance:textfield] bg-transparent text-center text-base font-semibold text-ink tabular-nums focus:outline-none focus-visible:outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
        />
        <button
          type="button"
          className={btn}
          onClick={() => step(1)}
          disabled={safe >= max}
          aria-label={`More ${label.toLowerCase()}`}
        >
          <Plus className="size-4" aria-hidden="true" />
        </button>
      </div>
    </Field>
  );
}

/* ------------------------------------------------------------------ */
/* Component                                                           */
/* ------------------------------------------------------------------ */

type Sent = { channel: "whatsapp" | "email"; href: string } | null;

/**
 * Trip enquiry form. On submit it composes a WhatsApp message (primary) or a mailto: (secondary).
 * Prefills from props, else from `?package=` / `?destination=`. `destinations` / `packages` come from
 * the server page (CMS); contact details from `useSite()`.
 *
 * Lead saving: right before WhatsApp / the email app opens, the enquiry is also posted to the CMS inbox
 * (`saveEnquiry`, fire-and-forget, source "website") when NEXT_PUBLIC_CMS_API_URL is set. It never delays
 * or blocks the hand-off. A hidden honeypot field (`website`) is sent along for the API's spam check.
 */
export function EnquiryForm({
  destinations,
  packages,
  defaultPackage,
  defaultDestination,
}: {
  destinations: EnquiryDestination[];
  packages: EnquiryPackage[];
  defaultPackage?: string;
  defaultDestination?: string;
}) {
  const site = useSite();
  const uid = useId();
  const fid = (key: string) => `${uid}-${key}`;
  const reduce = useReducedMotion();

  // Query params are read on the client only; the server snapshot is empty so prerendering stays static.
  const search = useSyncExternalStore(subscribeToLocation, getSearch, getServerSearch);
  const initial = useMemo(() => {
    const q = new URLSearchParams(search);
    const pkg = findPackage(packages, defaultPackage) ?? findPackage(packages, q.get("package"));
    const destination =
      findDestinationSlug(destinations, defaultDestination) ??
      findDestinationSlug(destinations, q.get("destination")) ??
      pkg?.destinations[0] ??
      "";
    return { pkg: pkg?.slug ?? "", destination };
  }, [search, defaultPackage, defaultDestination, packages, destinations]);

  // `null` = untouched → falls back to the prefill, so no effect/state sync is needed.
  const [destinationEdit, setDestinationEdit] = useState<string | null>(null);
  const [packageEdit, setPackageEdit] = useState<string | null>(null);
  const destination = destinationEdit ?? initial.destination;
  const packageSlug = packageEdit ?? initial.pkg;
  const selectedPackage = packages.find((p) => p.slug === packageSlug);

  const [values, setValues] = useState<Values>({
    name: "",
    phone: "",
    email: "",
    month: "",
    adults: "2",
    children: "0",
    hotel: "",
    message: "",
  });
  // Honeypot: hidden from people (off-screen, not focusable); bots that fill every field get filtered by the API.
  const [honeypot, setHoneypot] = useState("");
  const set = <K extends keyof Values>(key: K, value: Values[K]) => setValues((v) => ({ ...v, [key]: value }));

  const [attempted, setAttempted] = useState(false);
  const [announcement, setAnnouncement] = useState("");
  const [sent, setSent] = useState<Sent>(null);
  const errors = attempted ? validate(values) : {};

  const successHeading = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    if (sent) successHeading.current?.focus();
  }, [sent]);

  const packageGroups = useMemo(
    () =>
      REGION_ORDER.map((region) => ({ region, items: packages.filter((p) => p.region === region) })).filter(
        (g) => g.items.length > 0,
      ),
    [packages],
  );
  const destinationGroups = useMemo(
    () =>
      REGION_ORDER.map((region) => ({ region, items: destinations.filter((d) => d.region === region) })).filter(
        (g) => g.items.length > 0,
      ),
    [destinations],
  );

  /** Validate; on failure announce and focus the first invalid field. */
  function check() {
    setAttempted(true);
    const errs = validate(values);
    const first = FIELD_ORDER.find((k) => errs[k]);
    if (!first) {
      setAnnouncement("");
      return true;
    }
    const count = Object.keys(errs).length;
    setAnnouncement(`Please check ${count === 1 ? "the highlighted field" : `${count} highlighted fields`}.`);
    document.getElementById(fid(first))?.focus();
    return false;
  }

  function onPackageChange(slug: string) {
    setPackageEdit(slug);
    // Helpful default: picking a package fills an empty destination with its first stop.
    const pkg = packages.find((p) => p.slug === slug);
    if (pkg && !destination) setDestinationEdit(pkg.destinations[0] ?? "");
  }

  const destinationName = destinations.find((d) => d.slug === destination)?.name;

  /** Fire-and-forget copy to the CMS inbox — called just before WhatsApp / the email app opens. */
  function save() {
    saveEnquiry({
      name: values.name.trim(),
      phone: values.phone.trim(),
      email: values.email.trim() || undefined,
      destination: destinationName,
      package: selectedPackage?.title,
      travelMonth: values.month || undefined,
      adults: Number(values.adults),
      children: Number(values.children || 0),
      hotelCategory: values.hotel || undefined,
      message: values.message.trim() || undefined,
      source: "website",
      website: honeypot,
    });
  }

  function sendWhatsApp(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!check()) return;
    const href = whatsappLink(
      site.whatsapp,
      composeMessage(values, site.name, destinationName, selectedPackage, "whatsapp"),
    );
    save();
    window.open(href, "_blank", "noopener,noreferrer");
    setSent({ channel: "whatsapp", href });
  }

  function sendEmail() {
    if (!check()) return;
    const subject = `Trip enquiry${destinationName ? ` — ${destinationName}` : ""} — ${values.name.trim()}`;
    const body = composeMessage(values, site.name, destinationName, selectedPackage, "email");
    const href = `mailto:${site.emails[0]}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    save();
    window.location.assign(href);
    setSent({ channel: "email", href });
  }

  const enter = reduce ? { opacity: 1 } : { opacity: 1, y: 0 };
  const from = reduce ? { opacity: 0 } : { opacity: 0, y: 16 };
  const transition = { duration: reduce ? 0.15 : 0.6, ease: [0.16, 1, 0.3, 1] as const };

  return (
    <div className="relative">
      <p role="status" aria-live="polite" className="sr-only">
        {announcement}
      </p>

      <AnimatePresence mode="wait" initial={false}>
        {sent ? (
          <motion.div
            key="sent"
            initial={from}
            animate={enter}
            exit={from}
            transition={transition}
            className="py-6 sm:py-10"
          >
            <span className="relative mb-6 grid size-16 place-items-center rounded-full bg-leaf/15 text-leaf">
              <span
                aria-hidden="true"
                className="absolute inset-0 rounded-full border border-leaf/40 motion-safe:animate-pulse-ring"
              />
              <CircleCheck className="size-8" aria-hidden="true" />
            </span>
            <h3
              ref={successHeading}
              tabIndex={-1}
              className="font-display text-2xl font-semibold text-ink focus:outline-none sm:text-3xl"
            >
              {sent.channel === "whatsapp" ? "WhatsApp opened" : "Your email is ready"}
            </h3>
            <p className="mt-3 max-w-md text-base leading-relaxed text-muted text-pretty">
              {sent.channel === "whatsapp"
                ? "Your trip details are waiting in WhatsApp — just press send there and we'll get back to you shortly."
                : "Your email app should open with everything filled in — press send and we'll get back to you shortly."}
            </p>
            <p className="mt-4 text-sm text-faint">
              {sent.channel === "whatsapp" ? "Nothing opened? " : "Email app didn't open? "}
              <a
                href={sent.href}
                {...(sent.channel === "whatsapp" ? { target: "_blank", rel: "noopener noreferrer" } : {})}
                className="font-medium text-accent underline-offset-4 hover:underline"
              >
                {sent.channel === "whatsapp" ? "Open WhatsApp again" : `Write to ${site.emails[0]}`}
              </a>{" "}
              or call{" "}
              <a href={`tel:${site.phones[0].raw}`} className="font-medium text-ink underline-offset-4 hover:underline">
                {site.phones[0].display}
              </a>
              .
            </p>
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <Button variant="outline" onClick={() => setSent(null)} className="w-full sm:w-auto">
                <ArrowLeft className="size-4" aria-hidden="true" />
                Edit my enquiry
              </Button>
              <Button
                variant="ghost"
                onClick={() => {
                  setValues((v) => ({ ...v, message: "" }));
                  setSent(null);
                }}
                className="w-full sm:w-auto"
              >
                Start a new enquiry
              </Button>
            </div>
          </motion.div>
        ) : (
          <motion.form
            key="form"
            initial={from}
            animate={enter}
            exit={from}
            transition={transition}
            onSubmit={sendWhatsApp}
            noValidate
            aria-label="Trip enquiry"
            className="grid grid-cols-1 gap-x-5 gap-y-6 sm:grid-cols-2"
          >
            {/* Honeypot (spam trap): off-screen and out of the tab order, so people never see or fill it. */}
            <div aria-hidden="true" className="absolute -left-[9999px] top-0 size-px overflow-hidden">
              <label htmlFor={fid("website")}>Website</label>
              <input
                id={fid("website")}
                name="website"
                type="text"
                tabIndex={-1}
                autoComplete="off"
                value={honeypot}
                onChange={(e) => setHoneypot(e.target.value)}
              />
            </div>

            <Field id={fid("name")} label="Full name" required error={errors.name}>
              <input
                id={fid("name")}
                name="name"
                type="text"
                autoComplete="name"
                autoCapitalize="words"
                enterKeyHint="next"
                required
                aria-required="true"
                aria-invalid={errors.name ? true : undefined}
                aria-describedby={describedBy(fid("name"), errors.name)}
                value={values.name}
                onChange={(e) => set("name", e.target.value)}
                placeholder="Your name"
                maxLength={80}
                className={cn(control, "h-12")}
              />
            </Field>

            <Field
              id={fid("phone")}
              label="Phone / WhatsApp"
              required
              hint="10-digit mobile, +91 optional"
              error={errors.phone}
            >
              <div className="relative">
                <Phone
                  aria-hidden="true"
                  className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-faint"
                />
                <input
                  id={fid("phone")}
                  name="phone"
                  type="tel"
                  inputMode="tel"
                  autoComplete="tel"
                  enterKeyHint="next"
                  required
                  aria-required="true"
                  aria-invalid={errors.phone ? true : undefined}
                  aria-describedby={describedBy(fid("phone"), errors.phone, "hint")}
                  value={values.phone}
                  onChange={(e) => set("phone", e.target.value)}
                  placeholder="+91 98765 43210"
                  maxLength={20}
                  className={cn(control, "h-12 pl-11")}
                />
              </div>
            </Field>

            <Field id={fid("email")} label="Email" error={errors.email} className="sm:col-span-2">
              <input
                id={fid("email")}
                name="email"
                type="email"
                inputMode="email"
                autoComplete="email"
                autoCapitalize="none"
                autoCorrect="off"
                spellCheck={false}
                enterKeyHint="next"
                aria-invalid={errors.email ? true : undefined}
                aria-describedby={describedBy(fid("email"), errors.email)}
                value={values.email}
                onChange={(e) => set("email", e.target.value)}
                placeholder="you@example.com"
                maxLength={120}
                className={cn(control, "h-12")}
              />
            </Field>

            <Field id={fid("destination")} label="Destination">
              <SelectShell>
                <select
                  id={fid("destination")}
                  name="destination"
                  value={destination}
                  onChange={(e) => setDestinationEdit(e.target.value)}
                  className={cn(control, "h-12 appearance-none pr-10")}
                >
                  <option value="">Not sure yet — suggest something</option>
                  {destinationGroups.map((g) => (
                    <optgroup key={g.region} label={regionLabels[g.region]}>
                      {g.items.map((d) => (
                        <option key={d.slug} value={d.slug}>
                          {d.name}
                        </option>
                      ))}
                    </optgroup>
                  ))}
                </select>
              </SelectShell>
            </Field>

            <Field id={fid("month")} label="Travel month">
              <SelectShell>
                <select
                  id={fid("month")}
                  name="month"
                  value={values.month}
                  onChange={(e) => set("month", e.target.value)}
                  className={cn(control, "h-12 appearance-none pr-10")}
                >
                  <option value="">Flexible / not decided</option>
                  {MONTHS.map((m) => (
                    <option key={m} value={m}>
                      {m}
                    </option>
                  ))}
                </select>
              </SelectShell>
            </Field>

            {packages.length > 0 && (
              <Field id={fid("package")} label="Package" className="sm:col-span-2">
                <SelectShell>
                  <select
                    id={fid("package")}
                    name="package"
                    value={packageSlug}
                    onChange={(e) => onPackageChange(e.target.value)}
                    className={cn(control, "h-12 appearance-none truncate pr-10")}
                  >
                    <option value="">No package — plan a custom trip</option>
                    {packageGroups.map((g) => (
                      <optgroup key={g.region} label={regionLabels[g.region]}>
                        {g.items.map((p) => (
                          <option key={p.slug} value={p.slug}>
                            {p.title} · {p.nights}N/{p.days}D
                          </option>
                        ))}
                      </optgroup>
                    ))}
                  </select>
                </SelectShell>
              </Field>
            )}

            <Stepper
              id={fid("adults")}
              label="Adults"
              value={values.adults}
              min={ADULTS.min}
              max={ADULTS.max}
              error={errors.adults}
              onChange={(v) => set("adults", v)}
            />
            <Stepper
              id={fid("children")}
              label="Children"
              value={values.children}
              min={CHILDREN.min}
              max={CHILDREN.max}
              error={errors.children}
              onChange={(v) => set("children", v)}
            />

            <fieldset className="min-w-0 sm:col-span-2">
              <legend className="mb-2 flex items-baseline gap-1 text-[13px] font-semibold text-ink">
                Hotel category <span className="text-xs font-normal text-faint">(optional)</span>
              </legend>
              <div className="flex flex-wrap gap-2">
                {HOTEL_OPTIONS.map((h) => (
                  <label
                    key={h}
                    className={cn(
                      "relative inline-flex min-h-11 cursor-pointer items-center rounded-full border px-4 text-sm font-medium transition-colors duration-200",
                      "has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary/50 has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-surface",
                      values.hotel === h
                        ? "border-primary bg-primary text-white shadow-[0_6px_16px_-8px_rgb(0_84_144/0.6)]"
                        : "border-line bg-surface text-muted hover:border-primary/40 hover:text-ink",
                    )}
                  >
                    <input
                      type="radio"
                      name="hotel"
                      value={h}
                      checked={values.hotel === h}
                      onChange={() => set("hotel", h)}
                      onClick={() => values.hotel === h && set("hotel", "")}
                      className="sr-only"
                    />
                    {h}
                  </label>
                ))}
              </div>
            </fieldset>

            <Field id={fid("message")} label="Anything else?" className="sm:col-span-2">
              <textarea
                id={fid("message")}
                name="message"
                rows={4}
                maxLength={1000}
                value={values.message}
                onChange={(e) => set("message", e.target.value)}
                placeholder="Dates, occasion (honeymoon, family, yatra), pickup point, special requests…"
                className={cn(control, "min-h-28 resize-y py-3 leading-relaxed")}
              />
            </Field>

            <div className="flex flex-col gap-3 pt-2 sm:col-span-2 sm:flex-row sm:items-center">
              <Button type="submit" size="lg" className="w-full sm:w-auto">
                <MessageCircle className="size-5" aria-hidden="true" />
                Send on WhatsApp
              </Button>
              <Button type="button" variant="outline" size="lg" onClick={sendEmail} className="w-full sm:w-auto">
                <Mail className="size-5" aria-hidden="true" />
                Send by email instead
              </Button>
            </div>
            <p className="-mt-2 text-xs leading-relaxed text-faint sm:col-span-2">
              {enquirySavingEnabled
                ? "We keep a copy of your enquiry so our team can follow up — it also opens in WhatsApp or your inbox."
                : "Nothing is stored on this website — your details go straight to our WhatsApp or inbox."}
              <span className="text-accent" aria-hidden="true">
                {" "}
                *
              </span>
              <span aria-hidden="true"> Required</span>
            </p>
          </motion.form>
        )}
      </AnimatePresence>
    </div>
  );
}
