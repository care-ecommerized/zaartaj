<?php

namespace App\Catalog\Import;

class Encoding
{
    /**
     * Characters that only appear when UTF-8 has been decoded as a single-byte charset.
     *
     * "â" leads an em dash, "Ã" an accented Latin letter, "Ø"/"Ù" Arabic, "ð" a
     * four-byte emoji. Requiring one of these before touching anything keeps text
     * that is merely accented from being mangled.
     */
    private const MOJIBAKE_MARKERS = ['Â', 'Ã', 'â', 'Ø', 'Ù', 'Ú', 'ð', 'å', 'æ', 'ï¿½'];

    /**
     * Charsets to reverse through, in order of likelihood.
     *
     * ISO-8859-1 comes first because it round-trips the C1 range (0x80–0x9F) that
     * Windows-1252 leaves unmapped — and that range is exactly where the bytes of an
     * em dash or an Arabic letter land.
     */
    private const CHARSETS = ['ISO-8859-1', 'Windows-1252'];

    /**
     * Repair text that was written as UTF-8 and then read back as a single-byte charset.
     *
     * The Shopify export this importer consumes is double-encoded throughout: em
     * dashes arrive as "â", and Arabic words as "Ø¨Ø³ÙÙÙØ©". Left alone, every
     * affected title and description lands in the database corrupted.
     *
     * Repair repeats because a file can be double-encoded more than once, and stops
     * as soon as a pass fails to produce valid UTF-8.
     */
    public static function fix(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Input that is not valid UTF-8 at all is a different fault: transcode it
        // once and stop, rather than guessing at layered damage.
        if (! mb_check_encoding($value, 'UTF-8')) {
            return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        $passes = 0;

        while ($passes < 3 && self::looksDoubleEncoded($value)) {
            $repaired = self::repair($value);

            if ($repaired === null) {
                break;
            }

            $value = $repaired;
            $passes++;
        }

        return $value;
    }

    /**
     * Apply the repair across a whole row.
     *
     * @param  array<int|string, string|null>  $row
     * @return array<int|string, string|null>
     */
    public static function fixRow(array $row): array
    {
        return array_map(static fn (?string $value) => self::fix($value), $row);
    }

    /**
     * Undo one layer of decoding, or return null if this text isn't recoverable.
     */
    private static function repair(string $value): ?string
    {
        /*
         * Reversal only makes sense when every code point fits in a single byte.
         * Anything higher means the string holds genuine multi-byte characters, and
         * converting would replace them with "?" — real damage in the name of a fix.
         */
        if (preg_match('/^[\x{0000}-\x{00FF}]*$/u', $value) !== 1) {
            return null;
        }

        foreach (self::CHARSETS as $charset) {
            $candidate = @mb_convert_encoding($value, $charset, 'UTF-8');

            if (! is_string($candidate) || $candidate === '' || $candidate === $value) {
                continue;
            }

            // The recovered bytes have to *be* UTF-8, otherwise this was not mojibake.
            if (mb_check_encoding($candidate, 'UTF-8')) {
                return $candidate;
            }
        }

        return null;
    }

    private static function looksDoubleEncoded(string $value): bool
    {
        foreach (self::MOJIBAKE_MARKERS as $marker) {
            if (str_contains($value, $marker)) {
                return true;
            }
        }

        return false;
    }
}
