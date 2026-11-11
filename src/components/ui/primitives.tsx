import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

/** Centered page container with the standard 16px mobile gutter. */
export function Container({ className, children }: { className?: string; children: ReactNode }) {
  return <div className={cn("mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8", className)}>{children}</div>;
}

/** Section wrapper with consistent vertical rhythm. */
export function Section({
  id,
  className,
  children,
}: {
  id?: string;
  className?: string;
  children: ReactNode;
}) {
  return (
    <section id={id} className={cn("relative py-20 sm:py-28", className)}>
      {children}
    </section>
  );
}

/** Mono eyebrow label, e.g. "01 — WHY RTH". */
export function Eyebrow({ className, children }: { className?: string; children: ReactNode }) {
  return <p className={cn("label text-gold", className)}>{children}</p>;
}

/** Eyebrow + display heading + optional lead paragraph. */
export function SectionHeading({
  eyebrow,
  title,
  lead,
  align = "left",
  className,
}: {
  eyebrow?: string;
  title: ReactNode;
  lead?: ReactNode;
  align?: "left" | "center";
  className?: string;
}) {
  return (
    <div className={cn("max-w-3xl", align === "center" && "mx-auto text-center", className)}>
      {eyebrow && <Eyebrow className="mb-4">{eyebrow}</Eyebrow>}
      <h2 className="font-display text-3xl font-bold leading-[1.05] tracking-tight text-balance sm:text-5xl">
        {title}
      </h2>
      {lead && <p className="mt-5 text-base leading-relaxed text-muted text-pretty sm:text-lg">{lead}</p>}
    </div>
  );
}

/** Small rounded pill/tag. */
export function Pill({
  className,
  children,
  active,
}: {
  className?: string;
  children: ReactNode;
  active?: boolean;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium",
        active ? "border-gold bg-gold text-night" : "border-line bg-white/[0.03] text-muted",
        className,
      )}
    >
      {children}
    </span>
  );
}
