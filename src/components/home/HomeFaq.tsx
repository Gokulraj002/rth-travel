import { MessageCircle } from "lucide-react";
import { faqs } from "@/data/content";
import { FaqList } from "@/components/shared/FaqList";
import { ButtonLink } from "@/components/ui/Button";
import { Container, Section, SectionHeading } from "@/components/ui/primitives";
import { Reveal } from "@/components/ui/Reveal";
import { whatsappLink } from "@/lib/utils";

/** Home FAQ: sticky heading + WhatsApp prompt on the left, accordion on the right. */
export function HomeFaq() {
  if (faqs.length === 0) return null;

  return (
    <Section id="faq">
      <Container>
        <div className="grid items-start gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-16">
          <div className="lg:sticky lg:top-[calc(var(--header-h)+24px)]">
            <Reveal>
              <SectionHeading
                eyebrow="FAQ"
                title="Questions, answered."
                lead="Permits, seasons, stays and rides — the things travellers ask us most before a Kashmir or Ladakh trip."
              />
              <div className="mt-8 rounded-2xl border border-line bg-surface p-6 shadow-soft inset-shadow-[0_1px_0_rgb(255_255_255/0.05)]">
                <p className="label text-accent">Still curious?</p>
                <p className="mt-2.5 text-[15px] leading-relaxed text-ink text-pretty sm:text-base">
                  Message us on WhatsApp — a real person from our Srinagar team will reply with straight answers.
                </p>
                <ButtonLink
                  href={whatsappLink("Hi Reyaan Trip Holiday! I have a question about a trip.")}
                  variant="primary"
                  className="mt-5 w-full sm:w-auto"
                >
                  <MessageCircle aria-hidden className="size-4" />
                  Ask on WhatsApp
                </ButtonLink>
              </div>
            </Reveal>
          </div>

          <Reveal delay={0.1}>
            <FaqList items={faqs.slice(0, 6)} />
          </Reveal>
        </div>
      </Container>
    </Section>
  );
}
