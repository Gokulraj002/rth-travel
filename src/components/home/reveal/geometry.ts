/**
 * Geometry helpers for the knockout-word zoom in <CinematicReveal>.
 *
 * Everything is in SVG user units of a fixed 1000 × 300 viewBox, so the numbers
 * are viewport-independent: the SVG (preserveAspectRatio "meet") scales the word
 * with the viewport, never font-size math.
 */

export const VB_W = 1000;
export const VB_H = 300;
export const BASE_VIEWBOX = `0 0 ${VB_W} ${VB_H}`;
/** Ink width of the word as a share of the viewBox width (≈ 83% of the stage width on screen). */
export const TARGET_W = 830;
/**
 * Max ink (cap) height in user units. With preserveAspectRatio "meet" the 300-unit viewBox height
 * is at most the full stage height, so 120 units keeps the word ≤ 40% of the stage (and thus of the
 * viewport) even on ultra-wide screens; short words shrink a little below TARGET_W instead.
 */
export const MAX_INK_H = 120;
/**
 * Knockout weight. Deliberately heavier than the 500–600 used for display text: this is a mask,
 * and Playfair's thick stems at 800 are what let the photo show through (and what the camera dives into).
 */
export const WEIGHT = 800;

export interface WordGeometry {
  /** font-size in user units */
  fontSize: number;
  /** text x (text-anchor: middle) and alphabetic baseline y */
  x: number;
  y: number;
  /** Half of the ink (cap) height — used to place the mono label above the word */
  half: number;
  /** Focal point (deepest solid point inside a glyph stroke) the camera flies into */
  px: number;
  py: number;
  /** Distance from the focal point to the nearest glyph edge, in user units */
  depth: number;
}

/** SSR-safe first guess before fonts load and we can measure. */
export function estimateGeometry(word: string): WordGeometry {
  // Playfair Display 800 caps: ≈ 0.74 em average advance, ≈ 0.71 em cap height.
  const fontSize = Math.min(TARGET_W / (word.length * 0.74), MAX_INK_H / 0.71);
  const half = fontSize * 0.355;
  return {
    fontSize,
    x: VB_W / 2,
    y: VB_H / 2 + half,
    half,
    px: VB_W / 2,
    py: VB_H / 2,
    depth: fontSize * 0.08,
  };
}

/**
 * The distance transform runs on a downscaled raster: 0.5 px per user unit → a 500 × 150
 * canvas (75k px, ~4× fewer than full-res). Plenty of precision for an 800-weight serif
 * whose main stems are ~20+ user units thick, and it keeps the pass ~1 ms.
 */
const DT_SCALE = 0.5;
const cache = new Map<string, WordGeometry>();

/**
 * Measure the word with the real (loaded) display font on a small offscreen canvas:
 * fit its ink to TARGET_W, centre it optically, then run a chamfer distance
 * transform over the glyph mask to find the deepest point inside a stroke.
 * Zooming into that point floods the screen with the photo, not the navy overlay.
 * Results are cached per word + font, so each word is only rasterised once per page —
 * but only when `cacheable` (the webfont is actually loaded), so a measurement taken with the
 * fallback face is never reused once Playfair arrives.
 */
