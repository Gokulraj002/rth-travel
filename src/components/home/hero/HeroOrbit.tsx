import type { CSSProperties } from "react";

/**
 * A slow orbit ring around "H◯LIDAY", echoing the RTH logo's elliptical swoosh.
 * Must be rendered inside the display-word span: everything is sized in `em`, so it scales
 * with the title. Decorative and aria-hidden; the section's overflow clips it on narrow phones.
 *
 * - Ring: an SVG ellipse split into a faint far (upper) arc and a brighter near (lower) arc
 *   whose gradient stroke fades out at both ends, plus a ghost second line like the logo.
 * - Comet: a glowing dot travelling the same ellipse (see `.hero-orbit-*` in HeroStyles) —
 *   transform/opacity only, hidden under reduced motion.
 */
const RX = 3.3; // em — half the ring's width (the word itself is ≈ 5.1em wide)
const RY = 0.55; // em — half the ring's height (clears the caps above and below)
const PAD = 0.1; // em — room for the stroke

const W = Math.round((RX + PAD) * 200) / 100;
const H = Math.round((RY + PAD) * 200) / 100;
// viewBox units: 100 per em (rounded so the SVG attributes stay clean)
const u = (em: number) => Math.round(em * 100);
const cx = u(RX + PAD);
const cy = u(RY + PAD);
const rx = u(RX);
const ry = u(RY);

const farArc = `M ${cx - rx} ${cy} A ${rx} ${ry} 0 0 1 ${cx + rx} ${cy}`;
const nearArc = `M ${cx - rx} ${cy} A ${rx} ${ry} 0 0 0 ${cx + rx} ${cy}`;
const r = Math.round;
const ghostArc = `M ${r(cx - rx * 0.86)} ${r(cy + ry * 0.62)} A ${r(rx * 0.97)} ${r(ry * 1.08)} 0 0 0 ${r(cx + rx * 0.9)} ${r(cy + ry * 0.46)}`;

const orbitVars = { "--orbit-a": `${RX}em`, "--orbit-b": `${RY}em` } as CSSProperties;

export function HeroOrbit({ delay = 0 }: { delay?: number }) {
  return (
    <span
      aria-hidden="true"
      className="hero-orbit-in pointer-events-none absolute inset-0 -z-10"
      style={{ animationDelay: `${delay}ms` }}
    >
      {/* Zero-size anchor at the word's optical centre, tilted like the logo swoosh */}
      <span className="absolute left-1/2 top-[52%] size-0 -rotate-[4deg]" style={orbitVars}>
        <svg
          viewBox={`0 0 ${u(W)} ${u(H)}`}
          fill="none"
          className="absolute max-w-none overflow-visible"
          style={{ left: `${-W / 2}em`, top: `${-H / 2}em`, width: `${W}em`, height: `${H}em` }}
        >
          <defs>
            <linearGradient id="rth-orbit-far" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0" stopColor="#7cc0ff" stopOpacity="0" />
              <stop offset="0.5" stopColor="#7cc0ff" stopOpacity="0.16" />
              <stop offset="0.85" stopColor="#7cc0ff" stopOpacity="0.32" />
              <stop offset="1" stopColor="#7cc0ff" stopOpacity="0" />
            </linearGradient>
            <linearGradient id="rth-orbit-near" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0" stopColor="#4f8dff" stopOpacity="0" />
              <stop offset="0.22" stopColor="#4f8dff" stopOpacity="0.55" />
              <stop offset="0.55" stopColor="#b8ddff" stopOpacity="0.95" />
              <stop offset="0.82" stopColor="#7cc0ff" stopOpacity="0.6" />
              <stop offset="1" stopColor="#7cc0ff" stopOpacity="0" />
            </linearGradient>
          </defs>
          <path d={farArc} stroke="url(#rth-orbit-far)" strokeWidth="1" vectorEffect="non-scaling-stroke" />
          <path d={nearArc} stroke="url(#rth-orbit-near)" strokeWidth="1.4" vectorEffect="non-scaling-stroke" />
          <path
            d={ghostArc}
            stroke="url(#rth-orbit-near)"
            strokeOpacity="0.35"
            strokeWidth="1"
            vectorEffect="non-scaling-stroke"
          />
        </svg>

        {/* Comet: x / y / depth layers compose an elliptical path (see HeroStyles) */}
        <span className="hero-comet hero-orbit-x">
          <span className="hero-orbit-y block">
            <span className="hero-orbit-depth block">
              <span className="block size-[max(4px,0.05em)] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#eef7ff] shadow-[0_0_0.1em_0.03em_rgb(124_192_255/0.85),0_0_0.28em_rgb(79_141_255/0.5)]" />
            </span>
          </span>
        </span>
      </span>
    </span>
  );
}
