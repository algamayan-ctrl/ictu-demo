<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a browser that opened the application through another hostname
 * (127.0.0.1 instead of localhost, a LAN alias, a raw IP) to the configured
 * APP_URL, so the session cookie, CSRF token, form actions and OAuth
 * callbacks all share one origin.
 *
 * - The destination origin comes only from APP_URL; the request supplies
 *   nothing but its own path and query string.
 * - Only the hostname is compared. Scheme and port are left to the HTTPS
 *   proxy (HSTS / Cloudflare), so a proxy that terminates TLS cannot cause
 *   a redirect loop. Forwarded host headers count only from TRUSTED_PROXIES.
 * - Only GET/HEAD are redirected. Any other method on a non-canonical host
 *   is refused before a controller runs, so no form data is replayed and no
 *   record is changed.
 * - /up stays reachable by IP for container and VPS health checks.
 */
class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.canonical_redirect')) {
            return $next($request);
        }

        $canonical = parse_url((string) config('app.url'));
        $canonicalScheme = strtolower((string) ($canonical['scheme'] ?? ''));
        $canonicalHost = strtolower((string) ($canonical['host'] ?? ''));

        if (! in_array($canonicalScheme, ['http', 'https'], true)
            || $canonicalHost === ''
            || $request->is('up')
            || strtolower($request->getHost()) === $canonicalHost) {
            return $next($request);
        }

        $origin = $canonicalScheme.'://'.$canonicalHost
            .(isset($canonical['port']) ? ':'.(int) $canonical['port'] : '');

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return redirect()->away($origin.'/'.ltrim($request->getRequestUri(), '/'), 302);
        }

        return response(
            '<!doctype html><meta charset="utf-8"><title>Open SPMU-ACPMP at its official address</title>'
            .'<p>This request was sent to an address other than the official SPMU-ACPMP address. Nothing was saved.</p>'
            .'<p><a href="'.e($origin.'/').'">Open SPMU-ACPMP</a> and try again.</p>',
            421,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
