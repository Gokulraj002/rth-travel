import Image from "next/image";
import { cn } from "@/lib/utils";

/**
 * No-WebGL / reduced-motion globe: the blue-marble texture clipped to a circle, centred on
 * the subcontinent, with CSS sphere shading, an atmosphere glow and a leaf-green Kashmir pin.
 * No Canvas, no animation loop.
 */

// Centre the 2:1 texture (rendered at 200% width via object-fit: cover) on longitude 76°E.
const CENTER_LNG = 76;
const centerU = (CENTER_LNG + 180) / 360;
const OBJECT_X = `${((2 * centerU - 0.5) * 100).toFixed(2)}%`;

// Kashmir (Srinagar) in container fractions for the flat projection above.
const PIN_X = 0.5 + ((74.7973 + 180) / 360 - centerU) * 2;
const PIN_Y = (90 - 34.0837) / 180;

export default function StaticGlobe({ className }: { className?: string }) {
  return (
    // Size container: the globe scales with the stage height (viewport minus sticky header).
    <div
      className={cn("pointer-events-none absolute inset-0 flex items-center justify-center [container-type:size]", className)}
      aria-hidden
    >
      <div
        className="relative aspect-square w-[min(80vw,78cqh)] rounded-full"
        style={{
          boxShadow:
            "0 0 60px 6px rgb(79 141 255 / 0.35), 0 0 160px 30px rgb(79 141 255 / 0.12), inset 0 0 0 1px rgb(127 178 255 / 0.25)",
        }}
      >
        <div className="absolute inset-0 overflow-hidden rounded-full">
          <Image
            src="/textures/earth-blue-marble-2k.jpg"
            alt=""
            fill
            sizes="(max-width: 768px) 80vw, 74vh"
            className="object-cover"
            style={{ objectPosition: `${OBJECT_X} 50%` }}
          />
          {/* Sphere shading: sunlit from upper-left, night limb on the right. */}
          <div
            className="absolute inset-0 rounded-full"
            style={{
              background:
                "radial-gradient(circle at 34% 30%, rgb(255 255 255 / 0.10) 0%, transparent 38%), radial-gradient(circle at 38% 36%, transparent 42%, rgb(2 11 26 / 0.55) 72%, rgb(2 11 26 / 0.92) 100%)",
            }}
          />
          {/* Atmospheric rim */}
          <div
            className="absolute inset-0 rounded-full"
            style={{ boxShadow: "inset 0 0 40px 4px rgb(79 141 255 / 0.45), inset -18px -10px 60px rgb(2 11 26 / 0.6)" }}
          />
        </div>
        {/* Kashmir pin */}
        <span
          className="absolute -translate-x-1/2 -translate-y-1/2"
          style={{ left: `${PIN_X * 100}%`, top: `${PIN_Y * 100}%` }}
        >
          <span className="absolute left-1/2 top-1/2 h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border border-leaf/70" />
          <span className="relative block h-2 w-2 rounded-full bg-leaf shadow-[0_0_14px_3px_rgb(76_193_61/0.7)]" />
        </span>
      </div>
    </div>
  );
}
