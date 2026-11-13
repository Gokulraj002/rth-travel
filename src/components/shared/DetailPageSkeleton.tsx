/**
 * Suspense fallback for /packages/[slug] and /destinations/[slug].
 * Under Cache Components these routes' static shell can be regenerated at runtime (after a CMS edit
 * revalidates the `cms` tag) without known params, so param-dependent content must sit inside <Suspense>.
 * Known slugs are fully prerendered, so real visitors almost never see this. Height matches the dark
 * photo hero (PageHero / package hero) to avoid layout shift.
 */
export function DetailPageSkeleton() {
  return (
    <div aria-busy="true" aria-live="polite">
      <div className="theme-dark min-h-[46svh] bg-night sm:min-h-[52svh]">
        <div className="mx-auto w-full max-w-7xl px-4 pt-20 pb-16 sm:px-6 lg:px-8">
          <div className="h-3 w-40 animate-pulse rounded-full bg-white/10" />
          <div className="mt-6 h-12 w-3/4 max-w-xl animate-pulse rounded-xl bg-white/10 sm:h-16" />
          <div className="mt-5 h-4 w-2/3 max-w-lg animate-pulse rounded-full bg-white/10" />
        </div>
      </div>
      <div className="mx-auto w-full max-w-7xl space-y-4 px-4 py-14 sm:px-6 lg:px-8">
        <div className="h-6 w-56 animate-pulse rounded-full bg-surface-2" />
        <div className="h-4 w-full max-w-2xl animate-pulse rounded-full bg-surface-2" />
        <div className="h-4 w-5/6 max-w-xl animate-pulse rounded-full bg-surface-2" />
      </div>
      <span className="sr-only">Loading…</span>
    </div>
  );
}
