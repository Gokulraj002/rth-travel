<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Safe image uploads.
 * - Accepts JPEG / PNG / WebP up to UPLOAD_MAX_MB (default 8 MB), checked by real content (finfo), not extension.
 * - Every image is DECODED and RE-ENCODED with GD → strips EXIF/GPS and any hidden payload.
 * - Resized to max 2000px on the long edge, saved as WebP (q=82) under public/uploads/YYYY/MM/<random>.webp.
 * - public/uploads/.htaccess disables script execution as a second line of defence.
 * Returns the public path, e.g. "/uploads/2026/11/3f9c…a1.webp", and records it in the `media` table.
 */
final class Uploader
{
    private const MAX_EDGE = 2000;
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** @param array<string,mixed> $file one entry from $_FILES @throws \RuntimeException with a user-friendly message */
    public static function image(array $file): string
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(match ($err) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image is too large.',
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_FILE => 'Please choose an image.',
                default => 'The image could not be uploaded (server error ' . $err . ').',
            });
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp) && PHP_SAPI !== 'cli') {
            throw new \RuntimeException('Invalid upload.');
        }
        $maxBytes = Env::int('UPLOAD_MAX_MB', 8) * 1024 * 1024;
        if ((int) $file['size'] > $maxBytes) {
            throw new \RuntimeException('The image is larger than ' . Env::int('UPLOAD_MAX_MB', 8) . ' MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('Only JPG, PNG or WebP images are allowed.');
        }
        $info = @getimagesize($tmp);
        if ($info === false || $info[0] < 200 || $info[1] < 150) {
            throw new \RuntimeException('That file is not a valid image (minimum 200×150 px).');
        }
        if ($info[0] * $info[1] > 40_000_000) {
            throw new \RuntimeException('The image dimensions are too large.');
        }

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png' => @imagecreatefrompng($tmp),
            'image/webp' => @imagecreatefromwebp($tmp),
        };
        if (!$src) {
            throw new \RuntimeException('The image could not be read.');
        }
        if ($mime === 'image/jpeg') {
            $src = self::autoRotate($src, $tmp);
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, self::MAX_EDGE / max($w, $h));
        $nw = (int) round($w * $scale);
        $nh = (int) round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        $dir = '/uploads/' . date('Y') . '/' . date('m');
        $abs = PUBLIC_PATH . $dir;
        if (!is_dir($abs) && !mkdir($abs, 0755, true) && !is_dir($abs)) {
            throw new \RuntimeException('Upload folder is not writable.');
        }
        $useWebp = function_exists('imagewebp');
        $name = bin2hex(random_bytes(12)) . ($useWebp ? '.webp' : '.jpg');
        $ok = $useWebp ? imagewebp($dst, "{$abs}/{$name}", 82) : imagejpeg($dst, "{$abs}/{$name}", 84);
        imagedestroy($dst);
        if (!$ok) {
            throw new \RuntimeException('The image could not be saved.');
        }

        $path = "{$dir}/{$name}";
        Database::insert('media', [
            'path' => $path,
            'original_name' => mb_substr(basename((string) ($file['name'] ?? '')), 0, 255),
            'mime' => $useWebp ? 'image/webp' : 'image/jpeg',
            'size_bytes' => (int) filesize("{$abs}/{$name}"),
            'width' => $nw,
            'height' => $nh,
            'uploaded_by' => Session::get('admin_id'),
        ]);
        return $path;
    }

    /** Apply EXIF orientation (phones store rotated photos) before EXIF is discarded. */
    private static function autoRotate(\GdImage $img, string $file): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($file);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $img;
        }
        $rotated = imagerotate($img, $angle, 0);
        if ($rotated === false) {
            return $img;
        }
        imagedestroy($img);
        return $rotated;
    }

    /** Delete an uploaded file (only inside public/uploads — never anything else). */
    public static function delete(string $path): void
    {
        if (!str_starts_with($path, '/uploads/') || str_contains($path, '..')) {
            return;
        }
        $abs = PUBLIC_PATH . $path;
        if (is_file($abs)) {
            @unlink($abs);
        }
        Database::query('DELETE FROM media WHERE path = ?', [$path]);
    }
}
