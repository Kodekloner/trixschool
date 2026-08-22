<?php

if (!function_exists('result_download_validate_image_proxy_url')) {
    /**
     * Return a normalized, fetchable SchoolLift asset URL or null.
     * Keeping the host allowlist exact prevents this image helper becoming an
     * open proxy or an SSRF route into a tenant server's private network.
     */
    function result_download_validate_image_proxy_url($rawUrl)
    {
        $rawUrl = trim((string) $rawUrl);
        if ($rawUrl === '' || strlen($rawUrl) > 2048) {
            return null;
        }

        $parts = parse_url($rawUrl);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'schoollift.s3.us-east-2.amazonaws.com'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['fragment'])
            || empty($parts['path'])
            || $parts['path'] === '/'
        ) {
            return null;
        }

        return 'https://schoollift.s3.us-east-2.amazonaws.com'
            . $parts['path']
            . (isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '');
    }
}

if (!function_exists('result_download_detect_proxy_image_type')) {
    /** Return an allowlisted raster MIME type, or null for non-image content. */
    function result_download_detect_proxy_image_type($contents)
    {
        if (!is_string($contents) || $contents === '') {
            return null;
        }

        $mimeType = null;
        if (function_exists('finfo_open')) {
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($fileInfo !== false) {
                $mimeType = finfo_buffer($fileInfo, $contents);
                finfo_close($fileInfo);
            }
        }

        if (!is_string($mimeType) || strpos($mimeType, 'image/') !== 0) {
            if (strncmp($contents, "\x89PNG\r\n\x1A\n", 8) === 0) {
                $mimeType = 'image/png';
            } elseif (strncmp($contents, "\xFF\xD8\xFF", 3) === 0) {
                $mimeType = 'image/jpeg';
            } elseif (strncmp($contents, 'GIF87a', 6) === 0 || strncmp($contents, 'GIF89a', 6) === 0) {
                $mimeType = 'image/gif';
            } elseif (strlen($contents) >= 12
                && strncmp($contents, 'RIFF', 4) === 0
                && substr($contents, 8, 4) === 'WEBP'
            ) {
                $mimeType = 'image/webp';
            } elseif (strncmp($contents, 'BM', 2) === 0) {
                $mimeType = 'image/bmp';
            }
        }

        $allowedTypes = array(
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
        );

        return in_array($mimeType, $allowedTypes, true) ? $mimeType : null;
    }
}
