"use client";

import { Suspense, useCallback, useEffect, useMemo, useRef, useState, type RefObject } from "react";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { MathUtils, Vector3, type Group, type Material, type Mesh, type Object3D, type PerspectiveCamera, type Texture } from "three";
import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { useJourney } from "@/lib/store";
import Earth from "./Earth";
import Atmosphere from "./Atmosphere";
import Starfield from "./Starfield";
import Markers from "./Markers";
import FlightArc from "./FlightArc";
import { facingRotation, shortestAngle, type GlobePin, type GlobeTier, type RigState } from "./globeShared";

gsap.registerPlugin(ScrollTrigger);

export type { GlobeTier };

/** Intro framing: centre on the subcontinent so Kashmir sits just above centre. */
const INTRO = facingRotation(23, 77);
const INTRO_DIST = 3.4;
const FOCUS_DIST = 1.55;
const FOV = 40;

type Focus = ReturnType<typeof facingRotation> & { az: number; el: number };

/** Per-destination targets: globe rotation + a camera angle that varies per step. */
function focusTargets(stops: GlobePin[]): Focus[] {
  return stops.map((d, i) => {
    const rot = facingRotation(d.geo.lat, d.geo.lng);
    const side = i % 2 === 0 ? -1 : 1;
    return {
      ...rot,
      az: side * (0.07 + 0.035 * (i % 3)),
      el: -0.2 - 0.05 * (i % 3),
    };
  });
}

/**
 * Camera + globe choreography. Reads `activeIndex` from the store with getState() every frame
 * (no subscriptions in the loop) and eases everything with delta-based damping.
 */
function Rig({
  globeRef,
  rigRef,
  focus,
}: {
  globeRef: RefObject<Group | null>;
  rigRef: RefObject<RigState>;
  focus: Focus[];
}) {
  const s = useRef({
    yaw: INTRO.y - 2.4, // start rotated away so India drifts into view on load
    tilt: INTRO.x - 0.15,
    az: 0,
    el: 0,
    dist: INTRO_DIST + 0.6,
    time: 0,
  });
  const vecs = useRef({ look: new Vector3(), lookTarget: new Vector3() });

  useFrame((state, delta) => {
    const dt = Math.min(delta, 0.1);
    const st = s.current;
    const { look, lookTarget } = vecs.current;
    st.time += dt;
    const t = st.time;
    const cam = state.camera as PerspectiveCamera;
    const aspect = state.size.width / Math.max(1, state.size.height);
    const halfV = Math.tan(MathUtils.degToRad(cam.fov / 2));

    const active = useJourney.getState().activeIndex;
    const f = active >= 0 ? focus[active] : undefined;

    let yawT: number, tiltT: number, azT: number, elT: number, distT: number;
    if (f) {
      // Focused: destination faces the camera, close orbit with a slight per-step tilt + drift.
      yawT = f.y + Math.sin(t * 0.23) * 0.012;
      tiltT = f.x + Math.cos(t * 0.19) * 0.008;
      azT = f.az + Math.sin(t * 0.17) * 0.015;
      elT = f.el + Math.cos(t * 0.13) * 0.012;
      distT = FOCUS_DIST + Math.max(0, 1 - aspect) * 0.8;
      lookTarget.set(0, 0.12, 0.88);
    } else {
      // Intro: whole earth, gently swaying so the subcontinent drifts but never leaves view.
      yawT = INTRO.y + Math.sin(t * 0.11) * 0.22;
      tiltT = INTRO.x + Math.sin(t * 0.07) * 0.04;
      azT = 0;
      elT = 0;
      // On portrait screens pull back until the globe fits the width.
      distT = Math.max(INTRO_DIST, 1.2 / (halfV * aspect));
      lookTarget.set(0, 0, 0);
    }

    // Focus moves are snappy; the intro settles slowly so the earth visibly turns India into view.
    const rotLambda = f ? 2.2 : 1.1;
    st.yaw = MathUtils.damp(st.yaw, shortestAngle(st.yaw, yawT), rotLambda, dt);
    st.tilt = MathUtils.damp(st.tilt, tiltT, rotLambda, dt);
    st.az = MathUtils.damp(st.az, azT, 2, dt);
    st.el = MathUtils.damp(st.el, elT, 2, dt);
    st.dist = MathUtils.damp(st.dist, distT, 1.8, dt);
    look.x = MathUtils.damp(look.x, lookTarget.x, 2, dt);
    look.y = MathUtils.damp(look.y, lookTarget.y, 2, dt);
    look.z = MathUtils.damp(look.z, lookTarget.z, 2, dt);

    if (globeRef.current) globeRef.current.rotation.set(st.tilt, st.yaw, 0);

    const ce = Math.cos(st.el);
    cam.position.set(Math.sin(st.az) * ce * st.dist, Math.sin(st.el) * st.dist, Math.cos(st.az) * ce * st.dist);
    cam.lookAt(look);

    const r = rigRef.current;
    r.focus = MathUtils.damp(r.focus, f ? 1 : 0, 2.5, dt);
    r.camDist = st.dist;
  });

  return null;
}

