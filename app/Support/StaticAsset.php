<?php

namespace App\Support;

/**
 * Origin-relative URLs for files under public/ that the browser loads into
 * an application page (stylesheets, scripts, logos, backgrounds).
 *
 * asset() follows the forced APP_URL root, which emails, OAuth, signed links
 * and redirects need. A page opened through another hostname (127.0.0.1
 * instead of localhost, or a proxy alias) would then point its own CSS and
 * images at a different origin, which the 'self' Content-Security-Policy
 * blocks. A path such as /css/app.css always resolves against the origin the
 * page was actually served from. The request base path keeps subdirectory
 * installs working; the Host header is never used.
 */
class StaticAsset
{
    public static function url(string $path): string
    {
        $basePath = app()->bound('request') ? rtrim((string) request()->getBasePath(), '/') : '';

        return $basePath.'/'.ltrim($path, '/');
    }
}
