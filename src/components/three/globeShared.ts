"use client";

import { useEffect, useMemo } from "react";
import { CanvasTexture, Color, SRGBColorSpace, Vector3 } from "three";
import { latLngToXYZ } from "@/lib/geo";

/**
 * Shared constants + helpers for the WebGL globe. Only imported by files inside the
 * lazily loaded GlobeScene chunk (never by GlobeStage / StaticGlobe), so `three` stays
 * out of the initial bundle.
 */

export type GlobeTier = "full" | "reduced";

/** Mutable per-frame state written by the camera rig, read by other parts (no React renders). */
export interface RigState {
  /** 0 = intro (whole earth), 1 = focused on a destination. Damped. */
  focus: number;
  /** Current camera distance from the earth centre. */
  camDist: number;
}

/** Fixed world-space sun direction: front-left-top, so the camera-facing side is lit and the
 * night side (city lights) wraps the right limb. */
export const SUN_DIR = new Vector3(-0.85, 0.32, 0.55).normalize();

/** Active marker, its beam and the flight comet — logo leaf green, brightened for the dark globe. */
export const ACTIVE = "#57d046";
/** Hot core of the comet head (additive, so it reads as a white-green spark). */
export const COMET_HEAD = "#d2ffc8";
/** Accent light blue (`--accent`) — guide arc and other secondary lines. */
export const ACCENT = "#7cc0ff";
/** Idle markers / origin dot — soft white-blue so they sit quietly on the blue earth. */
export const MARKER_IDLE = "#cfe0ff";
export const ATMOSPHERE_FALLBACK = "#4f8dff";

/** Earth surface radius for things sitting on the globe (avoids z-fighting with the 64–96-seg sphere). */
export const SURFACE_R = 1.003;

export function cssColor(varName: string, fallback: string) {
  if (typeof window === "undefined") return new Color(fallback);
  const v = getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
  try {
    return new Color(v || fallback);
  } catch {
    return new Color(fallback);
  }
}

export function toVec3(lat: number, lng: number, radius = 1) {
  const [x, y, z] = latLngToXYZ(lat, lng, radius);
  return new Vector3(x, y, z);
}

/**
 * Globe-group Euler angles (order XYZ → Y applied first, then X) that bring (lat, lng)
 * to world +Z, i.e. straight in front of a camera on the +Z axis, north up.
 */
export function facingRotation(lat: number, lng: number): { x: number; y: number } {
  const [px, , pz] = latLngToXYZ(lat, lng, 1);
  return { x: (lat * Math.PI) / 180, y: -Math.atan2(px, pz) };
}

/** Wrap an angle delta into [-π, π] so damped yaw always takes the short way round. */
export function shortestAngle(from: number, to: number) {
  let d = (to - from) % (Math.PI * 2);
  if (d > Math.PI) d -= Math.PI * 2;
  if (d < -Math.PI) d += Math.PI * 2;
  return from + d;
}

/** Deterministic PRNG (mulberry32) — stable star positions between renders/sessions. */
export function mulberry32(seed: number) {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/** Soft radial glow sprite texture, generated once per consumer and disposed on unmount. */
export function useGlowTexture() {
  const tex = useMemo(() => {
    const size = 128;
    const canvas = document.createElement("canvas");
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext("2d");
    if (ctx) {
      const g = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
      g.addColorStop(0, "rgba(255,255,255,1)");
      g.addColorStop(0.18, "rgba(255,255,255,0.85)");
      g.addColorStop(0.45, "rgba(255,255,255,0.25)");
      g.addColorStop(1, "rgba(255,255,255,0)");
      ctx.fillStyle = g;
      ctx.fillRect(0, 0, size, size);
    }
    const t = new CanvasTexture(canvas);
    t.colorSpace = SRGBColorSpace;
    return t;
  }, []);
  useEffect(() => () => tex.dispose(), [tex]);
  return tex;
}
