# Authentication on other devices

Confirmed behavior: verified Google identity → employee/external registration with locked email → SAO; manual sign-up → email OTP → SAO. Google proves identity, never grants a role or SAO approval. CSPC employee registration, manual or through Google, requires an address in `INSTITUTIONAL_EMAIL_DOMAINS` (default `cspc.edu.ph`); a student address such as `@my.cspc.edu.ph` is treated as External unless the institution adds that domain. Gmail, Yahoo and government addresses work with Google only if they are registered Google accounts. Google owns the credential screen; SPMU never asks for the Google password.

## Current local review

Use `http://localhost:8080`, with callback `http://localhost:8080/auth/google/callback`. The server uses a separate fresh MariaDB database and private provider configuration; the old database and storage are retained. Localhost refers to the device opening the page. It is not a public or multi-device address.

In Google Cloud Console → Google Auth Platform → Clients → the configured Web application client, register that exact callback under Authorized redirect URIs, then save. Application URL, callback, protocol, hostname, port and path must agree. Authorized JavaScript origins alone do not register a server callback. If the consent application is in testing, ensure the intended Google accounts are permitted test users. A successful outgoing redirect proves only application configuration; the real consent/callback must still be verified.

## Public deployment sequence

1. Obtain institutional approval of hosting and a real HTTPS application hostname. For the documented Docker stack and document conversion dependencies, verify the selected Hostinger plan supports the required PHP/runtime/container services; a VPS can run this stack, while a shared-hosting plan must be checked separately. Laravel Cloud must also be checked against the approved MariaDB 11.4 and document-runtime requirements. This guide is not ICTU approval.
2. Configure DNS and a valid origin certificate. If Cloudflare proxies the site, use Full (strict) with a valid origin certificate. Cloudflare does not configure Laravel, SMTP or Google OAuth.
3. Set `APP_URL=https://<approved-hostname>` and `GOOGLE_REDIRECT_URI=https://<approved-hostname>/auth/google/callback` in protected server configuration. Add the exact public callback to the same Google Web client. Keep development and production clients separate where practical.
4. Configure the actual `TRUSTED_PROXIES` addresses used by the hosting platform; do not trust arbitrary forwarded headers. Use `APP_ENV=production`, `APP_DEBUG=false`, secure encrypted database/shared sessions, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, and appropriate host-only cookie settings. Keep `SEED_DEMO_USERS=false`; create authorized production staff accounts separately.
5. Configure real SMTP/provider credentials and an authorized sender. On port 587 use `MAIL_SCHEME=smtp` with STARTTLS; on port 465 use `smtps`. Use a delivery-capable mailer rather than log/array. Configure SPF/DKIM/DMARC as required by the email provider. Never copy credentials into documentation or Git.
6. Keep persistent private uploads, MariaDB storage, shared cache/session data, queue workers and the Laravel scheduler available. Take and test database plus file backups. Rebuild the container after source changes; this review image has no source bind mount.
7. Refresh configuration/routes/views, restart workers, and run `php artisan spmu:auth-readiness --json --check-smtp`. The SMTP probe sends no message and does not establish inbox delivery.
8. Test actual Google employee and external onboarding, denial/state failure, locked email/type, existing linked accounts, manual OTP receipt/expiry/replay, forgot-password receipt/token expiry, and SAO access controls. Repeat on a second device using the public HTTPS address. Record exact results before allowing real borrowers.

## Required private actions and evidence

Rotate the OAuth client secret, Gmail app password and any reused database credentials that were pasted into chat before production use. The new isolated database has newly generated credentials and its own application key. Never replace an existing production application key without a data/key migration plan.

Actual Google client settings require its owner's access. Actual OTP/reset receipt needs an authorized test mailbox and confirmation. Automated mocked-provider/array-mail tests, SMTP authentication and inbox receipt are separate evidence. Hosting alone will not correct a redirect URI mismatch.

Sources: [Google server OAuth documentation](https://developers.google.com/identity/protocols/oauth2/web-server), [Cloudflare Full (strict)](https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/), [Hostinger VPS guidance](https://support.hostinger.com/en/collections/944797-vps).
