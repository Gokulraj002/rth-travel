"use client";

import { useEffect, useMemo, useRef } from "react";
import { useFrame, useThree } from "@react-three/fiber";
import { AdditiveBlending, BufferAttribute, BufferGeometry, ShaderMaterial, type Points } from "three";
import { mulberry32 } from "./globeShared";

const vertexShader = /* glsl */ `
  attribute float aSize;
  attribute vec3 aColor;
  attribute float aPhase;
  uniform float uTime;
  uniform float uPixelRatio;
  varying vec3 vColor;
  varying float vAlpha;
  void main() {
    vec4 mv = modelViewMatrix * vec4(position, 1.0);
    gl_Position = projectionMatrix * mv;
    float tw = 0.75 + 0.25 * sin(uTime * (0.6 + aPhase * 1.4) + aPhase * 40.0);
    vAlpha = tw;
    vColor = aColor;
    gl_PointSize = aSize * uPixelRatio;
  }
`;

const fragmentShader = /* glsl */ `
  varying vec3 vColor;
  varying float vAlpha;
  void main() {
    vec2 c = gl_PointCoord - 0.5;
    float d = length(c);
    float core = smoothstep(0.5, 0.0, d);
    float a = pow(core, 2.2) * vAlpha;
    if (a < 0.01) discard;
    gl_FragColor = vec4(vColor * a, a);
    #include <colorspace_fragment>
  }
`;

/** Deterministic (seeded) star shell around the scene with gentle twinkle and drift. */
export default function Starfield({ count = 2600 }: { count?: number }) {
  const ref = useRef<Points>(null);
  const dpr = useThree((s) => s.viewport.dpr);

  const geometry = useMemo(() => {
    const rand = mulberry32(20260611);
    const pos = new Float32Array(count * 3);
    const col = new Float32Array(count * 3);
    const size = new Float32Array(count);
    const phase = new Float32Array(count);
    for (let i = 0; i < count; i++) {
      // Uniform direction on a sphere, radius 40–90.
      const u = rand() * 2 - 1;
      const t = rand() * Math.PI * 2;
      const s = Math.sqrt(1 - u * u);
      const r = 40 + rand() * 50;
      pos[i * 3] = s * Math.cos(t) * r;
      pos[i * 3 + 1] = u * r;
      pos[i * 3 + 2] = s * Math.sin(t) * r;
      // Mostly blue-white, a few warm stars. Values are linear.
      const warm = rand();
      const b = 0.55 + rand() * 0.45;
      if (warm > 0.9) {
        col[i * 3] = b;
        col[i * 3 + 1] = b * 0.78;
        col[i * 3 + 2] = b * 0.55;
      } else {
        col[i * 3] = b * 0.82;
        col[i * 3 + 1] = b * 0.9;
        col[i * 3 + 2] = b;
      }
      // Power-law sizes: many faint pin-pricks, a handful of bright ones.
      size[i] = 1.2 + Math.pow(rand(), 6) * 4.5;
      phase[i] = rand();
    }
    const g = new BufferGeometry();
    g.setAttribute("position", new BufferAttribute(pos, 3));
    g.setAttribute("aColor", new BufferAttribute(col, 3));
    g.setAttribute("aSize", new BufferAttribute(size, 1));
    g.setAttribute("aPhase", new BufferAttribute(phase, 1));
    return g;
  }, [count]);

  const material = useMemo(
    () =>
      new ShaderMaterial({
        uniforms: { uTime: { value: 0 }, uPixelRatio: { value: 1 } },
        vertexShader,
        fragmentShader,
        transparent: true,
        depthWrite: false,
        blending: AdditiveBlending,
      }),
    [],
  );

  useEffect(
    () => () => {
      geometry.dispose();
      material.dispose();
    },
    [geometry, material],
  );

  useFrame((_, delta) => {
    const dt = Math.min(delta, 0.1);
    const pts = ref.current;
    if (!pts) return;
    const mat = pts.material as ShaderMaterial;
    mat.uniforms.uTime.value += dt;
    mat.uniforms.uPixelRatio.value = dpr;
    pts.rotation.y += dt * 0.004;
    pts.rotation.x += dt * 0.0012;
  });

  return <points ref={ref} geometry={geometry} material={material} frustumCulled={false} renderOrder={-1} />;
}
