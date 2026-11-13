"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import dynamic from "next/dynamic";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Menu, Phone } from "lucide-react";
import { navLinks } from "@/data/site";
import { cn, whatsappLink } from "@/lib/utils";
import { ButtonLink } from "@/components/ui/Button";
import { Brand, PLAN_MSG, isActive } from "./NavBrand";
import { useSite } from "./SiteProvider";

/**
 * The full-screen mobile menu (and the `motion` library it animates with) is a separate chunk,
 * fetched on first hover / focus / touch of the hamburger and rendered only once the menu is opened.
 */
const MobileMenu = dynamic(() => import("./MobileMenu").then((m) => m.MobileMenu), { ssr: false });
const prefetchMenu = () => {
  void import("./MobileMenu");
};

/**
 * Light, always-visible sticky header. It sits in the normal document flow (`sticky`, not `fixed`),
 * so pages need no top padding to clear it; its height is `--header-h` (64px / 76px on lg+, globals.css).
 */
export function Navbar() {
  // usePathname() is request-time data under Cache Components: it lives here, inside the <Suspense>
  // boundary set in the root layout, so pages whose static shell must be regenerated at runtime
  // (e.g. /packages/[slug] after a CMS edit) can still prerender — they show <NavbarFallback/> meanwhile.
  return <NavbarView pathname={usePathname()} />;
}

/** Same header without route awareness (no active link, light theme) — the Suspense fallback. */
export function NavbarFallback() {
  return <NavbarView pathname="" />;
}

