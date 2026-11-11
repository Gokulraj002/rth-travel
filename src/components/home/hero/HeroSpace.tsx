/**
 * Deep-space backdrop for the home hero (decorative, server-rendered, zero JS, no images).
 *  - navy space with two faint logo-blue nebula tints
 *  - a fine CSS starfield: a static dust layer, a slowly drifting layer and a few breathing stars
 *  - a planet limb rising from the bottom edge: night-side body, a crisp atmospheric hairline
 *    rim (accent → atmosphere), a soft outer glow that slowly breathes, and a sunrise glint
 * All classes live in HeroStyles; every animation is transform/opacity only.
 */
export function HeroSpace() {
  return (
    <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
      {/* Space: deep navy, a touch lighter towards the horizon, faint logo-blue nebulae */}
      <div className="absolute inset-0 bg-[linear-gradient(180deg,#020b1a_0%,#03102a_62%,#051a3f_100%)]" />
      <div className="absolute inset-0 bg-[radial-gradient(55%_45%_at_12%_8%,rgb(0_84_144/0.22),transparent_70%),radial-gradient(45%_40%_at_90%_26%,rgb(79_141_255/0.09),transparent_70%)]" />

      {/* Starfield */}
      <div className="hero-stars-fine" />
      <div className="hero-stars-drift" />
      <div className="hero-stars-bright" />

      {/* Planet limb */}
      <div className="hero-planet hero-planet-in" style={{ animationDelay: "120ms" }}>
        <div className="hero-planet-haze" />
        <div className="hero-planet-glow" />
        <div className="hero-planet-body" />
        <div className="hero-planet-rim" />
        <div className="hero-glint">
          <div className="hero-glint-bloom absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2" />
          <div className="hero-glint-streak" />
        </div>
      </div>
    </div>
  );
}
