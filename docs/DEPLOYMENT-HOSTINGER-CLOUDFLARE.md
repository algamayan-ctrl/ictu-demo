# Deploying SPMU-ACPMP on Hostinger with Cloudflare

This guide takes the system from this repository to a public HTTPS address on a Hostinger VPS, with Cloudflare in front of it. Once it is complete, Google sign-in, Google registration, OTP email verification and password reset work from any device, because every link and callback uses one public address.

Related documents: [AUTH-PUBLIC-DEPLOYMENT.md](AUTH-PUBLIC-DEPLOYMENT.md) (authentication rules), [GOOGLE-EMAIL-SETUP.md](GOOGLE-EMAIL-SETUP.md), [DEPLOYMENT-ICTU.md](DEPLOYMENT-ICTU.md) (operations), [BACKUP-RESTORE-RUNBOOK.md](BACKUP-RESTORE-RUNBOOK.md).

> This guide covers the technical setup only. It does not replace any approval CSPC or ICTU requires before real borrowers use the system.

---

## 1. Why a public HTTPS address is needed

On a laptop, `APP_URL` is something like `http://127.0.0.1:8765` or `http://localhost:8080`. That address always means "this same device", so:

| Feature | On the laptop | On another device (before deployment) | After this guide |
|---|---|---|---|
| Google sign-in / registration | Works | Fails. Google only accepts `localhost` or a public HTTPS callback, never a LAN IP such as `192.168.1.5`. | Works |
| OTP email verification | Works | The code works, but links in the email point to `127.0.0.1` | Works |
| Password reset link | Works | The link opens `127.0.0.1` on the phone, which is the phone itself | Works |

OTP codes and reset tokens are stored in the **database**, not in the browser session. Once the address is public, a user can request a reset on a laptop and open the link on a phone, or register on one device and enter the OTP on another.

---

## 2. Architecture

```
Phone / Laptop ──HTTPS──► Cloudflare (DNS + TLS + Tunnel)
                                 │  outbound-only tunnel, no open ports
                                 ▼
                    Hostinger VPS (Ubuntu 24.04 + Docker)
            ┌────────────────────────────────────────────────┐
            │ cloudflared ──► app (Apache + PHP 8.4, :80)    │
            │                  ├── queue      (queue:work)   │
            │                  ├── scheduler  (schedule:work)│
            │                  └── database   (MariaDB 11.4) │
            └────────────────────────────────────────────────┘
                   Docker network 172.30.0.0/24
```

- **Cloudflare Tunnel** (`cloudflared`) connects *out* from the VPS to Cloudflare. No web ports need to be open on the VPS, and Cloudflare provides the HTTPS certificate automatically.
- The app is published only on `127.0.0.1:8080`, so it can't be reached directly from the internet.
- MariaDB is never published.

---

## 3. Choose and buy the Hostinger plan

**Choose a KVM VPS. Do not use shared hosting.** Premium, Business and Cloud hosting cannot run Docker, the always-on queue worker and scheduler, LibreOffice document conversion, or MariaDB 11.4 under your control.

| Plan | Specs | Fit |
|---|---|---|
| KVM 1 | 1 vCPU, 4 GB RAM, 50 GB NVMe | Too tight. Image builds (LibreOffice, Tesseract) and MariaDB on one CPU are slow and can run out of memory. |
| **KVM 2 (recommended)** | 2 vCPU, 8 GB RAM, 100 GB NVMe | Runs all containers comfortably, with room for a defense demo with many simultaneous users. |
| KVM 4 | 4 vCPU, 16 GB RAM, 200 GB NVMe | More than a capstone needs. |

Prices change; check the checkout page. Promo prices need a long prepaid term, and renewal is higher. Every plan includes weekly backups and a free domain for the first year.

During checkout or setup:

1. **Operating system:** choose the template **Ubuntu 24.04 with Docker** (under *OS with Application*). If it isn't offered, choose plain Ubuntu 24.04 and install Docker in step 5.3.
2. **Data center:** choose the closest one to the Philippines that is offered (usually Malaysia, Singapore or India).
3. **Root password / SSH key:** set a strong root password. Adding an SSH key is better.
4. **Free domain:** claim it (for example `spmu-acpmp.com`). This guide calls it `yourdomain.com`.

