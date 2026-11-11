import { HomeHero } from "@/components/home/HomeHero";
import { GlobeJourney } from "@/components/home/GlobeJourney";
import { DestinationMarquee } from "@/components/home/DestinationMarquee";
import { CinematicReveal } from "@/components/home/CinematicReveal";
import { PackageExplorer } from "@/components/home/PackageExplorer";
import { WhyUs } from "@/components/home/WhyUs";
import { Fleet } from "@/components/home/Fleet";
import { Testimonials } from "@/components/home/Testimonials";
import { HomeFaq } from "@/components/home/HomeFaq";
import { CtaBand } from "@/components/shared/CtaBand";

/**
 * Home — the cinematic story, top to bottom:
 * hero (H🌍LIDAY + trip planner) → scroll-driven globe journey across 7 destinations →
 * marquee → giant-word photo reveals → package explorer → why us → fleet → reviews → FAQ → CTA.
 */
export default function Home() {
  // The home page keeps the original dark navy "night sky" design (owner's request);
  // `theme-dark` flips every token inside. All other pages use the light theme.
  return (
    <div className="theme-dark bg-night">
      <HomeHero />
      <GlobeJourney />
      <DestinationMarquee />
      <CinematicReveal />
      <PackageExplorer />
      <div className="cv-auto">
        <WhyUs />
      </div>
      <div className="cv-auto">
        <Fleet />
      </div>
      <div className="cv-auto">
        <Testimonials />
      </div>
      <div className="cv-auto">
        <HomeFaq />
      </div>
      <div className="cv-auto">
        <CtaBand />
      </div>
    </div>
  );
}
