<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Server-rendered SVG charts (no JavaScript, CSP-safe, print-friendly). Colours come from CSS
 * (.rth-chart-* classes in app.css), hover tooltips via <title>.
 *   <?= App\Support\Chart::bars(['Jan','Feb'], [12000, 18000], ['money' => true]) ?>
 *   <?= App\Support\Chart::line(['Mon','Tue'], [3, 5]) ?>
 *   <?= App\Support\Chart::donut(['Website','Phone'], [12, 4]) ?>
 * Options: height (px, default 220), money (bool → ₹ labels), series2 (bars only: second series values), names [s1, s2].
 */
final class Chart
{
    /** @param list<string> $labels @param list<int|float> $values @param array<string,mixed> $o */
    public static function bars(array $labels, array $values, array $o = []): string
    {
        $h = (int) ($o['height'] ?? 220);
        $w = 640;
        $padL = 56; $padB = 28; $padT = 12;
        $s2 = $o['series2'] ?? null;
        $max = max(1, ...array_map('floatval', $values), ...array_map('floatval', $s2 ?? [0]));
        $max = self::niceMax($max);
        $n = max(1, count($values));
        $slot = ($w - $padL - 8) / $n;
        $bw = min(46, $slot * ($s2 ? 0.36 : 0.6));
        $plotH = $h - $padB - $padT;
        $svg = self::open($w, $h, 'bars') . self::grid($w, $h, $padL, $padB, $padT, $max, !empty($o['money']));
        foreach ($values as $i => $v) {
            $x = $padL + $slot * $i + ($slot - ($s2 ? $bw * 2 + 4 : $bw)) / 2;
            $bh = $plotH * ((float) $v / $max);
            $name = $o['names'][0] ?? '';
            $svg .= sprintf('<rect class="rth-chart-bar" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3"><title>%s%s: %s</title></rect>',
                $x, $h - $padB - $bh, $bw, max(0.5, $bh), e($labels[$i] ?? ''), $name ? ' · ' . e($name) : '', e(self::fmt((float) $v, !empty($o['money']))));
            if ($s2) {
                $v2 = (float) ($s2[$i] ?? 0);
                $bh2 = $plotH * ($v2 / $max);
                $svg .= sprintf('<rect class="rth-chart-bar2" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3"><title>%s · %s: %s</title></rect>',
                    $x + $bw + 4, $h - $padB - $bh2, $bw, max(0.5, $bh2), e($labels[$i] ?? ''), e($o['names'][1] ?? ''), e(self::fmt($v2, !empty($o['money']))));
            }
            $svg .= sprintf('<text class="rth-chart-label" x="%.1f" y="%d" text-anchor="middle">%s</text>', $padL + $slot * $i + $slot / 2, $h - 8, e($labels[$i] ?? ''));
        }
        return $svg . '</svg>' . self::legend($o);
    }

    /** @param list<string> $labels @param list<int|float> $values @param array<string,mixed> $o */
    public static function line(array $labels, array $values, array $o = []): string
    {
        $h = (int) ($o['height'] ?? 220);
        $w = 640;
        $padL = 56; $padB = 28; $padT = 12;
        $max = self::niceMax(max(1, ...array_map('floatval', $values ?: [0])));
        $n = max(2, count($values));
        $step = ($w - $padL - 16) / ($n - 1);
        $plotH = $h - $padB - $padT;
        $pts = [];
        foreach ($values as $i => $v) {
            $pts[] = [$padL + $step * $i, $h - $padB - $plotH * ((float) $v / $max)];
        }
        $svg = self::open($w, $h, 'line') . self::grid($w, $h, $padL, $padB, $padT, $max, !empty($o['money']));
        if ($pts) {
            $d = 'M' . implode(' L', array_map(static fn ($p) => sprintf('%.1f %.1f', $p[0], $p[1]), $pts));
            $area = $d . sprintf(' L%.1f %d L%.1f %d Z', end($pts)[0], $h - $padB, $pts[0][0], $h - $padB);
            $svg .= '<path class="rth-chart-area" d="' . $area . '"/><path class="rth-chart-line" d="' . $d . '"/>';
            $every = (int) ceil(count($pts) / 12);
            foreach ($pts as $i => [$x, $y]) {
                $svg .= sprintf('<circle class="rth-chart-dot" cx="%.1f" cy="%.1f" r="3.5"><title>%s: %s</title></circle>', $x, $y, e($labels[$i] ?? ''), e(self::fmt((float) $values[$i], !empty($o['money']))));
                if ($i % $every === 0) {
                    $svg .= sprintf('<text class="rth-chart-label" x="%.1f" y="%d" text-anchor="middle">%s</text>', $x, $h - 8, e($labels[$i] ?? ''));
                }
            }
        }
        return $svg . '</svg>';
    }