Write down the VPS **IP address** from hPanel → VPS → Overview.

---

## 4. Connect the domain to Cloudflare

1. Create a free account at <https://dash.cloudflare.com> and click **Add a domain**. Enter `yourdomain.com` and choose the **Free** plan.
2. Cloudflare shows two **nameservers** (for example `ada.ns.cloudflare.com`, `bob.ns.cloudflare.com`).
3. In Hostinger **hPanel → Domains → yourdomain.com → DNS / Nameservers → Change nameservers**, select *Use custom nameservers* and enter the two Cloudflare nameservers. Save.
4. Wait until Cloudflare shows the domain as **Active**. This usually takes minutes, but can take up to 24 hours.
5. In Cloudflare, for this domain:
   - **SSL/TLS → Overview:** set the mode to **Full (strict)**.
   - **SSL/TLS → Edge Certificates:** turn on **Always Use HTTPS**.
   - **Speed → Optimization:** turn **Rocket Loader off**. It rewrites page JavaScript and can break forms.
   - **Scrape Shield:** turn **Email Address Obfuscation off**. It alters pages that display email addresses.
6. **Pick one address and use it everywhere.** This guide uses `https://yourdomain.com` (no `www`). Add a redirect so `www` goes to it: **Rules → Redirect Rules → Create rule → "Redirect from WWW to root"** template. Do this because sign-in only works on the exact `APP_URL` host. A user arriving on another host (such as `www.`) is sent to `APP_URL` before Google sign-in starts.

---

## 5. Prepare the VPS

### 5.1 Log in

From your laptop (PowerShell or the VS Code terminal):

```bash
ssh root@YOUR_VPS_IP
```

### 5.2 Update and create a deploy user

```bash
apt update && apt -y upgrade
```

```bash
adduser deploy
```

```bash
usermod -aG sudo,docker deploy
```

Log out, then log back in as `deploy`:

```bash
ssh deploy@YOUR_VPS_IP
```

### 5.3 Check Docker

```bash
docker --version && docker compose version
```

Docker Compose must be **v2.24.4 or newer**, because the add-on file uses `!override`. Versions v3, v4 and v5 (for example `v5.6.0`) are all newer and fine. If Docker is missing, install it from the official guide: <https://docs.docker.com/engine/install/ubuntu/>.

### 5.4 Firewall

The tunnel only makes outbound connections, so open only SSH:

```bash
sudo ufw allow OpenSSH && sudo ufw enable
```

In **hPanel → VPS → Security → Firewall**, you may also allow only port 22 inbound.

> Docker-published ports bypass UFW. That is why `docker-compose.hostinger.yml` publishes the app only on `127.0.0.1`.

---

## 6. Get the code onto the VPS

The repository is private, so create a **read-only deploy key**:

```bash
ssh-keygen -t ed25519 -C "spmu-vps" -f ~/.ssh/spmu_deploy -N ""
```

```bash
cat ~/.ssh/spmu_deploy.pub
```

On GitHub, open the repository → **Settings → Deploy keys → Add deploy key**. Paste the key and leave *Allow write access* **unchecked**.

```bash
printf 'Host github.com\n  IdentityFile ~/.ssh/spmu_deploy\n' >> ~/.ssh/config
```

```bash
sudo mkdir -p /opt/spmu && sudo chown deploy:deploy /opt/spmu
```

```bash
git clone git@github.com:arbasagre-ctrl/capstone-spmu-acpmp.git /opt/spmu
```

```bash
cd /opt/spmu && git switch main
```

---

## 7. Create the Cloudflare Tunnel

1. In Cloudflare, open **Zero Trust** (left menu). The first time, choose a team name and the **Free** plan.
2. Go to **Networks → Tunnels → Create a tunnel → Cloudflared**. Name it `spmu-acpmp`.
3. On the *Install connector* page, choose **Docker** and copy only the **token**: the long value after `--token`.
4. On the VPS, save it in `tunnel.env`. This file is git-ignored and Docker-ignored, so it never enters Git or the app image.

   ```bash
   cd /opt/spmu && printf 'TUNNEL_TOKEN=PASTE_TOKEN_HERE\n' > tunnel.env && chmod 600 tunnel.env
   ```

