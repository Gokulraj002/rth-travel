"use client";

import { createContext, useContext, type ReactNode } from "react";
import { site as localSite } from "@/data/site";
import type { SiteContact } from "@/types";

/**
 * Contact details from the CMS (name, WhatsApp, phones, emails, addresses) for client components.
 * The root layout reads `getSite()` on the server and passes the small `SiteContact` slice in as a
 * prop — client components can't fetch, and the error boundary can't receive props at all.
 * The default (outside the provider) is the local `src/data/site.ts`, already in the client bundle.
 */
const SiteContext = createContext<SiteContact>(localSite);

export function SiteProvider({ value, children }: { value: SiteContact; children: ReactNode }) {
  return <SiteContext value={value}>{children}</SiteContext>;
}

export function useSite() {
  return useContext(SiteContext);
}
