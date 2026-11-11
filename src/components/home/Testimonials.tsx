"use client";

import { useEffect, useRef } from "react";
import type { Testimonial } from "@/types";
import { testimonials } from "@/data/content";
import { Container, Section, SectionHeading } from "@/components/ui/primitives";
import { Reveal } from "@/components/ui/Reveal";
import { cn } from "@/lib/utils";

/** Minimum cards per marquee copy so a track is always wider than the viewport. */
const MIN_PER_TRACK = 5;

function fill(list: Testimonial[]) {
  if (list.length === 0) return list;
  const out: Testimonial[] = [];
  while (out.length < MIN_PER_TRACK) out.push(...list);
  return out;
}

/** Text stars (one node instead of five SVGs per card — this section repeats cards for the marquee). */
function Stars({ rating }: { rating: number }) {
  const r = Math.max(0, Math.min(5, Math.round(rating)));
  return (
    <p role="img" aria-label={`${r} out of 5`} className="text-sm leading-none tracking-[0.2em]">
      <span className="text-leaf">{"★".repeat(r)}</span>
      <span className="text-line-strong">{"★".repeat(5 - r)}</span>
    </p>
  );
}

function Card({ t, className }: { t: Testimonial; className?: string }) {
  return (
    <figure
      className={cn(
        // Solid surface, not `glass`: these cards move constantly and sit on a plain background,
        // so a backdrop blur would cost a re-filter every frame for no visible gain.
        "relative flex shrink-0 flex-col rounded-2xl border border-line bg-surface p-6 shadow-soft inset-shadow-[0_1px_0_rgb(255_255_255/0.05)] transition-colors duration-500 hover:border-line-strong",
        className,
      )}
    >
      <span aria-hidden className="block h-7 font-display text-5xl font-semibold leading-none text-accent/40 select-none">
        &ldquo;
      </span>
      <blockquote className="mt-3 flex-1 text-[15px] leading-relaxed text-ink text-pretty">
        {t.quote}
      </blockquote>
      <figcaption className="mt-5 border-t border-line pt-4">
        <Stars rating={t.rating} />
        <p className="mt-3 text-[15px] font-semibold text-ink">{t.name}</p>
        <p className="label mt-1.5">{t.from}</p>
        <p className="label mt-2 text-accent">{t.trip}</p>
      </figcaption>
    </figure>
  );
}

function MarqueeRow({ items, reverse }: { items: Testimonial[]; reverse?: boolean }) {
  const track = fill(items);
  return (
    <div className="group relative overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_6%,black_94%,transparent)]">
      <div
        className={cn(
          "flex w-max group-hover:[animation-play-state:paused] [[data-offscreen]_&]:[animation-play-state:paused]",
          reverse ? "animate-[marquee_70s_linear_infinite_reverse]" : "animate-[marquee_70s_linear_infinite]",
        )}
      >
        {[0, 1].map((copy) => (
          <div key={copy} className="flex gap-4 pr-4 sm:gap-5 sm:pr-5">
            {track.map((t, i) => (
              <Card key={`${copy}-${i}`} t={t} className="w-[17.5rem] sm:w-[22rem]" />
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}

/**
 * Two counter-scrolling marquee rows of guest stories (pause on hover).
 * The marquee animates `transform` only and is paused while off-screen (IntersectionObserver
 * toggles `data-offscreen`, CSS sets `animation-play-state`).
 * Under prefers-reduced-motion the marquee is swapped for a static grid via CSS.
 * The marquee is aria-hidden; screen readers get the same list that forms the static grid.
 */
export function Testimonials() {
  const marquee = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = marquee.current;
    if (!el || typeof IntersectionObserver === "undefined") return;
    const io = new IntersectionObserver(([entry]) => {
      el.toggleAttribute("data-offscreen", !entry.isIntersecting);
    });
    io.observe(el);
    return () => io.disconnect();
  }, []);

  if (testimonials.length === 0) return null;

  const half = Math.ceil(testimonials.length / 2);
  const [rowA, rowB] =
    testimonials.length >= MIN_PER_TRACK
      ? [testimonials.slice(0, half), testimonials.slice(half)]
      : [testimonials, [...testimonials].reverse()];

  return (
    <Section className="overflow-hidden">
      <Container>
        <Reveal>
          <SectionHeading
            eyebrow="Guest stories"
            title="Postcards from our travellers."
            lead="Stories from the road — houseboats, snow days and high passes — in our guests' own words."
          />
        </Reveal>
      </Container>

      {/* Animated marquee (motion OK) */}
      <div ref={marquee} aria-hidden className="mt-10 flex flex-col gap-4 motion-reduce:hidden sm:mt-12 sm:gap-5">
        <MarqueeRow items={rowA} />
        <MarqueeRow items={rowB} reverse />
      </div>

      {/* One list: screen-reader only alongside the marquee, a visible static grid under reduced motion */}
      <Container>
        <div className="sr-only motion-reduce:not-sr-only">
          <ul className="mt-10 grid gap-4 sm:mt-12 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3">
            {testimonials.map((t, i) => (
              <li key={`list-${i}`} className="flex">
                <Card t={t} className="w-full" />
              </li>
            ))}
          </ul>
        </div>
      </Container>
    </Section>
  );
}
