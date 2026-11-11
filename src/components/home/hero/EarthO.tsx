import type { CSSProperties } from "react";
import { cn } from "@/lib/utils";

/**
 * A spinning CSS earth sized in `em`, so it scales with the surrounding display type
 * and can stand in for the letter "O". Purely decorative — always render it aria-hidden
 * next to real text for screen readers.
 *
 * Structure (compositor-friendly — nothing repaints per frame):
 *  - outer circle (overflow-hidden): thin atmospheric rim + soft blue halo (tuned for deep space)
 *  - strip: 4× the circle's width = the 2:1 equirectangular texture tiled twice; it slides by
 *    exactly one tile (`translateX(-50%)` → 0) via `.hero-earth-strip`, a transform animation
 *  - overlay: sunlit upper-left, night side lower-right, inner atmospheric scatter — fixed above
 *    the strip, so the planet turns under a stationary light
 */
const shellStyle: CSSProperties = {
  boxShadow: [
    "0 0 0 0.008em rgb(124 192 255 / 0.75)", // crisp atmospheric rim
    "0 0 0.06em 0.01em rgb(124 192 255 / 0.45)", // tight rim glow
    "0 0 0.22em rgb(79 141 255 / 0.45)", // soft blue halo
    "0 0 0.55em rgb(79 141 255 / 0.18)", // wide, faint halo
  ].join(", "),
};

const stripStyle: CSSProperties = {
  backgroundImage: "url(/textures/earth-blue-marble-640.webp)",
  backgroundSize: "50% 100%",
  backgroundRepeat: "repeat-x",
};

const shadeStyle: CSSProperties = {
  backgroundImage: [
    // specular sunlight, upper-left
    "radial-gradient(circle at 30% 26%, rgb(255 255 255 / 0.26) 0%, rgb(255 255 255 / 0) 34%)",
    // terminator: day → night towards the lower-right
    "radial-gradient(circle at 34% 32%, rgb(2 11 26 / 0) 42%, rgb(2 11 26 / 0.45) 70%, rgb(2 11 26 / 0.88) 100%)",
    // atmospheric blue scatter along the sunlit edge
    "radial-gradient(circle at 50% 50%, rgb(79 141 255 / 0) 62%, rgb(79 141 255 / 0.28) 100%)",
  ].join(", "),
  boxShadow: [
    "inset -0.08em -0.05em 0.12em rgb(2 11 26 / 0.7)", // night limb
    "inset 0.02em 0.015em 0.035em rgb(184 221 255 / 0.55)", // sunlit rim
  ].join(", "),
};

export function EarthO({ className }: { className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        // Playfair Display: cap height ≈ 0.708em, round caps overshoot ≈ 0.012em above and below,
        // so a 0.73em circle nudged 0.012em under the baseline matches the serif "O".
        "relative inline-block size-[0.73em] overflow-hidden rounded-full bg-brand-deep align-[-0.012em]",
        className,
      )}
      style={shellStyle}
    >
      <span className="hero-earth-strip absolute inset-y-0 left-0 w-[400%]" style={stripStyle} />
      <span className="absolute inset-0 rounded-full" style={shadeStyle} />
    </span>
  );
}
