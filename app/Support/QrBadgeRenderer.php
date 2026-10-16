<?php

namespace App\Support;

use App\Models\User;

/**
 * Renders an intern's personal QR ID badge as a PNG — the same card the
 * Profile page shows (dark header, initials, name, student ID, the code)
 * and its Download PNG button produces. Ported from that page's canvas
 * script: identical layout math, colors, and type scale, drawn with GD
 * at 3x for crisp printing.
 */
class QrBadgeRenderer
{
    private const W = 288;
    private const HEADER_H = 64;
    private const AVATAR_D = 64;
    private const QR_BOX = 224;
    private const QR_IMG = 208;
    private const SCALE = 3;

    /** DejaVu (OFL) ships with dompdf — regular and bold cover the badge. */
    private const FONT = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf';
    private const FONT_BOLD = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';

    /**
     * The badge as raw PNG bytes.
     */
    public static function render(User $intern): string
    {
        $hasId = (bool) $intern->student_id;

        // Fixed vertical rhythm, mirroring the on-screen badge.
        $y = self::HEADER_H + 22;
        $avatarTop = $y;   $y += self::AVATAR_D + 18;
        $nameTop = $y;     $y += 15 + 6;
        $idTop = null;
        if ($hasId) { $idTop = $y; $y += 11; }
        $y += 16;
        $qrBoxTop = $y;    $y += self::QR_BOX + 16;
        $footerTop = $y;   $y += 9 + 24;
        $totalH = $y;

        $s = self::SCALE;
        $W = self::W * $s;
        $H = $totalH * $s;
        $img = imagecreatetruecolor($W, $H);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        imagealphablending($img, true);

        $white = self::color($img, '#ffffff');
        $ink = self::color($img, '#111827');
        $gray400 = self::color($img, '#9ca3af');
        $gray500 = self::color($img, '#6b7280');
        $gray100 = self::color($img, '#f3f4f6');
        $gray200 = self::color($img, '#e5e7eb');

        // Card: dark rounded rect behind, white body (rounded bottom only)
        // on top — leaves the header band with square lower edge, rounded
        // top corners tucked into the card.
        self::fillRoundRect($img, 0, 0, $W, $H, $s * 16, $ink);
        self::fillRoundRect($img, 0, self::HEADER_H * $s, $W, $H - self::HEADER_H * $s, $s * 16, $white, [false, false, true, true]);

        // Header band.
        $cx = $W / 2;
        self::trackedText($img, $cx, 25 * $s, 'OJT TRACKER — NORSU BSC', $gray400, 8, true, 1.5, $s);
        self::text($img, $cx, 44 * $s, 'Time In / Out', $white, 14, true, $s, 'middle');

        // Avatar — initials fallback, the same one the on-screen badge
        // and its canvas twin use when no photo can be drawn.
        $r = self::AVATAR_D * $s / 2;
        $acy = $avatarTop * $s + $r;
        imagefilledellipse($img, (int) $cx, (int) $acy, (int) ($r * 2), (int) ($r * 2), $gray100);
        imageellipse($img, (int) $cx, (int) $acy, (int) ($r * 2), (int) ($r * 2), $gray200);
        self::text($img, $cx, $acy + 1 * $s, $intern->initials ?: '', $gray500, 22, true, $s, 'middle');

        // Name and student ID.
        self::text($img, $cx, $nameTop * $s, $intern->full_name, $ink, 15, true, $s, 'top', self::W * $s - 28 * $s);
        if ($hasId) {
            self::text($img, $cx, $idTop * $s, $intern->student_id, $gray500, 11, false, $s, 'top', self::W * $s - 28 * $s);
        }

        // QR in its bordered box — the border is a filled ring (outer box
        // in the border color, inner box in white) so the corners read as
        // smooth rounds, not arc joints.
        $boxX = $cx - self::QR_BOX * $s / 2;
        self::fillRoundRect($img, $boxX, $qrBoxTop * $s, self::QR_BOX * $s, self::QR_BOX * $s, $s * 12, $gray100);
        self::fillRoundRect($img, $boxX + $s, $qrBoxTop * $s + $s, self::QR_BOX * $s - 2 * $s, self::QR_BOX * $s - 2 * $s, $s * 11, $white);

        $png = base64_decode(substr($intern->qrDataUri(), strlen('data:image/png;base64,')), true);
        if ($png !== false && ($qr = imagecreatefromstring($png)) !== false) {
            $qd = self::QR_IMG * $s;
            $qx = (int) ($cx - $qd / 2);
            $qy = (int) ($qrBoxTop * $s + (self::QR_BOX * $s - $qd) / 2);
            imagecopyresampled($img, $qr, $qx, $qy, 0, 0, (int) $qd, (int) $qd, imagesx($qr), imagesy($qr));
        }

        // Footer.
        self::trackedText($img, $cx, $footerTop * $s, 'PRESENT AT THE OFFICE SCANNER', $gray400, 9, true, 1.0, $s);

        ob_start();
        imagepng($img, null, 6);

        return ob_get_clean();
    }