5. Back in Cloudflare, click **Next → Public Hostnames → Add a public hostname**:
   - Subdomain: *(leave empty)*
   - Domain: `yourdomain.com`
   - Service type: **HTTP**
   - URL: **`app:80`** (the Docker service name, not `localhost`)

   Save. Cloudflare creates the DNS record for you.

---

## 8. Configure the application (`.env.docker`)

```bash
cd /opt/spmu && cp .env.docker.example .env.docker && chmod 600 .env.docker
```

```bash
nano .env.docker
```

Set every value below. Leave the other values from the example as they are.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_KEY=                       # generated in step 9.1
SEED_DEMO_USERS=false          # never demo accounts in production
RUN_MIGRATIONS=true            # first start; see DEPLOYMENT-ICTU.md for later

DB_PASSWORD=<strong random password>
MARIADB_PASSWORD=<same as DB_PASSWORD>
MARIADB_ROOT_PASSWORD=<different strong random password>

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
TRUSTED_PROXIES=172.30.0.0/24  # the Docker network that cloudflared lives on

QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=spmuacpmp@gmail.com
MAIL_PASSWORD=<Gmail App Password, 16 characters, no spaces>
MAIL_FROM_ADDRESS=spmuacpmp@gmail.com
MAIL_FROM_NAME="SPMU-ACPMP"

GOOGLE_CLIENT_ID=<from Google Cloud Console>
GOOGLE_CLIENT_SECRET=<from Google Cloud Console>
GOOGLE_REDIRECT_URI=https://yourdomain.com/auth/google/callback
GOOGLE_ALLOWED_DOMAINS=
INSTITUTIONAL_EMAIL_DOMAINS=cspc.edu.ph
```

Rules that matter for sign-in on multiple devices:

- **`APP_URL` and `GOOGLE_REDIRECT_URI` must match exactly**: same `https`, same host, no trailing slash. Write the full URL rather than `${APP_URL}/...`.
- **`TRUSTED_PROXIES=172.30.0.0/24` is required.** Without it, Laravel thinks requests arrive over `http`. That makes them differ from `APP_URL`, so `/auth/google` keeps redirecting to itself and secure cookies fail. The range is fixed by `docker-compose.hostinger.yml`.
- `EMAIL_VERIFICATION_BASE_URL`, which older documents mention, is **no longer used**. OTP, reset and Google links are all built from `APP_URL`.
- Generate passwords with `openssl rand -base64 24`.
- **Rotate secrets before going live.** The current Google client secret and Gmail App Password were pasted into chats during development. Create new ones (steps 10 and 11) and use only the new ones here.

---

## 9. First start

### 9.1 Generate the application key

```bash
docker compose -f docker-compose.yml -f docker-compose.hostinger.yml build app
```

```bash
docker compose -f docker-compose.yml -f docker-compose.hostinger.yml run --rm --no-deps -e RUN_MIGRATIONS=false app php artisan key:generate --show
```

Copy the printed `base64:...` value into `APP_KEY=` in `.env.docker`. **Keep a copy somewhere safe.** If it is lost, encrypted sessions and data become unreadable. Never change it on a running system.

### 9.2 Start everything

To avoid repeating the two `-f` options, set this once per login session (or add it to `~/.bashrc`):

```bash
export COMPOSE_FILE=docker-compose.yml:docker-compose.hostinger.yml
```

```bash
cd /opt/spmu && docker compose up -d --build
```

```bash
docker compose ps
```

All five services (`database`, `app`, `queue`, `scheduler`, `cloudflared`) should be `Up`, and `app` should be `(healthy)`. On first start, the app runs the migrations and loads the reference data (`spmu:bootstrap`).

### 9.3 Create the first ICTU account

```bash
docker compose exec app php artisan spmu:user ictu.admin@cspc.edu.ph --name="ICTU Administrator" --employee-no="REAL-EMPLOYEE-NO" --unit=ICTU --role=ICTU
```

It prompts for a 12+ character password (upper, lower, number, symbol). That account then creates the other staff accounts in **User Accounts**. See [DEPLOYMENT-ICTU.md](DEPLOYMENT-ICTU.md) step 8.

---

## 10. Google Cloud Console (Google sign-in and registration)

Open <https://console.cloud.google.com> → select the project → **Google Auth Platform**.

1. **Clients → your Web application client** (or create one; type *Web application*):
   - **Authorized JavaScript origins:** `https://yourdomain.com`
   - **Authorized redirect URIs:** `https://yourdomain.com/auth/google/callback`
   - Save. Changes can take a few minutes to apply.
   - Create a **new client secret** (rotation) and put it in `.env.docker`. Delete the old one once the new one works.
   - You may keep `http://localhost:8080/auth/google/callback` for local development. A separate development client is cleaner.
