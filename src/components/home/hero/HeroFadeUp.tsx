import type { CSSProperties, ReactNode } from "react";
import { cn } from "@/lib/utils";

/**
 * Fade-and-rise for hero blocks (mono line, tagline, planner, CTAs, scroll hint).
 * Server component: pure CSS (`hero-fade-up` in HeroStyles), so the content is in the
 * server HTML and animates on first paint without waiting for hydration.
 * Reduced motion → no animation, content sits in its final state.
 *
 * @param delay  animation delay in ms (keep small — this is above the fold)
 * @param y      starting offset in px (0 = opacity only)
 */
export function HeroFadeUp({
  children,
  delay = 0,
  y = 16,
  className,
}: {
  children: ReactNode;
  delay?: number;
  y?: number;
  className?: string;
}) {
  const style = { animationDelay: `${delay}ms`, "--rise": `${y}px` } as CSSProperties;
  return (
    <div className={cn("hero-fade-up", className)} style={style}>
      {children}
    </div>
  );
}
