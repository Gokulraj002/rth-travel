"use client";

import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import { cn } from "@/lib/utils";

/* ------------------------------------------------------------------ */
/* Local time (IST) — external store so server + hydration render null */
/* One shared 30 s ticker for every instance; paused while the tab is  */
/* hidden and refreshed immediately when it becomes visible again.     */
/* ------------------------------------------------------------------ */

const timeFormatter = new Intl.DateTimeFormat("en-US", {
  timeZone: "Asia/Kolkata",
  hour: "numeric",
  minute: "2-digit",
  hour12: true,
});

const clockListeners = new Set<() => void>();
let clockTimer: number | undefined;

function emitClock() {
  clockListeners.forEach((l) => l());
}

function startClock() {
  if (clockTimer === undefined) clockTimer = window.setInterval(emitClock, 30_000);
}

function stopClock() {
  if (clockTimer !== undefined) window.clearInterval(clockTimer);
  clockTimer = undefined;
}

function onClockVisibility() {
  if (document.visibilityState === "visible") {
    emitClock(); // catch up after being hidden
    startClock();
  } else {
    stopClock();
  }
}

function subscribeClock(onChange: () => void) {
  clockListeners.add(onChange);
  if (clockListeners.size === 1) {
    document.addEventListener("visibilitychange", onClockVisibility);
    if (document.visibilityState === "visible") startClock();
  }
  return () => {
    clockListeners.delete(onChange);
    if (clockListeners.size === 0) {
      document.removeEventListener("visibilitychange", onClockVisibility);
      stopClock();
    }
  };
}
// Strings compare by value, so the snapshot is stable within a minute.
const getClockSnapshot = () => timeFormatter.format(new Date());
const getClockServerSnapshot = () => null;

function useIstTime() {
  return useSyncExternalStore(subscribeClock, getClockSnapshot, getClockServerSnapshot);
}

/* ------------------------------------------------------------------ */
/* Forecast — Open-Meteo, fetched once per lat/lng for the session     */
/* ------------------------------------------------------------------ */

interface Forecast {
  temp: number;
  label: string;
}

/** WMO weather interpretation codes → short labels. */
function weatherLabel(code: number): string {
  if (code === 0) return "Clear";
  if (code === 1) return "Mostly clear";
  if (code === 2) return "Partly cloudy";
  if (code === 3) return "Cloudy";
  if (code === 45 || code === 48) return "Fog";
  if (code >= 51 && code <= 57) return "Drizzle";
  if (code >= 61 && code <= 67) return "Rain";
  if (code >= 71 && code <= 77) return "Snow";
  if (code >= 80 && code <= 82) return "Showers";
  if (code === 85 || code === 86) return "Snow showers";
  if (code >= 95) return "Storm";
  return "—";
}

interface CacheEntry {
  promise: Promise<Forecast | null>;
  controller: AbortController;
  /** Mounted instances waiting on this request. */
  users: number;
  settled: boolean;
  value?: Forecast | null;
}

/**
 * Module cache: one request per location, shared by every instance. An in-flight request is
 * aborted (and forgotten) only when its last waiting instance unmounts; failures are forgotten
 * too so a later mount can retry. Successful results live for the session.
 */
const forecastCache = new Map<string, CacheEntry>();

const forecastKey = (lat: number, lng: number) => `${lat.toFixed(3)},${lng.toFixed(3)}`;

function acquireForecast(lat: number, lng: number, key: string): CacheEntry {
  let entry = forecastCache.get(key);
  if (!entry) {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 8000);
    const url =
      `https://api.open-meteo.com/v1/forecast?latitude=${lat.toFixed(4)}&longitude=${lng.toFixed(4)}` +
      `&current=temperature_2m,weather_code&timezone=Asia%2FKolkata`;
    const created: CacheEntry = { controller, users: 0, settled: false, promise: Promise.resolve(null) };
    created.promise = fetch(url, { signal: controller.signal })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((json: { current?: { temperature_2m?: number; weather_code?: number } }) => {
        const t = json.current?.temperature_2m;
        const c = json.current?.weather_code;
        if (typeof t !== "number" || typeof c !== "number") throw new Error("bad payload");
        return { temp: Math.round(t), label: weatherLabel(c) };
      })
      .catch(() => {
        // Allow a retry on a later mount (only if this entry is still the cached one).
        if (forecastCache.get(key) === created) forecastCache.delete(key);
        return null;
      })
      .then((value) => {
        window.clearTimeout(timeout);
        created.settled = true;
        created.value = value;
        return value;
      });
    forecastCache.set(key, created);
    entry = created;
  }
  entry.users += 1;
  return entry;
}

