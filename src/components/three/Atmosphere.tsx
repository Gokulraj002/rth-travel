"use client";

import { useEffect, useMemo } from "react";
import { AdditiveBlending, BackSide, ShaderMaterial, SphereGeometry } from "three";
import { ATMOSPHERE_FALLBACK, SUN_DIR, cssColor } from "./globeShared";

const vertexShader = /* glsl */ `
  varying vec3 vNormalW;
  varying vec3 vPosW;
  void main() {
    vec4 wp = modelMatrix * vec4(position, 1.0);
    vPosW = wp.xyz;
    vNormalW = normalize(mat3(modelMatrix) * normal);
    gl_Position = projectionMatrix * viewMatrix * wp;
  }
`;

const fragmentShader = /* glsl */ `
  uniform vec3 uColor;
  uniform vec3 uSunDir;
  uniform float uIntensity;
  varying vec3 vNormalW;
  varying vec3 vPosW;
  void main() {
    vec3 n = normalize(vNormalW);
    vec3 v = normalize(cameraPosition - vPosW);
    // Back faces: n points away from the camera. 0 at the outer silhouette, rising toward the
    // earth's limb (≈0.45 for a 1.12 shell) — a soft glow that fades to nothing at its edge.
    float rim = clamp(-dot(n, v), 0.0, 1.0);
    float glow = pow(smoothstep(0.0, 0.55, rim), 1.8);
    float lit = mix(0.28, 1.0, smoothstep(-0.45, 0.55, dot(n, uSunDir)));
    vec3 col = uColor * glow * lit * uIntensity;
    gl_FragColor = vec4(col, glow * lit);
    #include <colorspace_fragment>
  }
`;

/** Back-side fresnel halo around the earth, additive, tinted by the --atmosphere token. */
export default function Atmosphere({
  scale = 1.12,
  intensity = 1.35,
  segments = 64,
}: {
  scale?: number;
  intensity?: number;
  segments?: number;
}) {
  const geometry = useMemo(() => new SphereGeometry(1, segments, segments), [segments]);
  const material = useMemo(
    () =>
      new ShaderMaterial({
        uniforms: {
          uColor: { value: cssColor("--atmosphere", ATMOSPHERE_FALLBACK) },
          uSunDir: { value: SUN_DIR.clone() },
          uIntensity: { value: intensity },
        },
        vertexShader,
        fragmentShader,
        side: BackSide,
        blending: AdditiveBlending,
        transparent: true,
        depthWrite: false,
      }),
    [intensity],
  );

  useEffect(
    () => () => {
      geometry.dispose();
      material.dispose();
    },
    [geometry, material],
  );

  return <mesh geometry={geometry} material={material} scale={scale} renderOrder={1} />;
}
