"use client";

import { useEffect, useRef, type RefObject } from "react";
import Link from "next/link";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import { ArrowRight, Mail, MapPin, Phone, X } from "lucide-react";
import { navLinks, site } from "@/data/site";
import { cn, pad2, whatsappLink } from "@/lib/utils";
import { WhatsAppIcon } from "@/components/layout/WhatsAppFab";
import { Brand, PLAN_MSG, isActive } from "./NavBrand";

const EASE = [0.16, 1, 0.3, 1] as const;

/** Small descriptive label under each menu link. */
const LINK_NOTES: Record<string, string> = {
  "/": "Overview",
  "/destinations": "Kashmir · Ladakh · Jammu",
  "/packages": "Curated tour packages",
  "/about": "Our story & team",
  "/contact": "Call, write or visit",
};

/**
 * Full-screen light mobile menu. Loaded lazily by the Navbar (next/dynamic, ssr:false) the first
 * time the hamburger is hovered, focused, touched or clicked, so `motion` stays out of every
 * page's initial JS. Scroll lock, Esc, focus trap and focus return live in the Navbar so they work
 * instantly, even while this chunk is still loading; this component only moves focus in on open.
 *
 * It covers the sticky header (z-60 over z-50) and repeats it pixel-for-pixel in its top bar
 * (same height, gutters, brand position; the close button sits where the hamburger was), and the
 * panel itself only fades — so opening it causes no visible shift.
 */
