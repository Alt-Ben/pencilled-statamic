<?php

namespace Pencilled\Statamic\Support;

/**
 * Second-layer sanitiser for description HTML arriving from the Pencilled
 * API. Pencilled sanitises at source, but synced markup is rendered raw by
 * site templates, so it is reduced to the same allowlist again here — a
 * compromised feed must then beat both layers.
 */
class Sanitiser
{
    private const ALLOWED = '<p><br><strong><em><ul><ol><li><a>';

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        // strip_tags() keeps inner text, so remove elements whose content is
        // as unwanted as the tags themselves before stripping.
        $html = (string) preg_replace('#<(script|style|iframe|object|embed|svg|math|template|noscript)\b[^>]*>.*?</\1\s*>#is', '', $html);

        $html = strip_tags($html, self::ALLOWED);

        // Drop every attribute from the kept tags; <a> keeps only a
        // validated http(s) href and a forced rel.
        $html = (string) preg_replace('/<(p|br|strong|em|ul|ol|li)\b[^>]*>/i', '<$1>', $html);

        $html = (string) preg_replace_callback('/<a\b[^>]*>/i', function (array $match): string {
            if (! preg_match('/href\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $match[0], $href)) {
                return '';
            }

            $url = html_entity_decode($href[2] !== '' ? $href[2] : ($href[3] ?? ''), ENT_QUOTES);

            if (! preg_match('#^https?://#i', trim($url))) {
                return '';
            }

            $escaped = htmlspecialchars(trim($url), ENT_QUOTES);

            return '<a href="'.$escaped.'" rel="nofollow noopener">';
        }, $html);

        $html = trim($html);

        return trim(strip_tags($html)) === '' ? null : $html;
    }
}