2. **Branding:** fill in the app name, support email, and app home page `https://yourdomain.com`, and add `yourdomain.com` under **Authorized domains**.
3. **Audience:**
   - *Testing* status: only Google accounts listed under **Test users** (max 100) can sign in. Add every panelist's and tester's Gmail.
   - *In production* (**Publish app**): any Google account can sign in. The system only requests `openid`, `email` and `profile`, which are non-sensitive scopes, so no lengthy Google verification is required.
4. **Data access:** confirm only `openid`, `.../auth/userinfo.email` and `.../auth/userinfo.profile` are listed.

Google proves identity only. A new Google user still goes through registration and SAO approval, exactly as on localhost.

---

## 11. Email for OTP and password reset

OTP and reset emails are sent **immediately during the request** (not through the queue), using the SMTP settings in `.env.docker`.

**Gmail (fine for a capstone, about 500 messages/day):**

1. Sign in to `spmuacpmp@gmail.com` → **Google Account → Security** → turn on **2-Step Verification**.
2. **Security → App passwords** → create one named `SPMU VPS`. Copy the 16 characters, without spaces, into `MAIL_PASSWORD`.
3. Revoke the old App Password that was used during development.

**Institutional mail (later):** ask ICTU for SMTP host, port and credentials, and for the sending domain's SPF/DKIM records. Then change `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` and `MAIL_FROM_ADDRESS`.

Apply any `.env.docker` change with:

```bash
docker compose up -d --force-recreate app queue scheduler
```

---

## 12. Verify

### 12.1 Configuration check on the server

```bash
docker compose exec app php artisan spmu:auth-readiness --json --check-smtp
```

It must show `"gate": "PASS"` and `"SMTP connection/authentication": true`. To send one real test email:

```bash
docker compose exec app php artisan spmu:auth-readiness --send-test-to=your.name@gmail.com
```

Also run:

```bash
docker compose exec app php artisan spmu:deployment-readiness --json
```

### 12.2 Real test on two devices

Use **Device A** (laptop on Wi-Fi) and **Device B** (phone on **mobile data**, not the same Wi-Fi, to prove the address is truly public). Record the results.

| # | Test | Expected |
|---|---|---|
| 1 | Open `https://yourdomain.com` on both | Padlock shown, login page loads |
| 2 | Open `https://www.yourdomain.com` | Redirects to `https://yourdomain.com` |
| 3 | B: **Continue with Google** with a Gmail test account | Google consent screen, then back to the system (registration or dashboard) |
| 4 | A: manual sign-up at `/register` | OTP email arrives; enter the code on **B** and it is accepted |
| 5 | Wrong OTP 5 times, or an OTP older than 10 minutes | Rejected with a clear message; a new OTP can be requested |
| 6 | A: **Forgot password** | Reset email arrives; open the link on **B**, set a new password, sign in on **A** with it |
| 7 | Reuse the same reset link, or use one older than 60 minutes | Rejected |
| 8 | SAO approves the pending registration | The user can sign in on both devices |
| 9 | Stay idle past the session timeout | Signed out, can sign back in |

---

## 13. Updating the system later

```bash
cd /opt/spmu && git pull
```

```bash
docker compose up -d --build
```

