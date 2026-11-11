"use client";

import Image from "next/image";
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type CSSProperties,
  type KeyboardEvent,
  type MouseEvent,
  type ReactNode,
} from "react";
import { ChevronLeft, ChevronRight, X } from "lucide-react";
import { pad2 } from "@/lib/utils";

/* Inside the theme-dark dialog. Solid on phones (backdrop blur is expensive on mid-range GPUs), frosted from md up. ≥ 44px. */
const CONTROL =
  "flex size-11 items-center justify-center rounded-full border border-line-strong bg-night/75 text-ink transition-colors hover:border-accent hover:text-accent md:bg-night/50 md:backdrop-blur-md";

/**
 * Client half of DestinationGallery: listens for clicks on the server-rendered thumbnails
 * (`[data-gallery-index]`) and shows an accessible native modal <dialog>:
 * Esc closes, ←/→ navigate, horizontal swipe on touch, focus returns to the opening thumbnail,
 * page scroll is locked while open. The full-size image is only mounted while open.
 * Slide transition is a CSS keyframe (transform/opacity only; off under reduced motion).
 */
export function DestinationLightbox({
  images,
  name,
  children,
}: {
  images: string[];
  name: string;
  children: ReactNode;
}) {
  const [open, setOpen] = useState(false);
  const [current, setCurrent] = useState(0);
  const [direction, setDirection] = useState(1);
  const rootRef = useRef<HTMLDivElement>(null);
  const dialogRef = useRef<HTMLDialogElement>(null);
  const touch = useRef<{ x: number; y: number } | null>(null);

  const count = images.length;

  const go = useCallback(
    (delta: number) => {
      setDirection(delta);
      setCurrent((i) => (i + delta + count) % count);
    },
    [count],
  );

  const close = useCallback(() => setOpen(false), []);

  // Event delegation: one listener for every thumbnail button.
  const onGridClick = (e: MouseEvent<HTMLDivElement>) => {
    const trigger = (e.target as HTMLElement).closest<HTMLElement>("[data-gallery-index]");
    if (!trigger) return;
    const i = Number(trigger.dataset.galleryIndex);
    if (!Number.isInteger(i) || i < 0 || i >= count) return;
    setDirection(1);
    setCurrent(i);
    setOpen(true);
  };

  // Sync the native modal dialog with state.
  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;
    if (open && !dialog.open) dialog.showModal();
    if (!open && dialog.open) dialog.close();
  }, [open]);

  // Lock page scroll while open (compensating for the scrollbar to avoid layout shift).
  useEffect(() => {
    if (!open) return;
    const html = document.documentElement;
    const prevOverflow = html.style.overflow;
    const prevPadding = html.style.paddingRight;
    const scrollbar = window.innerWidth - html.clientWidth;
    html.style.overflow = "hidden";
    if (scrollbar > 0) html.style.paddingRight = `${scrollbar}px`;
    return () => {
      html.style.overflow = prevOverflow;
      html.style.paddingRight = prevPadding;
    };
  }, [open]);

  // Fired for Esc (native cancel), the close button and backdrop clicks alike.
  const onDialogClose = () => {
    setOpen(false);
    rootRef.current
      ?.querySelector<HTMLElement>(`[data-gallery-index="${current}"]`)
      ?.focus({ preventScroll: true });
  };

  const onKeyDown = (e: KeyboardEvent) => {
    if (e.key === "ArrowRight") {
      e.preventDefault();
      go(1);
    } else if (e.key === "ArrowLeft") {
      e.preventDefault();
      go(-1);
    }
  };

  const slideStyle = { "--lb-dx": `${direction * 40}px` } as CSSProperties;

  return (
    <div ref={rootRef} onClick={onGridClick}>
      {children}

      <dialog
        ref={dialogRef}
        aria-label={`${name} photo gallery`}
        onClose={onDialogClose}
        onKeyDown={onKeyDown}
        data-lenis-prevent
        className="theme-dark fixed inset-0 m-0 h-dvh max-h-none w-screen max-w-none overflow-hidden bg-night/95 p-0 text-ink backdrop:bg-scrim/80"
      >
        {open && (
          <div className="relative flex h-full w-full flex-col">
            <style href="rth-lightbox" precedence="default">{`
              @keyframes rth-lb-in { from { opacity: 0; transform: translateX(var(--lb-dx, 40px)); } to { opacity: 1; transform: none; } }
            `}</style>

            {/* Top bar — clears the notch / status bar */}
            <div className="relative z-10 flex items-center justify-between gap-4 pt-[max(1rem,env(safe-area-inset-top))] pr-[max(1rem,env(safe-area-inset-right))] pb-4 pl-[max(1rem,env(safe-area-inset-left))] sm:pr-[max(1.5rem,env(safe-area-inset-right))] sm:pl-[max(1.5rem,env(safe-area-inset-left))]">
              <p className="label text-muted" aria-live="polite" aria-atomic="true">
                <span className="text-accent">{pad2(current + 1)}</span>
                <span className="mx-2 text-faint">/</span>
                {pad2(count)}
                <span className="sr-only"> — {name}</span>
              </p>
              <button type="button" onClick={close} autoFocus aria-label="Close gallery" className={CONTROL}>
                <X aria-hidden className="size-5" />
              </button>
            </div>

            {/* Stage — tap the empty area to close, swipe horizontally to navigate */}
            <div
              className="relative min-h-0 flex-1 touch-pan-y touch-pinch-zoom px-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-20 sm:pb-[max(2rem,env(safe-area-inset-bottom))]"
              onClick={(e) => {
                if (e.target === e.currentTarget) close();
              }}
              onTouchStart={(e) => {
                const t = e.touches[0];
                touch.current = e.touches.length === 1 && t ? { x: t.clientX, y: t.clientY } : null;
              }}
              onTouchEnd={(e) => {
                const start = touch.current;
                const t = e.changedTouches[0];
                touch.current = null;
                if (!start || !t) return;
                const dx = t.clientX - start.x;
                const dy = t.clientY - start.y;
                if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.2) go(dx < 0 ? 1 : -1);
              }}
            >
              <div
                key={current}
                style={slideStyle}
                className="relative h-full w-full motion-safe:animate-[rth-lb-in_0.5s_cubic-bezier(0.16,1,0.3,1)_both]"
              >
                <Image
                  src={images[current]}
                  alt={`${name} — photo ${current + 1} of ${count}`}
                  fill
                  sizes="100vw"
                  className="object-contain"
                  loading="eager"
                />
              </div>
            </div>

            {count > 1 && (
              <>
                <button
                  type="button"
                  onClick={() => go(-1)}
                  aria-label="Previous photo"
                  className={`${CONTROL} absolute top-1/2 left-[max(0.75rem,env(safe-area-inset-left))] z-10 -translate-y-1/2 sm:left-[max(1.25rem,env(safe-area-inset-left))] sm:size-12`}
                >
                  <ChevronLeft aria-hidden className="size-5" />
                </button>
                <button
                  type="button"
                  onClick={() => go(1)}
                  aria-label="Next photo"
                  className={`${CONTROL} absolute top-1/2 right-[max(0.75rem,env(safe-area-inset-right))] z-10 -translate-y-1/2 sm:right-[max(1.25rem,env(safe-area-inset-right))] sm:size-12`}
                >
                  <ChevronRight aria-hidden className="size-5" />
                </button>
              </>
            )}
          </div>
        )}
      </dialog>
    </div>
  );
}
