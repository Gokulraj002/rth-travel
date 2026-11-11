"use client";

import { useMemo, useRef, type ComponentRef, type RefObject } from "react";
import { useFrame } from "@react-three/fiber";
import { Html, Line } from "@react-three/drei";
import { AdditiveBlending, MathUtils, Vector3, type Group, type Sprite, type SpriteMaterial } from "three";
import { srinagarAirport } from "@/data/origins";
import { useJourney } from "@/lib/store";
import { ACCENT, ACTIVE, COMET_HEAD, MARKER_IDLE, SURFACE_R, toVec3, useGlowTexture, type RigState } from "./globeShared";

const SEGMENTS = 128;
const TRAVEL = 2.8; // seconds for the comet to fly the arc
const PAUSE = 0.7; // seconds before it relaunches

function easeInOutCubic(x: number) {
  return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2;
}

/**
 * Lifted great-circle arc from the selected origin city to Srinagar (SXR), with a faint
 * guide line, a travelling leaf-green comet (single moving dash + glowing head), and an origin chip.
 * Strong in the intro state, faded once a destination is focused. Rebuilds when origin changes.
 * Render INSIDE the rotating globe group.
 */
export default function FlightArc({ rigRef }: { rigRef: RefObject<RigState> }) {
  // Origin changes rarely (a select in the DOM) — a selector subscription here is cheap.
  const origin = useJourney((s) => s.origin);
  const glow = useGlowTexture();

  const arc = useMemo(() => {
    const a = toVec3(origin.geo.lat, origin.geo.lng).normalize();
    const b = toVec3(srinagarAirport.lat, srinagarAirport.lng).normalize();
    const angle = a.angleTo(b);
    const lift = 0.02 + angle * 0.36;
    const sinA = Math.sin(angle) || 1e-6;
    const pts: Vector3[] = [];
    const dists: number[] = [0];
    for (let i = 0; i <= SEGMENTS; i++) {
      const t = i / SEGMENTS;
      // Spherical linear interpolation along the great circle.
      const wa = Math.sin((1 - t) * angle) / sinA;
      const wb = Math.sin(t * angle) / sinA;
      const p = new Vector3().addScaledVector(a, wa).addScaledVector(b, wb).normalize();
      p.multiplyScalar(SURFACE_R + lift * Math.sin(Math.PI * t));
      if (i > 0) dists.push(dists[i - 1] + p.distanceTo(pts[i - 1]));
      pts.push(p);
    }
    const length = dists[dists.length - 1];
    return {
      pts,
      dists,
      length,
      trail: length * 0.28,
      originPos: a.clone().multiplyScalar(SURFACE_R),
    };
  }, [origin]);

  const baseRef = useRef<ComponentRef<typeof Line>>(null);
  const trailRef = useRef<ComponentRef<typeof Line>>(null);
  const headRef = useRef<Sprite>(null);
  const headMat = useRef<SpriteMaterial>(null);
  const originMat = useRef<SpriteMaterial>(null);
  const labelGroup = useRef<Group>(null);
  const chipRef = useRef<HTMLDivElement>(null);
  const clock = useRef(0);
  const tmp = useMemo(() => ({ world: new Vector3(), toCam: new Vector3() }), []);

  useFrame(({ camera }, delta) => {
    const dt = Math.min(delta, 0.1);
    clock.current += dt;
    const focus = rigRef.current?.focus ?? 0;
    const master = 1 - 0.9 * focus;
    const k = Math.pow(MathUtils.clamp(((rigRef.current?.camDist ?? 3.4) - 1) / 0.55, 1, 6), 0.82);

    // Travelling dash: visible where (d + offset) mod period < trail, so offset = trail - s
    // puts a single dash ending exactly at arc distance s.
    const cycle = TRAVEL + PAUSE;
    const p = Math.min((clock.current % cycle) / TRAVEL, 1);
    const s = easeInOutCubic(p) * (arc.length + arc.trail);

    const trail = trailRef.current;
    if (trail) {
      trail.material.dashOffset = arc.trail - s;
      trail.material.opacity = 0.95 * master;
    }
    const base = baseRef.current;
    if (base) base.material.opacity = 0.32 * master;

    // Comet head: interpolate along the precomputed polyline.
    const head = headRef.current;
    if (head && headMat.current) {
      const sc = Math.min(s, arc.length);
      let i = 1;
      while (i < arc.dists.length - 1 && arc.dists[i] < sc) i++;
      const d0 = arc.dists[i - 1];
      const seg = arc.dists[i] - d0 || 1;
      head.position.lerpVectors(arc.pts[i - 1], arc.pts[i], MathUtils.clamp((sc - d0) / seg, 0, 1));
      const fadeOut = s > arc.length ? 1 - (s - arc.length) / arc.trail : 1;
      headMat.current.opacity = master * fadeOut;
      head.scale.setScalar(0.022 * k);
    }

    if (originMat.current) originMat.current.opacity = 0.9 * master;

    // Origin chip: fade with the arc and hide when it rotates to the far side.
    const g = labelGroup.current;
    const chip = chipRef.current;
    if (g && chip) {
      g.getWorldPosition(tmp.world);
      tmp.toCam.copy(camera.position).sub(tmp.world).normalize();
      const facing = MathUtils.clamp((tmp.world.normalize().dot(tmp.toCam) - 0.05) * 5, 0, 1);
      chip.style.opacity = String(facing * MathUtils.clamp(1 - focus * 1.6, 0, 1));
    }
  });

  return (
    <group>
      <Line
        ref={baseRef}
        points={arc.pts}
        color={ACCENT}
        lineWidth={1}
        transparent
        depthWrite={false}
        toneMapped={false}
      />
      <Line
        ref={trailRef}
        points={arc.pts}
        color={ACTIVE}
        lineWidth={2.2}
        dashed
        dashSize={arc.trail}
        gapSize={arc.length + arc.trail * 2}
        transparent
        depthWrite={false}
        toneMapped={false}
      />
      <sprite ref={headRef} renderOrder={5}>
        <spriteMaterial
          ref={headMat}
          map={glow}
          color={COMET_HEAD}
          transparent
          depthWrite={false}
          blending={AdditiveBlending}
          toneMapped={false}
        />
      </sprite>
      <sprite position={arc.originPos} scale={0.035} renderOrder={5}>
        <spriteMaterial
          ref={originMat}
          map={glow}
          color={MARKER_IDLE}
          transparent
          depthWrite={false}
          blending={AdditiveBlending}
          toneMapped={false}
        />
      </sprite>
      <group ref={labelGroup} position={arc.originPos}>
        <Html zIndexRange={[19, 0]} style={{ pointerEvents: "none" }}>
          <div
            ref={chipRef}
            key={origin.code}
            className="flex -translate-x-full -translate-y-1/2 items-center gap-2 whitespace-nowrap rounded-full border border-accent/40 bg-night/75 px-3 py-1.5 font-mono text-[10px] uppercase leading-none tracking-[0.18em] text-muted transition-opacity duration-300 [margin-left:-12px]"
            style={{ opacity: 0 }}
          >
            <span className="text-ink">{origin.code}</span>
            <span aria-hidden>→</span>
            <span className="text-accent">{srinagarAirport.code}</span>
          </div>
        </Html>
      </group>
    </group>
  );
}
