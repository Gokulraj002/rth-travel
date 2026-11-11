"use client";

import { useEffect, useMemo } from "react";
import { useLoader, useThree } from "@react-three/fiber";
import { ImageBitmapLoader, ShaderMaterial, SphereGeometry, SRGBColorSpace, Texture, TextureLoader } from "three";
import { ATMOSPHERE_FALLBACK, SUN_DIR, cssColor, type GlobeTier } from "./globeShared";

/** 4K for desktop ("full" tier), 2K for phones / low-core devices — ~4× less to download. */
const TEXTURES = {
  hd: { day: "/textures/earth-blue-marble.jpg", night: "/textures/earth-night.jpg" },
  sd: { day: "/textures/earth-blue-marble-2k.jpg", night: "/textures/earth-night-2k.jpg" },
} as const;

/** Per-tier cost: phones get fewer sphere segments and cheaper anisotropic filtering. */
const TIER = {
  full: { segments: 96, maxAniso: 16 },
  reduced: { segments: 64, maxAniso: 4 },
} as const;

/**
 * ImageBitmapLoader decodes the JPEGs off the main thread (createImageBitmap), so the GPU
 * upload no longer includes a multi-hundred-ms synchronous decode. Same browser gate as
 * three's GLTFLoader: old Safari / Firefox have broken createImageBitmap options.
 */
const USE_BITMAP = (() => {
  if (typeof navigator === "undefined" || typeof createImageBitmap === "undefined") return false;
  const ua = navigator.userAgent;
  const isSafari = /^((?!chrome|android).)*safari/i.test(ua);
  const safariVersion = isSafari ? Number(ua.match(/Version\/(\d+)/)?.[1] ?? -1) : -1;
  const firefoxVersion = ua.includes("Firefox") ? Number(ua.match(/Firefox\/(\d+)/)?.[1] ?? -1) : -1;
  return !(isSafari && safariVersion < 17) && !(firefoxVersion !== -1 && firefoxVersion < 98);
})();

function useBitmapMaps(urls: string[]): Texture[] {
  const bitmaps = useLoader(ImageBitmapLoader, urls, (loader) =>
    // ImageBitmaps ignore texture.flipY, so flip at decode time to match the sphere UVs.
    loader.setOptions({ imageOrientation: "flipY", premultiplyAlpha: "none" }),
  );
  const textures = useMemo(
    () =>
      bitmaps.map((b) => {
        const t = new Texture(b);
        t.flipY = false;
        return t;
      }),
    [bitmaps],
  );
  useEffect(() => () => textures.forEach((t) => t.dispose()), [textures]);
  return textures;
}

function useImageMaps(urls: string[]): Texture[] {
  return useLoader(TextureLoader, urls);
}

const useEarthMaps = USE_BITMAP ? useBitmapMaps : useImageMaps;

const vertexShader = /* glsl */ `
  varying vec2 vUv;
  varying vec3 vNormalW;
  varying vec3 vPosW;
  void main() {
    vUv = uv;
    vec4 wp = modelMatrix * vec4(position, 1.0);
    vPosW = wp.xyz;
    vNormalW = normalize(mat3(modelMatrix) * normal);
    gl_Position = projectionMatrix * viewMatrix * wp;
  }
`;

const fragmentShader = /* glsl */ `
  uniform sampler2D uDay;
  uniform sampler2D uNight;
  uniform vec3 uSunDir;
  uniform vec3 uAtmosphere;
  varying vec2 vUv;
  varying vec3 vNormalW;
  varying vec3 vPosW;

  void main() {
    vec3 n = normalize(vNormalW);
    vec3 v = normalize(cameraPosition - vPosW);
    float ndl = dot(n, uSunDir);

    // Soft terminator: day weight across ~12° of twilight.
    float dayMix = smoothstep(-0.12, 0.22, ndl);

    vec3 day = texture2D(uDay, vUv).rgb;   // sRGB texture → sampled as linear
    vec3 night = texture2D(uNight, vUv).rgb;

    // Day side: lambert-ish with a lifted ambient so continents stay readable.
    float diffuse = clamp(ndl, 0.0, 1.0);
    vec3 dayCol = day * (0.10 + 1.25 * pow(diffuse, 0.75));

    // Ocean mask from the blue-marble colour itself (blue-dominant + darkish) → sun glint.
    float ocean = clamp((day.b - max(day.r, day.g)) * 6.0, 0.0, 1.0) * (1.0 - smoothstep(0.25, 0.55, day.g));
    vec3 h = normalize(uSunDir + v);
    float spec = pow(max(dot(n, h), 0.0), 70.0) * ocean;
    dayCol += vec3(1.0, 0.92, 0.78) * spec * 0.55 * dayMix;

    // Warm twilight band right at the terminator.
    float twilight = smoothstep(-0.18, 0.0, ndl) * (1.0 - smoothstep(0.0, 0.2, ndl));
    dayCol += vec3(0.55, 0.22, 0.06) * twilight * 0.18;

    // Night side: sodium-warm city lights, boosted, fading in past the terminator.
    float lum = dot(night, vec3(0.299, 0.587, 0.114));
    vec3 lights = night * vec3(1.0, 0.78, 0.48) * 2.4 * smoothstep(0.03, 0.35, lum + 0.08);
    vec3 nightCol = lights + day * 0.012;

    vec3 col = mix(nightCol, dayCol, dayMix);

    // Atmospheric scattering on the disc edge (stronger on the sunlit limb).
    float fres = pow(1.0 - clamp(dot(n, v), 0.0, 1.0), 3.0);
    float litRim = smoothstep(-0.35, 0.5, ndl);
    col += uAtmosphere * fres * (0.25 + 0.95 * litRim);
    // Slight blue haze over the whole day side.
    col = mix(col, uAtmosphere * 0.6, 0.06 * dayMix);

    gl_FragColor = vec4(col, 1.0);
    #include <tonemapping_fragment>
    #include <colorspace_fragment>
  }
`;

/**
 * Realistic day/night earth: blue-marble day map, city lights on the night side, ocean glint.
 * Textures are only created here; GlobeScene uploads them (gl.initTexture) before the reveal.
 */
export default function Earth({ tier = "full" }: { tier?: GlobeTier }) {
  const urls = tier === "full" ? TEXTURES.hd : TEXTURES.sd;
  const { segments, maxAniso } = TIER[tier];
  const gl = useThree((s) => s.gl);
  const [day, night] = useEarthMaps([urls.day, urls.night]);

  const geometry = useMemo(() => new SphereGeometry(1, segments, segments), [segments]);

  const material = useMemo(() => {
    const aniso = Math.min(maxAniso, gl.capabilities.getMaxAnisotropy());
    for (const t of [day, night]) {
      t.colorSpace = SRGBColorSpace;
      t.anisotropy = aniso;
      t.needsUpdate = true;
    }
    return new ShaderMaterial({
      uniforms: {
        uDay: { value: day },
        uNight: { value: night },
        uSunDir: { value: SUN_DIR.clone() },
        uAtmosphere: { value: cssColor("--atmosphere", ATMOSPHERE_FALLBACK) },
      },
      vertexShader,
      fragmentShader,
    });
  }, [day, night, gl, maxAniso]);

  useEffect(
    () => () => {
      geometry.dispose();
      material.dispose();
    },
    [geometry, material],
  );

  return <mesh geometry={geometry} material={material} renderOrder={0} />;
}