    /** @param list<string> $labels @param list<int|float> $values @param array<string,mixed> $o */
    public static function donut(array $labels, array $values, array $o = []): string
    {
        $total = array_sum(array_map('floatval', $values));
        $size = 180; $r = 70; $c = 2 * M_PI * $r;
        $svg = '<div class="rth-donut"><svg class="rth-chart rth-chart-donut" viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-label="Chart">';
        $svg .= '<circle class="rth-chart-track" cx="90" cy="90" r="' . $r . '" fill="none" stroke-width="22"/>';
        $offset = 0.0;
        $legend = '<ul class="rth-donut-legend">';
        foreach ($values as $i => $v) {
            $frac = $total > 0 ? (float) $v / $total : 0;
            $len = $frac * $c;
            $cls = 'rth-chart-c' . ($i % 8);
            if ($len > 0) {
                $svg .= sprintf('<circle class="%s" cx="90" cy="90" r="%d" fill="none" stroke-width="22" stroke-dasharray="%.2f %.2f" stroke-dashoffset="%.2f" transform="rotate(-90 90 90)"><title>%s: %s (%d%%)</title></circle>',
                    $cls, $r, $len, $c - $len, -$offset, e($labels[$i] ?? ''), e(self::fmt((float) $v, !empty($o['money']))), (int) round($frac * 100));
            }
            $offset += $len;
            $legend .= '<li><span class="rth-dot ' . $cls . '"></span>' . e($labels[$i] ?? '') . ' <strong>' . e(self::fmt((float) $v, !empty($o['money']))) . '</strong></li>';
        }
        $svg .= '<text class="rth-donut-total" x="90" y="88" text-anchor="middle">' . e(self::fmt($total, !empty($o['money']))) . '</text>';
        $svg .= '<text class="rth-chart-label" x="90" y="108" text-anchor="middle">' . e((string) ($o['center'] ?? 'Total')) . '</text></svg>';
        return $svg . $legend . '</ul></div>';
    }

    private static function open(int $w, int $h, string $kind): string
    {
        return '<svg class="rth-chart rth-chart-kind-' . $kind . '" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Chart" width="100%">';
    }

    private static function grid(int $w, int $h, int $padL, int $padB, int $padT, float $max, bool $money): string
    {
        $out = '';
        for ($i = 0; $i <= 4; $i++) {
            $y = $h - $padB - ($h - $padB - $padT) * $i / 4;
            $out .= sprintf('<line class="rth-chart-grid" x1="%d" y1="%.1f" x2="%d" y2="%.1f"/>', $padL, $y, $w, $y);
            $out .= sprintf('<text class="rth-chart-label" x="%d" y="%.1f" text-anchor="end">%s</text>', $padL - 6, $y + 4, e(self::short($max * $i / 4, $money)));
        }
        return $out;
    }

    /** @param array<string,mixed> $o */
    private static function legend(array $o): string
    {
        if (empty($o['series2']) || empty($o['names'])) {
            return '';
        }
        return '<div class="rth-chart-legend"><span><i class="rth-dot rth-chart-c0"></i>' . e($o['names'][0]) . '</span><span><i class="rth-dot rth-chart-c1"></i>' . e($o['names'][1]) . '</span></div>';
    }

    private static function niceMax(float $v): float
    {
        $exp = 10 ** floor(log10($v));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $exp >= $v) {
                return $m * $exp;
            }
        }
        return 10 * $exp;
    }

    private static function short(float $v, bool $money): string
    {
        $p = $money ? '₹' : '';
        if ($v >= 10000000) return $p . round($v / 10000000, 1) . 'Cr';
        if ($v >= 100000) return $p . round($v / 100000, 1) . 'L';
        if ($v >= 1000) return $p . round($v / 1000, 1) . 'k';
        return $p . ($v > 0 && $v < 10 && $v != floor($v) ? number_format($v, 1) : (string) round($v));
    }

    private static function fmt(float $v, bool $money): string
    {
        return $money ? inr($v) : number_format($v, $v == floor($v) ? 0 : 1);
    }
}