function NavbarView({ pathname }: { pathname: string }) {
  const site = useSite();
  const phone = site.phones[0];
  const isHome = pathname === "/";

  const [open, setOpen] = useState(false);
  // `menuMounted`: the lazy menu has been requested (first open). `menuReady`: its chunk has rendered.
  const [menuMounted, setMenuMounted] = useState(false);
  const [menuReady, setMenuReady] = useState(false);
  const onMenuReady = useCallback(() => setMenuReady(true), []);
  const [scrolled, setScrolled] = useState(false);

  // Close the menu on route change (state adjustment during render — no effect needed).
  const [lastPath, setLastPath] = useState(pathname);
  if (lastPath !== pathname) {
    setLastPath(pathname);
    setOpen(false);
  }

  const toggleRef = useRef<HTMLButtonElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const wasOpen = useRef(false);

  // Passive, rAF-throttled scroll check; setState only when the 8px threshold flips.
  // (Lenis drives the native window scroll too, so this covers both.)
  useEffect(() => {
    let frame = 0;
    let current = false;
    const update = () => {
      frame = 0;
      const next = window.scrollY > 8;
      if (next !== current) {
        current = next;
        setScrolled(next);
      }
    };
    const onScroll = () => {
      if (!frame) frame = requestAnimationFrame(update);
    };
    update();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => {
      window.removeEventListener("scroll", onScroll);
      cancelAnimationFrame(frame);
    };
  }, []);

  const close = useCallback(() => setOpen(false), []);

  // Mobile menu: scroll lock, Esc to close, focus trap, focus restore (focus-in happens in MobileMenu).
  useEffect(() => {
    if (!open) {
      if (wasOpen.current) {
        wasOpen.current = false;
        toggleRef.current?.focus({ preventScroll: true });
      }
      return;
    }
    wasOpen.current = true;

    const html = document.documentElement;
    const prevOverflow = html.style.overflow;
    const prevPadding = html.style.paddingRight;
    // Compensate for a disappearing desktop-style scrollbar so nothing shifts sideways.
    const scrollbar = window.innerWidth - html.clientWidth;
    if (scrollbar > 0) html.style.paddingRight = `${scrollbar}px`;
    html.style.overflow = "hidden";
    window.__lenis?.stop();

    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        e.preventDefault();
        setOpen(false);
        return;
      }
      if (e.key !== "Tab" || !dialogRef.current) return;
      const items = Array.from(
        dialogRef.current.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'),
      ).filter((el) => el.offsetParent !== null || el === document.activeElement);
      if (items.length === 0) return;
      const first = items[0];
      const last = items[items.length - 1];
      const active = document.activeElement;
      if (e.shiftKey && (active === first || !dialogRef.current.contains(active))) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && (active === last || !dialogRef.current.contains(active))) {
        e.preventDefault();
        first.focus();
      }
    };
    document.addEventListener("keydown", onKey);

    // Close if the viewport grows to the desktop layout while open.
    const mq = window.matchMedia("(min-width: 1024px)");
    const onMq = () => mq.matches && setOpen(false);
    mq.addEventListener("change", onMq);

    return () => {
      document.removeEventListener("keydown", onKey);
      mq.removeEventListener("change", onMq);
      html.style.overflow = prevOverflow;
      html.style.paddingRight = prevPadding;
      window.__lenis?.start();
    };
  }, [open]);

  return (
    <>
      <a
        href="#main"
        className="fixed left-4 top-3 z-[70] -translate-y-24 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lift transition-transform duration-300 focus:translate-y-0 focus-visible:translate-y-0"
      >
        Skip to content
      </a>

      <header
        className={cn(
          // In flow + sticky. Solid below md (no backdrop blur on mobile GPUs), frosted on md+.
          "sticky top-0 z-50 border-b border-line pt-[env(safe-area-inset-top)] transition-shadow duration-300 md:backdrop-blur-md md:backdrop-saturate-150",
          // Home is the dark immersive page → navy header there; every other page gets the white header.
          isHome ? "theme-dark bg-night" : "bg-surface md:bg-surface/90",
          scrolled && "shadow-soft",
        )}
      >
        <nav
          aria-label="Primary"
          className="mx-auto flex h-[var(--header-h)] w-full max-w-7xl items-center justify-between gap-3 pl-[max(1rem,env(safe-area-inset-left))] pr-[max(1rem,env(safe-area-inset-right))] sm:pl-[max(1.5rem,env(safe-area-inset-left))] sm:pr-[max(1.5rem,env(safe-area-inset-right))] lg:gap-6 lg:px-8"
        >
          <Brand />

          <ul className="hidden items-center lg:flex xl:gap-1">
            {navLinks.map((link) => {
              const active = isActive(pathname, link.href);
              return (
                <li key={link.href}>
                  <Link
                    href={link.href}
                    aria-current={active ? "page" : undefined}
                    className={cn(
                      "group relative flex h-11 items-center px-3 text-sm font-medium transition-colors duration-200 xl:px-4 xl:text-[15px]",
                      active ? "text-ink" : "text-muted hover:text-ink",
                    )}
                  >
                    {link.label}
                    <span
                      aria-hidden="true"
                      className={cn(
                        "absolute inset-x-3 bottom-1 h-0.5 rounded-full transition-transform duration-300 ease-out-expo xl:inset-x-4",
                        active ? "scale-x-100 bg-primary" : "scale-x-0 bg-line-strong group-hover:scale-x-100",
                      )}
                    />
                  </Link>
                </li>
              );
            })}
          </ul>

          <div className="flex shrink-0 items-center gap-2 lg:gap-3">
            <a
              href={`tel:${phone.raw}`}
              aria-label={`Call ${phone.display}`}
              className="group flex size-11 items-center justify-center rounded-full border border-line text-ink transition-colors duration-200 hover:border-line-strong hover:bg-surface-2 lg:size-auto lg:h-11 lg:gap-2 lg:border-0 lg:px-2 lg:hover:bg-transparent"
            >
              <span className="flex items-center justify-center lg:size-8 lg:rounded-full lg:bg-primary/10 lg:text-primary">
                <Phone aria-hidden="true" className="size-4" />
              </span>
              <span className="hidden whitespace-nowrap text-sm font-semibold tabular-nums transition-colors duration-200 group-hover:text-primary lg:inline">
                {phone.display}
              </span>
            </a>
            <ButtonLink href={whatsappLink(site.whatsapp, PLAN_MSG)} size="md" className="hidden sm:inline-flex">
              Plan a trip
              <span className="sr-only">(opens WhatsApp in a new tab)</span>
            </ButtonLink>
            <button
              ref={toggleRef}
              type="button"
              onClick={() => {
                setMenuMounted(true);
                setOpen(true);
              }}
              onPointerEnter={prefetchMenu}
              onTouchStart={prefetchMenu}
              onFocus={prefetchMenu}
              aria-label="Open menu"
              aria-haspopup="dialog"
              aria-expanded={open}
              aria-controls="mobile-menu"
              className="flex size-11 items-center justify-center rounded-full border border-line text-ink transition-colors duration-200 hover:border-line-strong hover:bg-surface-2 lg:hidden"
            >
              <Menu aria-hidden="true" className="size-5" />
            </button>
          </div>
        </nav>
      </header>

      {/* Instant white cover while the menu chunk loads on the very first open (CSS fade-in only). */}
      {open && !menuReady && (
        <div
          aria-hidden="true"
          className="fixed inset-0 z-[60] bg-surface transition-opacity duration-200 starting:opacity-0 lg:hidden"
        />
      )}
      {menuMounted && (
        <MobileMenu open={open} onClose={close} onReady={onMenuReady} pathname={pathname} dialogRef={dialogRef} />
      )}
    </>
  );
}
