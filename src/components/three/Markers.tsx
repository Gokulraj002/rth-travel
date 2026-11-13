"use client";

import { useEffect, useMemo, useRef, type RefObject } from "react";
import { useFrame } from "@react-three/fiber";
import { Html } from "@react-three/drei";
import {
  AdditiveBlending,
  CircleGeometry,
  Color,
  CylinderGeometry,
  DoubleSide,
  MathUtils,
  Quaternion,
  RingGeometry,
  Vector3,
  type Group,
  type Mesh,
  type MeshBasicMaterial,
  type Sprite,
  type SpriteMaterial,
} from "three";
import { useJourney } from "@/lib/store";
import { pad2 } from "@/lib/utils";
import { ACTIVE, MARKER_IDLE, SURFACE_R, toVec3, useGlowTexture, type GlobePin, type RigState } from "./globeShared";

const Z = new Vector3(0, 0, 1);

interface MarkerPose {
  position: Vector3;
  quaternion: Quaternion;
}

function usePoses(stops: GlobePin[]): MarkerPose[] {
  return useMemo(
    () =>
      stops.map((d) => {
        const position = toVec3(d.geo.lat, d.geo.lng, SURFACE_R);
        const quaternion = new Quaternion().setFromUnitVectors(Z, position.clone().normalize());
        return { position, quaternion };
      }),
    [stops],
  );
}

/**
 * Destination pins on the globe surface: glowing dot + halo + pulsing ring (+ a light beam
 * for the active one). Must be rendered INSIDE the rotating globe group.
 */
export default function Markers({ stops, rigRef }: { stops: GlobePin[]; rigRef: RefObject<RigState> }) {
  const poses = usePoses(stops);
  const glow = useGlowTexture();
  const n = poses.length;

  const geo = useMemo(() => {
    const beam = new CylinderGeometry(0.0007, 0.0002, 1, 8, 1, true);
    beam.rotateX(Math.PI / 2); // axis along +Z (the surface normal in marker space)
    beam.translate(0, 0, 0.5); // base on the surface
    return {
      dot: new CircleGeometry(1, 32),
      ring: new RingGeometry(0.78, 1, 64),
      beam,
    };
  }, []);
  useEffect(
    () => () => {
      geo.dot.dispose();
      geo.ring.dispose();
      geo.beam.dispose();
    },
    [geo],
  );

  const colors = useMemo(() => ({ idle: new Color(MARKER_IDLE), active: new Color(ACTIVE) }), []);

  const dots = useRef<(Mesh | null)[]>([]);
  const dotMats = useRef<(MeshBasicMaterial | null)[]>([]);
  const rings = useRef<(Mesh | null)[]>([]);
  const ringMats = useRef<(MeshBasicMaterial | null)[]>([]);
  const halos = useRef<(Sprite | null)[]>([]);
  const haloMats = useRef<(SpriteMaterial | null)[]>([]);
  const beams = useRef<(Mesh | null)[]>([]);
  const beamMats = useRef<(MeshBasicMaterial | null)[]>([]);
  const amount = useRef<number[]>(new Array(n).fill(0));
  const time = useRef(0);

  useFrame((_, delta) => {
    const dt = Math.min(delta, 0.1);
    time.current += dt;
    const t = time.current;
    const active = useJourney.getState().activeIndex;
    const camDist = rigRef.current?.camDist ?? 3.4;
    // Keep pins a similar on-screen size whether we're at 3.4 units or 1.55.
    const k = Math.pow(MathUtils.clamp((camDist - 1) / 0.55, 1, 6), 0.82);

    for (let i = 0; i < n; i++) {
      const a = (amount.current[i] = MathUtils.damp(amount.current[i] ?? 0, i === active ? 1 : 0, 5, dt));

      const dot = dots.current[i];
      const dm = dotMats.current[i];
      if (dot && dm) {
        dot.scale.setScalar((0.0026 + 0.0024 * a) * k);
        dm.color.copy(colors.idle).lerp(colors.active, a);
      }

      const halo = halos.current[i];
      const hm = haloMats.current[i];
      if (halo && hm) {
        const breathe = 1 + Math.sin(t * 2.2 + i) * 0.08 * (0.4 + a);
        halo.scale.setScalar((0.016 + 0.022 * a) * k * breathe);
        hm.color.copy(colors.idle).lerp(colors.active, a);
        hm.opacity = 0.55 + 0.45 * a;
      }

      const ring = rings.current[i];
      const rm = ringMats.current[i];
      if (ring && rm) {
        const phase = (t * (0.5 + 0.2 * a) + i * 0.137) % 1;
        ring.scale.setScalar((0.005 + 0.004 * a) * k * (1 + 2.4 * phase));
        rm.opacity = (1 - phase) * (1 - phase) * (0.35 + 0.65 * a);
        rm.color.copy(colors.idle).lerp(colors.active, a);
      }

      const beam = beams.current[i];
      const bm = beamMats.current[i];
      if (beam && bm) {
        beam.visible = a > 0.01;
        beam.scale.set(k, k, 0.045 * k * a);
        bm.opacity = 0.7 * a;
      }
    }
  });

  return (
    <group>
      {poses.map((p, i) => (
        <group key={stops[i].slug} position={p.position} quaternion={p.quaternion}>
          <sprite
            ref={(el) => {
              halos.current[i] = el;
            }}
            renderOrder={3}
          >
            <spriteMaterial
              ref={(el) => {
                haloMats.current[i] = el;
              }}
              map={glow}
              transparent
              depthWrite={false}
              blending={AdditiveBlending}
              toneMapped={false}
            />
          </sprite>
          <mesh
            ref={(el) => {
              rings.current[i] = el;
            }}
            geometry={geo.ring}
            renderOrder={3}
          >
            <meshBasicMaterial
              ref={(el) => {
                ringMats.current[i] = el;
              }}
              transparent
              depthWrite={false}
              blending={AdditiveBlending}
              side={DoubleSide}
              toneMapped={false}
            />
          </mesh>
          <mesh
            ref={(el) => {
              dots.current[i] = el;
            }}
            geometry={geo.dot}
            position-z={0.0004}
            renderOrder={4}
          >
            <meshBasicMaterial
              ref={(el) => {
                dotMats.current[i] = el;
              }}
              side={DoubleSide}
              toneMapped={false}
            />
          </mesh>
          <mesh
            ref={(el) => {
              beams.current[i] = el;
            }}
            geometry={geo.beam}
            visible={false}
            renderOrder={4}
          >
            <meshBasicMaterial
              ref={(el) => {
                beamMats.current[i] = el;
              }}
              color={ACTIVE}
              transparent
              depthWrite={false}
              blending={AdditiveBlending}
              toneMapped={false}
            />
          </mesh>
        </group>
      ))}
      <ActiveLabel stops={stops} poses={poses} />
    </group>
  );
}

