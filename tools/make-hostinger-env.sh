#!/usr/bin/env bash
# Writes the production .env.docker for the Hostinger + Cloudflare deployment.
# Run on the VPS from the project folder:  bash tools/make-hostinger-env.sh
# Database passwords and APP_KEY are generated here, on the server. The Google
# client secret and Gmail App Password are typed in hidden and never echoed.
# See docs/DEPLOYMENT-HOSTINGER-CLOUDFLARE.md, section 8.
set -euo pipefail

cd "$(dirname "$0")/.."

if [ -e .env.docker ]; then
    backup=".env.docker.before-$(date +%Y%m%d-%H%M%S)"
    cp -p .env.docker "$backup"
    chmod 600 "$backup"
    echo "Existing .env.docker saved as $backup"
    echo "WARNING: if the database was already created, its passwords and APP_KEY"
    echo "must NOT change. Press Ctrl+C now to cancel, or Enter to continue."
    read -r
fi

read -rp "Public domain without https:// [spmu-acpmp.tech]: " domain
domain="${domain:-spmu-acpmp.tech}"
domain="${domain#https://}"
domain="${domain#http://}"
domain="${domain%%/*}"

read -rp "Google OAuth Client ID: " google_client_id
read -rsp "NEW Google OAuth Client Secret (hidden): " google_client_secret; echo
read -rp "Gmail sender address [spmuacpmp@gmail.com]: " mail_user
mail_user="${mail_user:-spmuacpmp@gmail.com}"
read -rsp "NEW Gmail App Password, 16 letters (hidden): " mail_password; echo
mail_password="${mail_password// /}"

for value in "$google_client_id" "$google_client_secret" "$mail_password"; do
    if [ -z "$value" ]; then
        echo "A required value was empty. Nothing was written." >&2
        exit 1
    fi
done
if [ "${#mail_password}" -ne 16 ]; then
    echo "Note: Gmail App Passwords are 16 letters; yours has ${#mail_password}." >&2
fi

app_key="base64:$(openssl rand -base64 32)"
db_password="$(openssl rand -hex 24)"
db_root_password="$(openssl rand -hex 24)"

umask 077
cat > .env.docker <<EOF
APP_NAME="SPMU-ACPMP"
APP_ENV=production
APP_KEY=${app_key}
APP_DEBUG=false
APP_URL=https://${domain}
APP_TIMEZONE=Asia/Manila
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file
SEED_DEMO_USERS=false
RUN_MIGRATIONS=true

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=database
DB_PORT=3306
DB_DATABASE=spmu_acpmp
DB_USERNAME=spmu_application
DB_PASSWORD=${db_password}
MARIADB_DATABASE=spmu_acpmp
MARIADB_USER=spmu_application
MARIADB_PASSWORD=${db_password}
MARIADB_ROOT_PASSWORD=${db_root_password}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_IDLE_TIMEOUT=15
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=null
SESSION_PATH=/
# The Docker network cloudflared runs on (docker-compose.hostinger.yml).
TRUSTED_PROXIES=172.30.0.0/24
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
PROTECTED_FILES_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_TIMEOUT=15
MAIL_USERNAME=${mail_user}
MAIL_PASSWORD=${mail_password}
MAIL_FROM_ADDRESS=${mail_user}
MAIL_FROM_NAME="SPMU-ACPMP"

GOOGLE_CLIENT_ID=${google_client_id}
GOOGLE_CLIENT_SECRET=${google_client_secret}
GOOGLE_REDIRECT_URI=https://${domain}/auth/google/callback
GOOGLE_ALLOWED_DOMAINS=
INSTITUTIONAL_EMAIL_DOMAINS=cspc.edu.ph
EOF
chmod 600 .env.docker

echo
echo "Wrote .env.docker for https://${domain}"
echo "Store a copy of this APP_KEY in a password manager (never in Git or chat):"
echo "  grep '^APP_KEY=' .env.docker"
echo
echo "Google Cloud Console must list exactly:"
echo "  JavaScript origin: https://${domain}"
echo "  Redirect URI:      https://${domain}/auth/google/callback"
echo
echo "Next: docker compose up -d --build   (with COMPOSE_FILE set, guide step 9.2)"
