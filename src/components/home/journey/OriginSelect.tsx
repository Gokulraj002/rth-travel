"use client";

import { useId } from "react";
import { ChevronDown, Plane } from "lucide-react";
import { originCities } from "@/data/origins";
import { useJourney } from "@/lib/store";
import { cn } from "@/lib/utils";

/**
 * Chip "FLYING FROM Delhi DEL ▾" bound to the journey origin.
 * A native <select> sits invisibly over the whole 44px chip (one big tap target), so it
 * stays fully keyboard / screen-reader accessible and uses the OS picker on mobile.
 */
export function OriginSelect({ className }: { className?: string }) {
  const id = useId();
  const origin = useJourney((s) => s.origin);
  const setOrigin = useJourney((s) => s.setOrigin);

  return (
    <div
      className={cn(
        // Solid translucent fill on phones (no backdrop blur over the live WebGL canvas); glass from md.
        "pointer-events-auto relative inline-flex h-11 items-center gap-3 rounded-full border border-line bg-night/85 pl-4 pr-3 md:glass",
        "focus-within:ring-2 focus-within:ring-accent",
        className,
      )}
    >
      <Plane aria-hidden className="size-3.5 shrink-0 -rotate-45 text-accent" />
      <label htmlFor={id} className="label whitespace-nowrap">
        Flying from
      </label>
      <span className="inline-flex items-center gap-1.5">
        <span aria-hidden className="flex items-baseline gap-1.5 whitespace-nowrap text-sm font-semibold text-ink">
          {origin.city}
          <span className="text-xs font-semibold tracking-wider text-accent">{origin.code}</span>
        </span>
        <ChevronDown aria-hidden className="size-3.5 text-muted" />
        <select
          id={id}
          value={origin.code}
          onChange={(e) => setOrigin(e.target.value)}
          className="absolute inset-0 h-full w-full cursor-pointer appearance-none rounded-full text-base opacity-0"
        >
          {originCities.map((c) => (
            <option key={c.code} value={c.code} className="bg-night text-ink">
              {c.city} ({c.code})
            </option>
          ))}
        </select>
      </span>
    </div>
  );
}
