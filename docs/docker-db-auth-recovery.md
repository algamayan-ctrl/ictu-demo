# Database authentication recovery

The existing spmu_application@% account in MariaDB was inspected and its password aligned with the existing DB_PASSWORD configured in .env.docker. Its database grants and root credentials were preserved.

A physical database-volume backup was made while MariaDB was stopped:
BACKUP-db-auth-20261012-062245/database-volume.tar.gz

The temporary recovery server had networking disabled and was stopped and removed after repair. The original database volume remains in use.

RUN_MIGRATIONS=false is now set in .env.docker to prevent automatic migrations and seeding on app startup. Apply migrations or seeding separately only when intended.

Verified: application database connection, optimize:clear, config:cache, route:cache, view:cache, HTTP /up and /login (200).

The original root password remains unknown; no root password reset was performed. Keep the backup private because it contains database data and account records.