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
    <section id={id} className={cn("relative py-16 sm:py-24", className)}>
      {children}
    </section>
  );
}

/** Mono eyebrow label, e.g. "01 — WHY RTH". */
export function Eyebrow({ className, children }: { className?: string; children: ReactNode }) {
  return <p className={cn("label text-accent", className)}>{children}</p>;
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
      <h2 className="font-display text-[1.875rem] font-semibold leading-[1.12] tracking-[-0.01em] text-balance text-ink sm:text-4xl lg:text-[2.75rem]">
        {title}
      </h2>
      {lead && <p className="mt-4 text-[15px] leading-relaxed text-muted text-pretty sm:text-[17px]">{lead}</p>}
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
        active ? "border-primary bg-primary text-white" : "border-line bg-surface text-muted",
        className,
      )}
    >
      {children}
    </span>
  );
}
