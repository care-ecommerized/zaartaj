<?php

namespace App\Catalog;

/**
 * The effective ceilings for admin media uploads.
 *
 * config/catalog.php states what the shop wants to allow; PHP states what it
 * will actually accept. A configured 100 MB video on a php.ini with a 40 MB
 * `upload_max_filesize` never reaches Laravel at all — PHP discards the body,
 * the request arrives empty and the admin sees a session/CSRF error instead of
 * "too large". So every limit is reported as the smaller of the two, and the
 * validator, the error messages and the on-screen hints all read from here.
 */
class MediaLimits
{
    public static function imageMaxKb(): int
    {
        return (int) min((int) config('catalog.image_max_kb', 5120), self::phpFileCeilingKb());
    }

    public static function videoMaxKb(): int
    {
        return (int) min((int) config('catalog.video_max_kb', 102400), self::phpFileCeilingKb());
    }

    public static function imageBatchMax(): int
    {
        return (int) min((int) config('catalog.image_batch_max', 20), self::phpFileCountCeiling());
    }

    /**
     * The largest total request body PHP will accept, in kilobytes. A batch of
     * images is bounded by this rather than by any single file's limit.
     */
    public static function requestMaxKb(): int
    {
        $bytes = self::iniBytes('post_max_size');

        return $bytes > 0 ? intdiv($bytes, 1024) : PHP_INT_MAX;
    }

    /**
     * The limits as the admin screens want them: whole megabytes, camelCased.
     *
     * @return array<string, int>
     */
    public static function forProps(): array
    {
        return [
            'imageMaxMb' => self::toMb(self::imageMaxKb()),
            'videoMaxMb' => self::toMb(self::videoMaxKb()),
            'imageBatchMax' => self::imageBatchMax(),
            'requestMaxMb' => self::toMb(self::requestMaxKb()),
        ];
    }

    /**
     * The biggest single upload PHP allows: the smaller of upload_max_filesize
     * and post_max_size, since one file has to fit inside the whole body too.
     */
    private static function phpFileCeilingKb(): int
    {
        $limits = array_filter([
            self::iniBytes('upload_max_filesize'),
            self::iniBytes('post_max_size'),
        ], fn (int $bytes) => $bytes > 0);

        return $limits === [] ? PHP_INT_MAX : intdiv((int) min($limits), 1024);
    }

    private static function phpFileCountCeiling(): int
    {
        $max = (int) ini_get('max_file_uploads');

        return $max > 0 ? $max : PHP_INT_MAX;
    }

    /**
     * Parse a php.ini shorthand size ("40M", "1G") into bytes. 0 or "-1" mean
     * unlimited and are returned as 0 so callers can ignore them.
     */
    private static function iniBytes(string $directive): int
    {
        $value = trim((string) ini_get($directive));

        if ($value === '' || $value === '-1' || $value === '0') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private static function toMb(int $kilobytes): int
    {
        return $kilobytes === PHP_INT_MAX ? PHP_INT_MAX : (int) max(1, round($kilobytes / 1024));
    }
}