export function MobileMenu({
  open,
  onClose,
  onReady,
  pathname,
  dialogRef,
}: {
  open: boolean;
  onClose: () => void;
  /** Called once on mount, so the Navbar can drop its loading placeholder. */
  onReady: () => void;
  pathname: string;
  dialogRef: RefObject<HTMLDivElement | null>;
}) {
  const reduce = useReducedMotion();
  const closeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    onReady();
  }, [onReady]);

  // Move focus into the dialog once it has mounted.
  useEffect(() => {
    if (!open) return;
    const t = window.setTimeout(() => closeRef.current?.focus({ preventScroll: true }), 30);
    return () => window.clearTimeout(t);
  }, [open]);

  const rise = (delay: number) =>
    reduce
      ? { initial: false as const }
      : {
          initial: { opacity: 0, y: 12 },
          animate: { opacity: 1, y: 0 },
          transition: { duration: 0.5, delay, ease: EASE },
        };

  return (
    <AnimatePresence>
      {open && (
        <motion.div
          ref={dialogRef}
          id="mobile-menu"
          role="dialog"
          aria-modal="true"
          aria-label="Site menu"
          data-lenis-prevent
          // Solid white (no backdrop blur); scrolls on short phones; only opacity/transform animate.
          className="fixed inset-0 z-[60] flex flex-col overflow-y-auto overscroll-contain bg-surface pt-[env(safe-area-inset-top)] lg:hidden"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: reduce ? 0.12 : 0.25, ease: EASE }}
        >
          {/* Soft logo-blue wash in the corner (static gradient, nothing to rasterise per frame). */}
          <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-x-0 top-0 h-80 bg-[radial-gradient(60%_100%_at_100%_0%,rgb(0_84_144/0.07),transparent)]"
          />

          {/* Mirrors the header bar exactly. */}
          <div className="relative shrink-0 border-b border-line">
            <div className="mx-auto flex h-[var(--header-h)] w-full max-w-7xl items-center justify-between gap-3 pl-[max(1rem,env(safe-area-inset-left))] pr-[max(1rem,env(safe-area-inset-right))] sm:pl-[max(1.5rem,env(safe-area-inset-left))] sm:pr-[max(1.5rem,env(safe-area-inset-right))]">
              <Brand onClick={onClose} />
              <button
                ref={closeRef}
                type="button"
                onClick={onClose}
                aria-label="Close menu"
                className="flex size-11 shrink-0 items-center justify-center rounded-full border border-line text-ink transition-colors duration-200 hover:border-line-strong hover:bg-surface-2"
              >
                <X aria-hidden="true" className="size-5" />
              </button>
            </div>
          </div>

          <div className="relative mx-auto flex w-full max-w-7xl flex-1 flex-col pb-[calc(1.5rem+env(safe-area-inset-bottom))] pl-[max(1rem,env(safe-area-inset-left))] pr-[max(1rem,env(safe-area-inset-right))] pt-6 sm:pl-[max(1.5rem,env(safe-area-inset-left))] sm:pr-[max(1.5rem,env(safe-area-inset-right))] sm:pt-8">
            <p className="label mb-2">Menu</p>
            <ul>
              {navLinks.map((link, i) => {
                const active = isActive(pathname, link.href);
                return (
                  <motion.li key={link.href} className="border-b border-line" {...rise(0.04 + i * 0.04)}>
                    <Link
                      href={link.href}
                      onClick={onClose}
                      aria-current={active ? "page" : undefined}
                      className="group flex min-h-16 items-center gap-4 py-2.5"
                    >
                      <span className={cn("label w-6 shrink-0", active && "text-primary")}>{pad2(i + 1)}</span>
                      <span className="min-w-0 flex-1">
                        <span
                          className={cn(
                            "block font-display text-[1.75rem] font-medium leading-tight transition-colors duration-200 sm:text-[2rem]",
                            active ? "text-primary" : "text-ink group-hover:text-primary",
                          )}
                        >
                          {link.label}
                        </span>
                        <span className="mt-0.5 block truncate text-[13px] text-muted">{LINK_NOTES[link.href]}</span>
                      </span>
                      {active ? (
                        <span className="label shrink-0 text-primary">Current</span>
                      ) : (
                        <ArrowRight
                          aria-hidden="true"
                          className="size-5 shrink-0 text-faint transition-[color,transform] duration-200 group-hover:translate-x-0.5 group-hover:text-primary"
                        />
                      )}
                    </Link>
                  </motion.li>
                );
              })}
            </ul>

            <motion.div
              className="mt-8 grid gap-5 rounded-2xl border border-line bg-night p-5 sm:grid-cols-2 sm:gap-6 sm:p-6"
              {...rise(0.26)}
            >
              <div>
                <p className="label mb-1">Call</p>
                {site.phones.map((p) => (
                  <a
                    key={p.raw}
                    href={`tel:${p.raw}`}
                    className="flex min-h-11 items-center gap-2.5 text-base font-semibold tabular-nums text-ink transition-colors hover:text-primary"
                  >
                    <Phone aria-hidden="true" className="size-4 shrink-0 text-primary" />
                    {p.display}
                  </a>
                ))}
              </div>
              <div className="min-w-0">
                <p className="label mb-1">Email</p>
                {site.emails.map((email) => (
                  <a
                    key={email}
                    href={`mailto:${email}`}
                    className="flex min-h-11 items-center gap-2.5 text-sm text-muted transition-colors [overflow-wrap:anywhere] hover:text-primary"
                  >
                    <Mail aria-hidden="true" className="size-4 shrink-0 text-primary" />
                    {email}
                  </a>
                ))}
              </div>
              <div className="sm:col-span-2">
                <p className="label mb-2">Visit</p>
                <address className="flex items-start gap-2.5 text-sm not-italic leading-relaxed text-muted">
                  <MapPin aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-primary" />
                  <span>
                    {site.addresses[0].line1}, {site.addresses[0].line2}
                  </span>
                </address>
              </div>
            </motion.div>

            <motion.div className="mt-auto pt-6" {...rise(0.32)}>
              <a
                href={whatsappLink(PLAN_MSG)}
                target="_blank"
                rel="noopener noreferrer"
                className="flex h-12 w-full items-center justify-center gap-2.5 rounded-full bg-primary px-6 text-[15px] font-semibold text-white shadow-[0_6px_20px_-8px_rgb(0_84_144/0.55)] transition-[background-color,transform] duration-300 ease-out-expo hover:bg-primary-bright active:scale-[0.98]"
              >
                <WhatsAppIcon className="size-5" />
                Plan your trip on WhatsApp
                <span className="sr-only">(opens in a new tab)</span>
              </a>
            </motion.div>
          </div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
