"use client";

import { useEffect, useState } from "react";
import { MessageCircle } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * Fixed bottom "Enquire on WhatsApp" bar for phones/tablets (< lg), shown until the full booking card
 * (`#${targetId}`) scrolls into view — after that the card itself (and the CTA band / footer) take over,
 * so the bar never covers the bottom of the page.
 *
 * Layout: a white bar (top hairline + soft shadow), CTA left-aligned with ~80px right padding so the global
 * floating WhatsApp button (right-4, 56px) sits in the gap instead of on top of the CTA.
 * Bottom padding respects env(safe-area-inset-bottom).
 * One IntersectionObserver, no scroll listener; visible in the server HTML (the card starts far below).
 */
export function MobileEnquiryBar({ href, targetId, priceLabel }: { href: string; targetId: string; priceLabel: string }) {
  const [visible, setVisible] = useState(true);

  useEffect(() => {
    const target = document.getElementById(targetId);
    if (!target || typeof IntersectionObserver === "undefined") return;
    const io = new IntersectionObserver(
      ([entry]) => {
        // Hide while the card is on screen or once it has been scrolled past (it's above the viewport).
        const reachedCard = entry.isIntersecting || entry.boundingClientRect.top < 0;
        setVisible(!reachedCard);
      },
      { rootMargin: "0px 0px -15% 0px" },
    );
    io.observe(target);
    return () => io.disconnect();
  }, [targetId]);

  return (
    <div
      inert={!visible}
      className={cn(
        "fixed inset-x-0 bottom-0 z-30 lg:hidden",
        "border-t border-line bg-surface pl-4 pr-20 pt-3 shadow-[0_-8px_24px_-12px_rgb(0_48_96/0.18)] sm:pl-6 sm:pr-24",
        "pb-[calc(0.75rem+env(safe-area-inset-bottom))]",
        "transition-[transform,opacity] duration-500 ease-out-expo",
        visible ? "translate-y-0 opacity-100" : "pointer-events-none translate-y-full opacity-0",
      )}
    >
      <a
        href={href}
        target="_blank"
        rel="noopener noreferrer"
        className="flex h-12 w-full max-w-md items-center gap-3 rounded-full bg-primary pl-5 pr-4 text-white shadow-[0_6px_20px_-8px_rgb(0_84_144/0.55)] transition-colors duration-300 hover:bg-primary-bright active:scale-[0.98]"
      >
        <MessageCircle className="size-5 shrink-0" aria-hidden />
        <span className="min-w-0 flex-1 truncate text-[15px] font-semibold">Enquire on WhatsApp</span>
        <span className="shrink-0 text-[13px] font-medium tabular-nums text-white max-[409px]:hidden">
          {priceLabel}
        </span>
      </a>
    </div>
  );
}
