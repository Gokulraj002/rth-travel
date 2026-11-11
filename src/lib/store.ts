"use client";

import { create } from "zustand";
import type { OriginCity } from "@/types";
import { originCities } from "@/data/origins";

/**
 * Shared state between the DOM scroll story (GlobeJourney) and the WebGL globe.
 * Inside R3F `useFrame`, read with `useJourney.getState()` — never subscribe there.
 *
 * activeIndex:
 *   -1  → intro: whole-earth view, flight arc from origin city to Srinagar
 *   0…n → focused on `destinations[activeIndex]`
 */
interface JourneyState {
  activeIndex: number;
  setActiveIndex: (i: number) => void;
  origin: OriginCity;
  setOrigin: (code: string) => void;
}

export const useJourney = create<JourneyState>((set) => ({
  activeIndex: -1,
  setActiveIndex: (activeIndex) => set({ activeIndex }),
  origin: originCities[0],
  setOrigin: (code) =>
    set({ origin: originCities.find((c) => c.code === code) ?? originCities[0] }),
}));
