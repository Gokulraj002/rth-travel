import { BedDouble } from "lucide-react";
import type { ItineraryDay } from "@/types";
import { cn, pad2 } from "@/lib/utils";
import styles from "./ItineraryTimeline.module.css";

/**
 * Day-by-day vertical timeline. Server component — no client JS.
 * A solid primary progress line fills as the list scrolls through the viewport,
 * driven by a CSS scroll-driven animation (`animation-timeline: view()`), so nothing re-renders on scroll.
 * Browsers without scroll timelines, and prefers-reduced-motion, get a fully drawn line and static days.
 */
export function ItineraryTimeline({ days, className }: { days: ItineraryDay[]; className?: string }) {
  if (days.length === 0) return null;

  return (
    <ol className={cn("relative", className)}>
      {/* Track + progress fill, aligned to the centre of the day markers */}
      <span aria-hidden className="absolute bottom-6 left-[1.375rem] top-6 w-px -translate-x-1/2 bg-line" />
      <span
        aria-hidden
        className={cn(
          styles.fill,
          "absolute bottom-6 left-[1.375rem] top-6 w-0.5 -translate-x-1/2 rounded-full bg-primary",
        )}
      />

      {days.map((d) => (
        <li
          key={d.day}
          className={cn(styles.day, "relative grid grid-cols-[2.75rem_minmax(0,1fr)] gap-4 pb-10 last:pb-0 sm:gap-6")}
        >
          <span
            className="relative z-10 grid size-11 place-items-center rounded-full bg-primary text-[13px] font-semibold tabular-nums text-white shadow-[0_0_0_5px_var(--night)]"
            aria-hidden
          >
            {pad2(d.day)}
          </span>

          <div className="min-w-0 pt-1">
            <p className="label text-accent" aria-hidden>
              Day {pad2(d.day)}
            </p>
            <h3 className="mt-1 font-display text-lg font-semibold leading-snug text-ink text-balance break-words sm:text-xl">
              <span className="sr-only">Day {d.day}: </span>
              {d.title}
            </h3>
            <p className="mt-2.5 text-[15px] leading-relaxed text-muted text-pretty sm:text-base">{d.description}</p>
            {d.stay && (
              <p className="mt-4 inline-flex max-w-full items-center gap-2 rounded-full border border-line bg-surface px-3 py-1.5 text-xs text-ink shadow-soft">
                <BedDouble className="size-3.5 shrink-0 text-accent" aria-hidden />
                <span className="font-semibold uppercase tracking-[0.12em] text-faint">Overnight</span>
                <span className="truncate font-medium">{d.stay}</span>
              </p>
            )}
          </div>
        </li>
      ))}
    </ol>
  );
}