    private static function color($img, string $hex): int
    {
        return imagecolorallocate(
            $img,
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        );
    }

    /**
     * Centred TTF text. $valign 'top' places the cap-top near y (GD works
     * from the baseline, so the ascent is added); 'middle' centres the
     * glyph box (ascent..descent) on y — the canvas baseline the badge's
     * on-screen twin uses for header, initials, and title.
     */
    private static function text($img, float $cx, float $y, string $string, int $color, float $size, bool $bold, int $s, string $valign = 'top', ?float $maxWidth = null): void
    {
        $font = base_path($bold ? self::FONT_BOLD : self::FONT);
        $size = $size * $s;

        if ($maxWidth !== null && $maxWidth > 0) {
            // Shrink-to-fit like the canvas width hint (never below 8px).
            while ($size > 8 * $s) {
                $box = imageftbbox($size, 0, $font, $string);
                if ($box[2] - $box[0] <= $maxWidth) {
                    break;
                }
                $size -= 0.5 * $s;
            }
        }

        $box = imageftbbox($size, 0, $font, $string);
        $width = $box[2] - $box[0];
        $ascent = -$box[7];
        $descent = $box[1];
        $baseline = $valign === 'middle'
            ? $y + ($ascent - $descent) / 2
            : $y + $ascent;

        imagettftext($img, $size, 0, (int) ($cx - $width / 2 - $box[0]), (int) $baseline, $color, $font, $string);
    }

    /**
     * Upper-case micro-labels drawn character-by-character with tracking,
     * matching the canvas letterSpacing styling on the badge. Middle-
     * anchored like the canvas headers.
     */
    private static function trackedText($img, float $cx, float $y, string $label, int $color, float $size, bool $bold, float $tracking, int $s): void
    {
        $font = base_path($bold ? self::FONT_BOLD : self::FONT);
        $size = $size * $s;
        $tracking = $tracking * $s;
        // mb_* (not strtoupper): the plain function is locale-dependent and
        // mangles multibyte characters like the em dash in the header.
        $label = mb_strtoupper($label, 'UTF-8');

        // mb_str_split (not str_split): the byte-based variant would carve
        // multibyte characters (the header's em dash) into garbage glyphs.
        $chars = mb_str_split($label, 1, 'UTF-8');

        $width = - $tracking;
        $boxes = [];
        foreach ($chars as $char) {
            $box = imageftbbox($size, 0, $font, $char);
            $boxes[] = [$box[2] - $box[0], -$box[7], $box[1]];
            $width += ($box[2] - $box[0]) + $tracking;
        }

        $ascent = max(array_map(fn ($b) => $b[1], $boxes ?: [[0, $size, 0]]));
        $descent = max(array_map(fn ($b) => $b[2], $boxes ?: [[0, 0, 0]]));
        $baseline = $y + ($ascent - $descent) / 2;

        $x = $cx - $width / 2;
        foreach ($chars as $i => $char) {
            if ($char !== ' ') {
                imagettftext($img, $size, 0, (int) $x, (int) $baseline, $color, $font, $char);
            }
            $x += $boxes[$i][0] + $tracking;
        }
    }

    /**
     * Filled rounded rectangle; $corners picks which of [tl, tr, br, bl]
     * round — the card body passes bottom-only.
     */
    private static function fillRoundRect($img, float $x, float $y, float $w, float $h, float $r, int $color, array $corners = [true, true, true, true]): void
    {
        imagefilledrectangle($img, (int) ($x + $r), (int) $y, (int) ($x + $w - $r), (int) ($y + $h), $color);
        imagefilledrectangle($img, (int) $x, (int) ($y + $r), (int) ($x + $w), (int) ($y + $h - $r), $color);

        $d = $r * 2;
        if ($corners[0]) { imagefilledarc($img, (int) ($x + $r), (int) ($y + $r), (int) $d, (int) $d, 180, 270, $color, IMG_ARC_PIE); }
        if ($corners[1]) { imagefilledarc($img, (int) ($x + $w - $r), (int) ($y + $r), (int) $d, (int) $d, 270, 360, $color, IMG_ARC_PIE); }
        if ($corners[2]) { imagefilledarc($img, (int) ($x + $w - $r), (int) ($y + $h - $r), (int) $d, (int) $d, 0, 90, $color, IMG_ARC_PIE); }
        if ($corners[3]) { imagefilledarc($img, (int) ($x + $r), (int) ($y + $h - $r), (int) $d, (int) $d, 90, 180, $color, IMG_ARC_PIE); }
    }
}
