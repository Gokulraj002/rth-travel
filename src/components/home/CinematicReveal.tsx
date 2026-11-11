import { getDestination } from "@/data/destinations";
import type { Destination } from "@/types";
import { Container, SectionHeading } from "@/components/ui/primitives";
import { Reveal } from "@/components/ui/Reveal";
import { RevealPanel } from "./reveal/RevealPanel";

const SHOWCASE = ["srinagar", "ladakh", "gulmarg"] as const;

const showcase: Destination[] = SHOWCASE.map((slug) => getDestination(slug)).filter(
  (d): d is Destination => Boolean(d),
);

/**
 * "Cinematic reveal" — three pinned scenes where a giant word knocked out of a navy overlay
 * (photo visible only through the letters) flies at the camera until the photo fills the screen,
 * then a glass caption with highlights and a CTA rises in.
 * Static full-bleed photo sections under prefers-reduced-motion.
 *
 * Server component: only the panels hydrate. Each panel arms itself (font measurement +
 * GSAP timeline) when it comes within ~one viewport, during idle time.
 */
export function CinematicReveal() {
  if (showcase.length === 0) return null;

  return (
    // Dark navy intro (the home page is wrapped in `theme-dark`). Its bg-night matches the panels'
    // knockout overlay (`var(--night)`), so the intro flows straight into the first scene.
    // Each RevealPanel also carries its own `theme-dark`, so it stays navy wherever it is used.
    <section aria-labelledby="cinematic-reveal-title" className="relative bg-night">
      <div className="relative isolate overflow-hidden py-16 sm:py-24">
        {/* Night-sky backdrop: faint star dust + a soft logo-blue glow behind the heading */}
        <div aria-hidden className="stars-bg pointer-events-none absolute inset-0 -z-10 opacity-60" />
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 -z-10 bg-radial-[60%_90%_at_12%_0%] from-primary/20 to-transparent to-70%"
        />
        <Container>
          <Reveal>
            <SectionHeading
              eyebrow="The valley, up close"
              title={
                <span id="cinematic-reveal-title">
                  Three places that <span className="text-accent">never leave you.</span>
                </span>
              }
              lead="Lakes that mirror the Zabarwan range, passes above the clouds and a meadow that turns to powder snow. Scroll in — and step through the letters."
            />
          </Reveal>
        </Container>
      </div>

      {showcase.map((d, i) => (
        <RevealPanel key={d.slug} destination={d} index={i} total={showcase.length} />
      ))}
    </section>
  );
}
