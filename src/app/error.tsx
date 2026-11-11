"use client";

import { useEffect } from "react";
import { MessageCircle, RotateCcw, TriangleAlert } from "lucide-react";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Container } from "@/components/ui/primitives";
import { whatsappLink } from "@/lib/utils";

/**
 * Spinning CSS earth used as the first "O" in OOPS. Compositor-only: a 4×-wide strip with the 2:1
 * texture tiled twice slides one tile via `transform` (`marquee` keyframes, reversed); the inset
 * shading sits on a fixed overlay so nothing repaints per frame.
 */
function EarthO() {
  return (
    <span aria-hidden className="relative mx-[0.03em] inline-block size-[0.74em] shrink-0 align-middle">
      <span className="absolute -inset-[0.05em] rounded-full bg-[radial-gradient(circle,rgb(0_84_144/0.18)_55%,transparent_72%)]" />
      <span
        className="absolute inset-0 overflow-hidden rounded-full bg-brand-deep"
        style={{ boxShadow: "0 0.08em 0.24em rgb(0 48 96 / 0.3)" }}
      >
        <span
          className="absolute inset-y-0 left-0 w-[400%] bg-repeat-x will-change-transform motion-safe:animate-[marquee_30s_linear_infinite_reverse] motion-reduce:-translate-x-1/4"
          style={{ backgroundImage: "url(/textures/earth-blue-marble-640.webp)", backgroundSize: "50% 100%" }}
        />
        <span
          className="absolute inset-0 rounded-full"
          style={{
            boxShadow:
              "inset -0.12em -0.05em 0.2em rgb(0 20 50 / 0.6), inset 0.05em 0.03em 0.12em rgb(255 255 255 / 0.3)",
          }}
        />
      </span>
    </span>
  );
}

export default function RouteError({ error, retry }: { error: unknown; retry: () => void }) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  const digest =
    error instanceof Error && "digest" in error && typeof error.digest === "string" ? error.digest : undefined;

  const helpMessage = `Hi Reyaan Trip Holiday, I ran into an error on your website${
    digest ? ` (ref ${digest})` : ""
  }. Can you help me with my enquiry?`;

  return (
    <section className="relative isolate flex min-h-[calc(100svh-var(--header-h))] items-center overflow-hidden bg-night py-16 sm:py-20">
      {/* soft sky: pale blue at the top fading into the page, with a faint logo-blue horizon */}
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(70%_55%_at_50%_0%,rgb(132_168_204/0.28),transparent_70%),radial-gradient(60%_45%_at_50%_105%,rgb(0_84_144/0.07),transparent_70%),linear-gradient(180deg,#e6effa_0%,var(--night)_65%)]"
      />

      <Container className="flex flex-col items-center text-center">
        <p className="label mb-6 inline-flex items-center gap-2 text-accent">
          <TriangleAlert className="size-3.5" aria-hidden />
          Unexpected turbulence
        </p>

        <h1
          className="flex items-center justify-center font-display leading-none font-semibold text-brand-deep uppercase"
          style={{ fontSize: "clamp(4.5rem, 20vw, 7rem)" }}
        >
          <span className="sr-only">Oops</span>
          <EarthO />
          <span aria-hidden>OPS</span>
        </h1>

        <h2 className="mt-8 font-display text-[1.875rem] font-semibold leading-[1.15] tracking-[-0.01em] text-balance text-ink sm:text-4xl">
          We hit some turbulence
        </h2>
        <p className="mt-4 max-w-xl text-[15px] leading-relaxed text-muted text-pretty sm:text-[17px]">
          Something went wrong while loading this page. Give it another go — and if it keeps happening, message us on
          WhatsApp and we&apos;ll plan your trip with you directly.
        </p>

        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
          <Button variant="primary" size="lg" onClick={() => retry()}>
            <RotateCcw className="size-4" aria-hidden />
            Try again
          </Button>
          <ButtonLink href={whatsappLink(helpMessage)} variant="secondary" size="lg">
            <MessageCircle className="size-4" aria-hidden />
            Get help on WhatsApp
          </ButtonLink>
        </div>

        {digest && <p className="label mt-12">Ref · {digest}</p>}
      </Container>
    </section>
  );
}