function releaseForecast(key: string, entry: CacheEntry) {
  entry.users -= 1;
  if (entry.users <= 0 && !entry.settled) {
    entry.controller.abort();
    if (forecastCache.get(key) === entry) forecastCache.delete(key);
  }
}

/** undefined = loading, null = failed (hidden). Fetches only once `enabled` (i.e. visible). */
function useForecast(lat: number, lng: number, enabled: boolean): Forecast | null | undefined {
  const key = forecastKey(lat, lng);
  const [result, setResult] = useState<{ key: string; value: Forecast | null } | null>(null);

  useEffect(() => {
    if (!enabled) return;
    const entry = acquireForecast(lat, lng, key);
    let alive = true;
    entry.promise.then((value) => {
      if (alive) setResult({ key, value });
    });
    return () => {
      alive = false;
      releaseForecast(key, entry);
    };
  }, [enabled, key, lat, lng]);

  if (result?.key === key) return result.value;
  // Already fetched this session (e.g. journey panel remounting) → show it straight away.
  const cached = forecastCache.get(key);
  return cached?.settled && cached.value ? cached.value : undefined;
}

/** Latches true the first time the element intersects the viewport. */
function useSeenOnce<T extends Element>() {
  const ref = useRef<T>(null);
  const [seen, setSeen] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (seen || !el) return;
    if (typeof IntersectionObserver === "undefined") {
      // Very old browsers: fetch after paint rather than never.
      const id = window.setTimeout(() => setSeen(true), 0);
      return () => window.clearTimeout(id);
    }
    const io = new IntersectionObserver((entries) => {
      if (entries.some((e) => e.isIntersecting)) {
        setSeen(true);
        io.disconnect();
      }
    });
    io.observe(el);
    return () => io.disconnect();
  }, [seen]);

  return [ref, seen] as const;
}

/* ------------------------------------------------------------------ */

function Skeleton({ compact }: { compact?: boolean }) {
  return (
    <span
      aria-hidden
      className={cn(
        // bg-ink tint reads on both the light pages and the dark journey panel.
        "inline-block animate-pulse rounded bg-ink/10 align-middle motion-reduce:animate-none",
        compact ? "h-3.5 w-16" : "h-4 w-20",
      )}
    />
  );
}

/** Small logo-leaf "live" dot; pulses (transform/opacity only) once the value is live. */
function LiveDot({ live, pulse }: { live: boolean; pulse?: boolean }) {
  return (
    <span aria-hidden className="relative inline-flex size-1.5 shrink-0">
      {live && pulse && (
        <span className="absolute inset-0 rounded-full bg-leaf/70 animate-pulse-ring motion-reduce:hidden" />
      )}
      <span className={cn("relative size-1.5 rounded-full transition-colors", live ? "bg-leaf" : "bg-faint/50")} />
    </span>
  );
}

/**
 * LOCAL TIME (IST) + FORECAST for a lat/lng, laid out as label over tabular value.
 * Time renders only after mount; the forecast is requested only once the block is
 * actually on screen, shows a skeleton while loading and disappears quietly on failure.
 */
export function LiveConditions({
  lat,
  lng,
  compact,
  className,
}: {
  lat: number;
  lng: number;
  compact?: boolean;
  className?: string;
}) {
  const [ref, seen] = useSeenOnce<HTMLDListElement>();
  const time = useIstTime();
  const forecast = useForecast(lat, lng, seen);

  const valueCls = cn(
    "tabular-nums text-ink",
    compact ? "mt-1 text-sm" : "mt-1.5 text-[15px] leading-snug",
  );

  return (
    <dl ref={ref} className={cn("grid grid-cols-2", compact ? "gap-x-4 gap-y-2" : "gap-x-6 gap-y-4", className)}>
      <div className="min-w-0">
        <dt className="label flex items-center gap-1.5">
          <LiveDot live={!!time} pulse />
          Local time
        </dt>
        <dd className={valueCls}>
          {time ? (
            <>
              {time} <span className="text-faint">IST</span>
            </>
          ) : (
            <>
              <Skeleton compact={compact} />
              <span className="sr-only">Loading local time</span>
            </>
          )}
        </dd>
      </div>
      {forecast !== null && (
        <div className="min-w-0" aria-live="polite">
          <dt className="label flex items-center gap-1.5">
            <LiveDot live={!!forecast} />
            Forecast
          </dt>
          <dd className={valueCls}>
            {forecast ? (
              <>
                {forecast.temp}° <span className="text-faint">·</span> {forecast.label}
              </>
            ) : (
              <>
                <Skeleton compact={compact} />
                <span className="sr-only">Loading forecast</span>
              </>
            )}
          </dd>
        </div>
      )}
    </dl>
  );
}

export default LiveConditions;