/** drei <Html> chip for the active destination only, e.g. "01 SRINAGAR". Hidden when on the far side. */
function ActiveLabel({ stops, poses }: { stops: GlobePin[]; poses: MarkerPose[] }) {
  // A selector subscription is fine here: activeIndex changes a handful of times per scroll,
  // and this re-renders only the label (never inside the frame loop).
  const active = useJourney((s) => s.activeIndex);
  const groupRef = useRef<Group>(null);
  const chipRef = useRef<HTMLDivElement>(null);
  const tmp = useMemo(() => ({ world: new Vector3(), toCam: new Vector3() }), []);

  useFrame(({ camera }) => {
    const g = groupRef.current;
    const chip = chipRef.current;
    if (!g || !chip) return;
    g.getWorldPosition(tmp.world);
    tmp.toCam.copy(camera.position).sub(tmp.world).normalize();
    const facing = tmp.world.normalize().dot(tmp.toCam);
    chip.style.opacity = String(MathUtils.clamp((facing - 0.05) * 5, 0, 1));
  });

  const d = active >= 0 ? stops[active] : undefined;
  if (!d) return null;
  const pose = poses[active];

  return (
    <group ref={groupRef} position={pose.position}>
      <Html zIndexRange={[20, 0]} style={{ pointerEvents: "none" }}>
        <div
          ref={chipRef}
          key={d.slug}
          className="flex -translate-y-1/2 translate-x-4 items-center gap-2 whitespace-nowrap rounded-full border border-accent/40 bg-night/75 px-3 py-1.5 font-mono text-[10px] uppercase leading-none tracking-[0.18em] text-ink shadow-[0_8px_30px_-12px_rgb(0_0_0/0.8)] transition-opacity duration-300"
          style={{ opacity: 0 }}
        >
          <span aria-hidden className="h-1.5 w-1.5 rounded-full bg-leaf shadow-[0_0_8px_1px_rgb(76_193_61/0.7)]" />
          <span className="text-accent">{pad2(active + 1)}</span>
          <span>{d.name}</span>
        </div>
      </Html>
    </group>
  );
}