/** Yield to the browser so each GPU upload / compile step lands in its own short task. */
const nextTask = () => new Promise<void>((resolve) => setTimeout(resolve, 0));

function collectTextures(scene: Object3D): Texture[] {
  const found = new Set<Texture>();
  const add = (v: unknown) => {
    if (v && typeof v === "object" && (v as Texture).isTexture) found.add(v as Texture);
  };
  scene.traverse((o) => {
    const mat = (o as Mesh).material as Material | Material[] | undefined;
    if (!mat) return;
    for (const m of Array.isArray(mat) ? mat : [mat]) {
      Object.values(m).forEach(add);
      const uniforms = (m as Material & { uniforms?: Record<string, { value: unknown }> }).uniforms;
      if (uniforms) Object.values(uniforms).forEach((u) => add(u.value));
    }
  });
  // Biggest first: the earth maps matter most and are the slowest to upload.
  return [...found].sort((a, b) => texSize(b) - texSize(a));
}
function texSize(t: Texture) {
  const img = t.image as { width?: number; height?: number } | undefined;
  return (img?.width ?? 0) * (img?.height ?? 0);
}

/**
 * Rendered inside the Suspense boundary, so it mounts only once every texture has resolved.
 * Uploads textures one per task, then compiles every program with `compileAsync`
 * (KHR_parallel_shader_compile — the driver compiles off the main thread while we poll),
 * and only then reports ready. The frameloop stays at "never" until then, so no render can
 * trigger a synchronous compile + upload in one giant frame.
 */
function Warmup({ onReady }: { onReady: () => void }) {
  const gl = useThree((s) => s.gl);
  const scene = useThree((s) => s.scene);
  const camera = useThree((s) => s.camera);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        for (const tex of collectTextures(scene)) {
          await nextTask();
          if (cancelled) return;
          gl.initTexture(tex);
        }
        await nextTask();
        if (cancelled) return;
        await gl.compileAsync(scene, camera);
      } catch {
        // Fall through: the first frame will compile/upload synchronously as before.
      }
      if (!cancelled) onReady();
    })();
    return () => {
      cancelled = true;
    };
  }, [gl, scene, camera, onReady]);

  return null;
}

function Scene({ tier, stops, onReady }: { tier: GlobeTier; stops: GlobePin[]; onReady: () => void }) {
  const globe = useRef<Group>(null);
  const focus = useMemo(() => focusTargets(stops), [stops]);
  const rigRef = useRef<RigState>({ focus: 0, camDist: INTRO_DIST });

  return (
    <>
      <Starfield count={tier === "full" ? 2600 : 900} />
      <Suspense fallback={null}>
        <group ref={globe} rotation={[INTRO.x - 0.15, INTRO.y - 2.4, 0]}>
          <Earth tier={tier} />
          <Markers stops={stops} rigRef={rigRef} />
          <FlightArc rigRef={rigRef} />
        </group>
        <Atmosphere segments={tier === "full" ? 64 : 48} />
        <Rig globeRef={globe} rigRef={rigRef} focus={focus} />
        <Warmup onReady={onReady} />
      </Suspense>
    </>
  );
}

/**
 * The R3F canvas. Lazily imported by GlobeStage (ssr:false) so three.js stays out of the
 * initial bundle. Nothing renders until <Warmup> has uploaded textures and compiled shaders;
 * after that `active` toggles the frameloop (paused while offscreen or the tab is hidden).
 * The "reduced" tier skips MSAA (the atmosphere halo hides the aliased limb) and caps DPR at 1.5.
 */
export default function GlobeScene({
  tier,
  stops,
  active,
  onReady,
}: {
  tier: GlobeTier;
  stops: GlobePin[];
  active: boolean;
  onReady?: () => void;
}) {
  const [ready, setReady] = useState(false);
  const handleReady = useCallback(() => {
    setReady(true);
    onReady?.();
  }, [onReady]);

  return (
    <div
      className="absolute inset-0 transition-opacity duration-[1400ms] ease-out-expo"
      style={{ opacity: ready ? 1 : 0 }}
    >
      <Canvas
        className="pointer-events-none"
        style={{ pointerEvents: "none" }}
        frameloop={ready && active ? "always" : "never"}
        dpr={tier === "full" ? [1, 2] : [1, 1.5]}
        gl={{ antialias: tier === "full", powerPreference: "high-performance", alpha: true }}
        camera={{ fov: FOV, near: 0.01, far: 300, position: [0, 0, INTRO_DIST + 0.6] }}
        flat
        onCreated={({ gl }) => {
          gl.setClearColor(0x000000, 0);
          requestAnimationFrame(() => ScrollTrigger.refresh());
        }}
      >
        <Scene tier={tier} stops={stops} onReady={handleReady} />
      </Canvas>
    </div>
  );
}
