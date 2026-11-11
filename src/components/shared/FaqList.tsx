"use client";

import { useId, useState } from "react";
import { Plus } from "lucide-react";
import type { Faq } from "@/types";
import { cn, pad2 } from "@/lib/utils";

/**
 * Accessible single-open accordion with numbered questions + FAQPage JSON-LD.
 * Panels stay mounted (inert when closed) so answers are in the HTML for SEO.
 * Height animates with the CSS `grid-template-rows: 0fr → 1fr` technique — no animation
 * library; the global reduced-motion rule makes it instant.
 * Tokens only, so it reads correctly in the light scope (contact/about) and inside `theme-dark`
 * (home). Text/hover states use `accent`, not `primary`: dark-scope primary is a CTA fill colour
 * (~3:1 as text on navy), accent is ≥4.5:1 in both scopes.
 */
export function FaqList({ items }: { items: Faq[] }) {
  const baseId = useId();
  const [open, setOpen] = useState<number | null>(0);

  if (!items || items.length === 0) return null;

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: items.map((f) => ({
      "@type": "Question",
      name: f.q,
      acceptedAnswer: { "@type": "Answer", text: f.a },
    })),
  };

  return (
    <div>
      <ul className="border-t border-line">
        {items.map((item, i) => {
          const isOpen = open === i;
          const btnId = `${baseId}-q${i}`;
          const panelId = `${baseId}-a${i}`;
          return (
            <li key={`${i}-${item.q}`} className="border-b border-line">
              <h3>
                <button
                  id={btnId}
                  type="button"
                  aria-expanded={isOpen}
                  aria-controls={panelId}
                  onClick={() => setOpen(isOpen ? null : i)}
                  className="group flex min-h-11 w-full items-start gap-4 py-5 text-left sm:gap-5"
                >
                  <span
                    className={cn(
                      "label mt-[0.3rem] w-6 shrink-0 transition-colors",
                      isOpen ? "text-accent" : "group-hover:text-muted",
                    )}
                  >
                    {pad2(i + 1)}
                  </span>
                  <span
                    className={cn(
                      "min-w-0 flex-1 text-base font-semibold leading-snug text-pretty transition-colors sm:text-[17px]",
                      isOpen ? "text-ink" : "text-ink group-hover:text-accent",
                    )}
                  >
                    {item.q}
                  </span>
                  <span
                    aria-hidden
                    className={cn(
                      "-mt-0.5 grid size-8 shrink-0 place-items-center rounded-full border transition-[background-color,border-color,color,transform] duration-500 ease-out-expo",
                      isOpen
                        ? "rotate-45 border-primary bg-primary text-white"
                        : "border-line-strong bg-surface text-muted group-hover:border-accent group-hover:text-accent",
                    )}
                  >
                    <Plus className="size-4" />
                  </span>
                </button>
              </h3>
              <div
                id={panelId}
                role="region"
                aria-labelledby={btnId}
                inert={!isOpen}
                className={cn(
                  "grid transition-[grid-template-rows,opacity] duration-500 ease-out-expo",
                  isOpen ? "grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0",
                )}
              >
                <div className="min-h-0 overflow-hidden">
                  <p className="pb-6 pl-[calc(1.5rem+1rem)] pr-2 text-[15px] leading-relaxed text-muted text-pretty sm:pl-[calc(1.5rem+1.25rem)] sm:pr-13">
                    {item.a}
                  </p>
                </div>
              </div>
            </li>
          );
        })}
      </ul>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }}
      />
    </div>
  );
}
