import Link from "next/link";
import type { ComponentProps, ReactNode } from "react";
import { cn } from "@/lib/utils";

type Variant = "primary" | "secondary" | "glass" | "ghost" | "outline";
type Size = "sm" | "md" | "lg";

/* All variants are scope-aware: inside `.theme-dark` the same tokens resolve to the dark palette. */
const variants: Record<Variant, string> = {
  primary: "bg-primary text-white hover:bg-primary-bright shadow-[0_6px_20px_-8px_rgb(0_84_144/0.55)]",
  secondary: "bg-surface text-ink border border-line shadow-soft hover:border-line-strong hover:shadow-lift",
  glass: "glass text-ink hover:bg-surface",
  ghost: "text-ink hover:bg-surface-2",
  outline: "border border-line-strong text-ink hover:border-primary hover:text-primary",
};

const sizes: Record<Size, string> = {
  sm: "h-9 px-4 text-[13px]",
  md: "h-11 px-5 text-sm",
  lg: "h-12 px-6 text-[15px]",
};

const base =
  "items-center justify-center gap-2 rounded-full font-semibold transition-all duration-300 ease-out-expo active:scale-[0.97] disabled:pointer-events-none disabled:opacity-50 whitespace-nowrap";

/**
 * Default display is `inline-flex`, but only when the caller doesn't set its own display utility.
 * (clsx doesn't resolve Tailwind conflicts, so `hidden sm:inline-flex` would otherwise lose to a
 * base `inline-flex` and the button would show on phones.)
 */
function display(className?: string) {
  return /(^|\s)(hidden|block|inline|inline-block|flex|grid|contents)(\s|$)/.test(className ?? "") ? "" : "inline-flex";
}

interface CommonProps {
  variant?: Variant;
  size?: Size;
  className?: string;
  children: ReactNode;
}

/** Internal or external link styled as a button. External links open in a new tab. */
export function ButtonLink({
  href,
  variant = "primary",
  size = "md",
  className,
  children,
  ...rest
}: CommonProps & Omit<ComponentProps<typeof Link>, "className" | "children">) {
  const external = typeof href === "string" && /^(https?:|mailto:|tel:)/.test(href);
  const cls = cn(display(className), base, variants[variant], sizes[size], className);
  if (external) {
    const url = href as string;
    const newTab = url.startsWith("http");
    // Forward plain anchor attributes (aria-label, title, onClick, data-*) — drop Link-only props.
    const {
      prefetch: _prefetch,
      replace: _replace,
      scroll: _scroll,
      shallow: _shallow,
      passHref: _passHref,
      legacyBehavior: _legacyBehavior,
      locale: _locale,
      onNavigate: _onNavigate,
      ...anchorProps
    } = rest as Record<string, unknown>;
    void [_prefetch, _replace, _scroll, _shallow, _passHref, _legacyBehavior, _locale, _onNavigate];
    return (
      <a
        href={url}
        className={cls}
        {...(anchorProps as ComponentProps<"a">)}
        {...(newTab ? { target: "_blank", rel: "noopener noreferrer" } : {})}
      >
        {children}
      </a>
    );
  }
  return (
    <Link href={href} className={cls} {...rest}>
      {children}
    </Link>
  );
}

export function Button({
  variant = "primary",
  size = "md",
  className,
  children,
  type = "button",
  ...rest
}: CommonProps & Omit<ComponentProps<"button">, "className" | "children">) {
  return (
    <button type={type} className={cn(display(className), base, variants[variant], sizes[size], className)} {...rest}>
      {children}
    </button>
  );
}
