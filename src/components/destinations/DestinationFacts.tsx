import type { Destination } from "@/types";
import { LiveConditions } from "@/components/destinations/LiveConditions";
import { cn, formatCoords } from "@/lib/utils";

const altitudeFormat = new Intl.NumberFormat("en-IN");

/*
 * Hairline borders per cell index. Phones/tablets: 2 × 2 facts + full-width live row.
 * lg: one row of five cells.
 */
const CELL_BORDERS = [
  "",
  "border-l",
  "border-t lg:border-t-0 lg:border-l",
  "border-t border-l lg:border-t-0",
];

/**
 * Facts strip: altitude, best time, distance, coordinates + live IST time & weather.
 * Two columns from 360px up so it stays a compact strip on phones. A solid white card with
 * `shadow-lift` (no backdrop blur) — the detail page overlaps it slightly onto the hero's bottom edge.
 */
export function DestinationFacts({ destination }: { destination: Destination }) {
  const { altitudeM, bestTime, distanceNote, geo } = destination;

  const facts = [
    { label: "Altitude", value: `${altitudeFormat.format(altitudeM)} m` },
    { label: "Best time", value: bestTime },
    { label: "Distance", value: distanceNote },
    { label: "Coordinates", value: formatCoords(geo.lat, geo.lng), mono: true },
  ];

  return (
    <div className="overflow-hidden rounded-2xl border border-line bg-surface shadow-lift">
      <div className="grid grid-cols-2 lg:grid-cols-[0.8fr_1.3fr_1.3fr_1fr_1.15fr]">
        {facts.map((fact, i) => (
          <dl key={fact.label} className={cn("flex min-w-0 flex-col gap-1.5 border-line p-4 sm:gap-2 sm:p-5 lg:p-6", CELL_BORDERS[i])}>
            <dt className="label">{fact.label}</dt>
            <dd
              className={
                fact.mono
                  ? "text-sm font-medium tracking-wide break-words text-ink tabular-nums"
                  : "text-sm leading-snug font-semibold break-words text-ink text-pretty sm:text-[15px]"
              }
            >
              {fact.value}
            </dd>
          </dl>
        ))}
        <div className="col-span-2 flex min-w-0 flex-col justify-center border-t border-line bg-surface-2/60 p-4 sm:p-5 lg:col-span-1 lg:p-6 lg:border-t-0 lg:border-l">
          <LiveConditions lat={geo.lat} lng={geo.lng} compact />
        </div>
      </div>
    </div>
  );
}
