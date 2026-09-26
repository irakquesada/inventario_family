<?php

namespace App\Support;

class MapLinks
{
    private const DEFAULT_LOCATION = 'Valencia, Spain';

    public static function embedUrl(?string $configuredUrl, ?string $address): string
    {
        if (self::isEmbeddable($configuredUrl)) {
            return trim((string) $configuredUrl);
        }

        return self::googleSearchUrl($address ?: self::DEFAULT_LOCATION, true);
    }

    public static function directionsUrl(?string $configuredUrl, ?string $legacyUrl, ?string $address): string
    {
        if (filled($configuredUrl)) {
            return trim((string) $configuredUrl);
        }

        if (filled($legacyUrl) && ! self::isEmbeddable($legacyUrl)) {
            return trim((string) $legacyUrl);
        }

        return self::googleSearchUrl($address ?: self::DEFAULT_LOCATION);
    }

    public static function isEmbeddable(?string $url): bool
    {
        if (blank($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url(trim((string) $url));
        $host = strtolower($parts['host'] ?? '');
        $path = strtolower($parts['path'] ?? '');
        parse_str($parts['query'] ?? '', $query);

        $isGoogleHost = $host === 'google.com'
            || str_ends_with($host, '.google.com')
            || preg_match('/(^|\.)google\.[a-z.]+$/', $host) === 1;

        return $isGoogleHost
            && (str_contains($path, '/maps/embed') || ($query['output'] ?? null) === 'embed');
    }

    private static function googleSearchUrl(string $location, bool $embed = false): string
    {
        $url = 'https://www.google.com/maps?q='.rawurlencode(str_replace("\n", ', ', trim($location)));

        return $embed ? $url.'&output=embed' : $url;
    }
}