export function measureWord(word: string, fontFamily: string, cacheable = true): WordGeometry | null {
  const key = `${word}\u0000${fontFamily}`;
  const hit = cache.get(key);
  if (hit) return hit;

  const W = Math.round(VB_W * DT_SCALE);
  const H = Math.round(VB_H * DT_SCALE);
  const canvas = document.createElement("canvas");
  canvas.width = W;
  canvas.height = H;
  const ctx = canvas.getContext("2d", { willReadFrequently: true });
  if (!ctx) return null;

  ctx.textAlign = "center";
  ctx.textBaseline = "alphabetic";

  ctx.font = `${WEIGHT} 100px ${fontFamily}`;
  const m100 = ctx.measureText(word);
  const ink100 = m100.actualBoundingBoxLeft + m100.actualBoundingBoxRight;
  if (!ink100 || !Number.isFinite(ink100)) return null;

  const inkH100 = m100.actualBoundingBoxAscent + m100.actualBoundingBoxDescent;
  // Fit the width to TARGET_W, but never let the ink grow taller than MAX_INK_H.
  const fontSize = Math.min((100 * TARGET_W) / ink100, inkH100 > 0 ? (100 * MAX_INK_H) / inkH100 : Infinity);
  ctx.font = `${WEIGHT} ${fontSize}px ${fontFamily}`;
  const m = ctx.measureText(word);
  const left = m.actualBoundingBoxLeft;
  const right = m.actualBoundingBoxRight;
  const ascent = m.actualBoundingBoxAscent;
  const descent = m.actualBoundingBoxDescent;

  // With text-anchor middle the ink is offset by (right - left) / 2 — cancel it out.
  const x = VB_W / 2 - (right - left) / 2;
  const y = VB_H / 2 + (ascent - descent) / 2;
  const half = (ascent + descent) / 2;

  // Rasterise in user units, scaled down onto the small canvas.
  ctx.setTransform(DT_SCALE, 0, 0, DT_SCALE, 0, 0);
  ctx.fillStyle = "#fff";
  ctx.fillText(word, x, y);

  const { data } = ctx.getImageData(0, 0, W, H);
  canvas.width = 0; // release the backing store early
  const INF = 1e9;
  const dist = new Float32Array(W * H);
  for (let i = 0; i < W * H; i++) dist[i] = data[i * 4 + 3] > 127 ? INF : 0;

  // Two-pass 3-4 chamfer distance transform (distance to nearest "outside" pixel).
  for (let yy = 0; yy < H; yy++) {
    for (let xx = 0; xx < W; xx++) {
      const i = yy * W + xx;
      if (dist[i] === 0) continue;
      let v = dist[i];
      if (xx > 0) v = Math.min(v, dist[i - 1] + 3);
      if (yy > 0) {
        v = Math.min(v, dist[i - W] + 3);
        if (xx > 0) v = Math.min(v, dist[i - W - 1] + 4);
        if (xx < W - 1) v = Math.min(v, dist[i - W + 1] + 4);
      }
      if (xx === 0 || yy === 0 || xx === W - 1) v = Math.min(v, 3);
      dist[i] = v;
    }
  }
  let best = -1;
  let bx = VB_W / 2;
  let by = VB_H / 2;
  let bestDepth = 0;
  for (let yy = H - 1; yy >= 0; yy--) {
    for (let xx = W - 1; xx >= 0; xx--) {
      const i = yy * W + xx;
      if (dist[i] === 0) continue;
      let v = dist[i];
      if (xx < W - 1) v = Math.min(v, dist[i + 1] + 3);
      if (yy < H - 1) {
        v = Math.min(v, dist[i + W] + 3);
        if (xx < W - 1) v = Math.min(v, dist[i + W + 1] + 4);
        if (xx > 0) v = Math.min(v, dist[i + W - 1] + 4);
      }
      if (yy === H - 1) v = Math.min(v, 3);
      dist[i] = v;
      // Prefer deep points near the middle of the word so the dive feels centred.
      const score = v * (1 - (0.45 * Math.abs(xx - W / 2)) / (W / 2));
      if (score > best) {
        best = score;
        // Back from raster pixels to user units.
        bx = (xx + 0.5) / DT_SCALE;
        by = (yy + 0.5) / DT_SCALE;
        bestDepth = v / 3 / DT_SCALE;
      }
    }
  }
  if (best <= 0) return null;

  const result: WordGeometry = { fontSize, x, y, half, px: bx, py: by, depth: bestDepth };
  if (cacheable) cache.set(key, result);
  return result;
}

/**
 * viewBox for zoom progress p ∈ [0,1]. Scale grows exponentially (constant
 * "camera speed") up to a factor big enough that the inscribed circle at the
 * focal point covers the whole viewport; the view centre glides onto the focal point.
 */
export function zoomViewBox(p: number, g: WordGeometry, widthPx: number, heightPx: number) {
  if (p <= 0 || widthPx <= 0 || heightPx <= 0) return BASE_VIEWBOX;
  const unit = Math.min(widthPx / VB_W, heightPx / VB_H); // px per user unit at scale 1
  const halfDiag = Math.hypot(widthPx / unit / 2, heightPx / unit / 2);
  const maxScale = Math.min(220, Math.max(24, (halfDiag / Math.max(g.depth, 1.5)) * 1.15));
  const s = Math.pow(maxScale, p);
  const k = 1 - Math.pow(1 - p, 3); // ease-out pan onto the focal point
  const cx = VB_W / 2 + (g.px - VB_W / 2) * k;
  const cy = VB_H / 2 + (g.py - VB_H / 2) * k;
  const w = VB_W / s;
  const h = VB_H / s;
  return `${(cx - w / 2).toFixed(3)} ${(cy - h / 2).toFixed(3)} ${w.toFixed(4)} ${h.toFixed(4)}`;
}