```bash
docker compose ps && docker compose logs --tail=50 app
```

- With `RUN_MIGRATIONS=true`, new migrations run automatically on restart. For planned updates, ICTU may prefer `false` and run `docker compose exec app php artisan migrate --force` deliberately. See *RUN_MIGRATIONS by situation* in [DEPLOYMENT-ICTU.md](DEPLOYMENT-ICTU.md).
- **Back up before every update** (section 14).
- Never run `docker compose down -v`. The `-v` option deletes the database and uploaded files.

---

## 14. Backups

- **Hostinger:** weekly automatic backups are included. Before risky changes, also take a manual **Snapshot** (hPanel → VPS → Snapshots & Backups).
- **Application level:** follow [BACKUP-RESTORE-RUNBOOK.md](BACKUP-RESTORE-RUNBOOK.md) to back up the MariaDB database and the `application_storage` volume (signatures, uploaded evidence, generated documents). Copy backups **off the VPS**, for example to Google Drive.
- Keep `APP_KEY`, `.env.docker` and `tunnel.env` in a password manager, never in Git.

---

## 15. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Google: **Error 400: redirect_uri_mismatch** | Callback in Google Console ≠ `GOOGLE_REDIRECT_URI` | Make both exactly `https://yourdomain.com/auth/google/callback`, then wait a few minutes |
| `/auth/google` reloads endlessly or "too many redirects" | `TRUSTED_PROXIES` missing or wrong, so Laravel sees `http` | Set `TRUSTED_PROXIES=172.30.0.0/24`, then recreate `app` (section 11). Check that the network subnet is `172.30.0.0/24` (`docker network inspect spmu_default`). |
| "Google sign-in needs server configuration" | `GOOGLE_REDIRECT_URI` ≠ `APP_URL` + `/auth/google/callback` | Fix `.env.docker`, then recreate `app` |
| Google: **Access blocked / app has not completed verification** | Consent screen in *Testing* and the account is not a test user | Add the account under **Test users**, or **Publish app** |
| **419 Page Expired** after a form or Google | Cookie not kept, usually `SESSION_SECURE_COOKIE=true` without trusted HTTPS, or a host other than `APP_URL` | Fix `TRUSTED_PROXIES`, add the `www` redirect, and use only `APP_URL` |
| OTP or reset email never arrives | SMTP login failed, or the mail landed in Spam | Run `spmu:auth-readiness --check-smtp`; check Spam; regenerate the Gmail App Password |
| Reset link points to `127.0.0.1` or `localhost` | Old `APP_URL`, or the config cache was built before the change | Fix `APP_URL` and recreate `app` (the entrypoint rebuilds the config cache) |
| Cloudflare **Error 1033** or **502** | Tunnel or app not running | `docker compose ps`; `docker compose logs cloudflared app`; in the Cloudflare tunnel, the service must be `http://app:80` |
| Changed `docker-compose.hostinger.yml` network but nothing changed | The Docker network already existed with the old subnet | `docker compose down` (**without** `-v`), then `docker compose up -d` |
| Container `app` is `unhealthy` | Database not ready, or a wrong DB password | `docker compose logs database app`; `DB_PASSWORD` must equal `MARIADB_PASSWORD` |

---

## 16. Go-live checklist

- [ ] KVM 2 VPS running Ubuntu 24.04 + Docker; only SSH open
- [ ] Domain **Active** on Cloudflare; SSL Full (strict); Always Use HTTPS; `www` redirect
- [ ] Tunnel healthy; public hostname points to `http://app:80`
- [ ] `.env.docker`: production values, `APP_DEBUG=false`, `SEED_DEMO_USERS=false`, `TRUSTED_PROXIES=172.30.0.0/24`
- [ ] New Google client secret and new Gmail App Password; old ones revoked
- [ ] Google Console: exact origin and callback; test users added, or app published
- [ ] `spmu:auth-readiness --check-smtp` → PASS; test email received
- [ ] Section 12.2 two-device tests all pass (phone on mobile data)
- [ ] First ICTU account created; demo accounts absent
- [ ] Backup taken and copied off the VPS; `APP_KEY` stored safely
