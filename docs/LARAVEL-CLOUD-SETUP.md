# Laravel Cloud setup and remaining acceptance

Status: FOR REVIEW; deployment gate NOT READY. No Cloud application, database provider, bucket, OAuth client, SMTP service, worker or production resource has been created or certified.

## Tested application direction

Retain PHP8.4, Laravel13, Bootstrap5.3.8, approved MariaDB11.4 and Docker development. The locked security update selects Laravel13.35.0, CommonMark2.10.3 and Flysystem3.36.0; the private S3 adapter is now a locked dependency. Actual installation/platform/audit and regression receipts must be attached before accepting these versions.

Cloud documents managed MySQL/Postgres; this does not authorize replacing MariaDB11.4. Use only an institution-approved MariaDB provider after connection/TLS, networking, least-privilege access and recovery checks. [Cloud databases](https://laravel.com/cloud/docs/resources/databases/laravel-mysql).

## Configuration to review for an approved staging environment

- PHP runtime8.4; locked Composer install and the existing Vite build. Current Bootstrap/JS public assets remain part of the established ZIP presentation.
- HTTPS `APP_URL`, stable private `APP_KEY`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, shared database/Redis sessions and cache/locks, durable database/Redis queues.
- `PROTECTED_FILESYSTEM_DISK=s3` with a private bucket and least-privilege credentials; existing StoredFile rows keep their original disk. Copy/verify existing local evidence before changing its metadata or removing a disk. The service checks byte count/hash and cleans new objects on database rollback.
- Scheduler enabled and actually observed executing the existing `spmu:process-deadlines`; shared cache supports `onOneServer()` and overlap locks. It must not automatically cancel missed pickups. Workers/retries/email delivery need separate observed execution; the current NotificationService has synchronous/after-commit delivery, so a configured queue alone does not prove asynchronous email processing.
- Approved email transport and actual delivery/OTP/password-reset checks. Local array-mail tests cannot certify SMTP.
- Google local OAuth callback must match the configured client exactly; password login remains fallback. Account Settings links the matching Google email after current-password confirmation and an expiring stateful flow. A stored provider subject, not a matching email alone, authenticates later Google logins. Registration/SAO approval and local role checks still apply. [Google identity guidance](https://developers.google.com/identity/openid-connect/openid-connect).

Use `php artisan spmu:deployment-readiness` for non-secret, read-only configuration checks. Its PASS result, if obtained, applies only to the listed configuration checks, not institutional/provider/user-evaluation gates.

## Runtime and migration checks still required

Run Composer platform checks and audit, Vite build, backed-up additive migrations and an isolated role/workflow regression. Review the new `oauth_identities` migration: one Google subject belongs to only one user and a user has one Google identity. It stores no OAuth token/client secret. Do not automatically link existing email matches during migration.

Cloud local files are ephemeral and replicas do not share them. [Deploy guide](https://laravel.com/cloud/docs/deploy-guides/laravel), [object storage](https://laravel.com/cloud/docs/resources/object-storage). Protected StoredFile evidence now supports a configurable disk; profile images still use local paths and need a separately tested persistent adapter/migration. Built-in outputs and active uploaded DOCX/XLSX template conversion must be tested on the actual runtime; LibreOffice availability is not established on ordinary Cloud hosting. Docker Compose is not evidence of a Cloud build.

Do not perform a Cloud deployment until the original Hosting Requirements, nine-deliverable ICTU letter, missing KPI/inventory references, approved MariaDB/Cloud architecture, backup/restore policy, scheduler/email/shared-session evidence and quality evaluation gates are reconciled. The reported TD RSC “Bolivia” title mismatch remains unverified because the PDF is unavailable here; it cannot certify SPMU approval.
