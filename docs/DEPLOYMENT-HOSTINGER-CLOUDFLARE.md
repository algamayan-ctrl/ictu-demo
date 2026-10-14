# SPMU-ACPMP Deployment Guide: Hostinger VPS + Cloudflare

A complete, step-by-step guide that takes SPMU-ACPMP from this GitHub repository to **https://spmu-acpmp.tech**, where Google sign-in, Google registration, OTP email verification and password reset work on **any device**.

Follow the parts **in order**. Every step says **where** to do it, **what** to click or type, and **what you should see**. Tick each checkbox as you go.

> This guide covers the technical setup only. It does not replace any approval CSPC or ICTU requires before real borrowers use the system.

---

## Contents

- [0. Read this first](#0-read-this-first)
- [Part A. Put the deployment files on `main` (GitHub)](#part-a-put-the-deployment-files-on-main-github)
- [Part B. Buy and set up the Hostinger VPS](#part-b-buy-and-set-up-the-hostinger-vps)
- [Part C. Hostinger firewall](#part-c-hostinger-firewall)
- [Part D. Connect the domain to Cloudflare](#part-d-connect-the-domain-to-cloudflare)
- [Part E. First login and server preparation](#part-e-first-login-and-server-preparation)
- [Part F. Download the code onto the VPS](#part-f-download-the-code-onto-the-vps)
- [Part G. Gmail account for OTP and reset emails](#part-g-gmail-account-for-otp-and-reset-emails)
- [Part H. Google sign-in (Google Cloud Console)](#part-h-google-sign-in-google-cloud-console)
- [Part I. Cloudflare Tunnel](#part-i-cloudflare-tunnel)
- [Part J. Create the configuration file (`.env.docker`)](#part-j-create-the-configuration-file-envdocker)
- [Part K. Start the system](#part-k-start-the-system)
- [Part L. Create the first ICTU account](#part-l-create-the-first-ictu-account)
- [Part M. Verify everything (including two devices)](#part-m-verify-everything-including-two-devices)
- [Part N. Everyday operations](#part-n-everyday-operations)
- [Part O. Backups](#part-o-backups)
- [Part P. Troubleshooting](#part-p-troubleshooting)
- [Part Q. Security and go-live checklist](#part-q-security-and-go-live-checklist)
- [Appendix 1. What each file does](#appendix-1-what-each-file-does)
- [Appendix 2. Command cheat sheet](#appendix-2-command-cheat-sheet)

---

## 0. Read this first

### 0.1 Why deployment fixes Google sign-in, OTP and reset on other devices

On a laptop, `APP_URL` is `http://127.0.0.1:...`, `http://localhost:...` or `http://192.168.x.x:...`.

- `127.0.0.1` and `localhost` always mean **"this same device"**. On a phone, a reset link or Google callback that points there opens the **phone itself**, so it fails.
- Google **refuses** LAN addresses (`192.168.x.x`) as sign-in callbacks. It accepts only `localhost` or a **public HTTPS** address.

After this guide, every link uses `https://spmu-acpmp.tech`, which every device on the internet can reach. OTP codes and reset tokens are stored in the **database**, so you can start on a laptop and finish on a phone.

### 0.2 How the finished system is connected

```
 Phone / Laptop (any network)
          │  https://spmu-acpmp.tech
          ▼
 Cloudflare  (DNS, HTTPS certificate, Tunnel)
          │  encrypted tunnel, started FROM the VPS (no open web ports)
          ▼
 Hostinger VPS: Ubuntu 24.04 + Docker, folder /opt/spmu
 ┌──────────────────────────────────────────────────────────┐
 │ cloudflared ──► app (website, Apache + PHP 8.4, port 80) │
 │                 queue      (background jobs)             │
 │                 scheduler  (deadline processing)         │
 │                 database   (MariaDB 11.4, never public)  │
 └──────────────────────────────────────────────────────────┘
   internal Docker network 172.30.0.0/24
```

### 0.3 Where each command is typed

Every command block in this guide is marked with **where** to run it. Running a command in the wrong place is the most common mistake.

| Mark | Where | How you know you're there |
|---|---|---|
| 🖥 **Laptop** | PowerShell or the VS Code terminal on your Windows laptop | Prompt looks like `PS C:\Users\...>` |
| 🐧 **VPS** | The Linux server, after `ssh deploy@YOUR_VPS_IP` | Prompt looks like `deploy@srv123456:~$` or `deploy@srv123456:/opt/spmu$` |
| 🌐 **Browser** | A web page (Hostinger hPanel, Cloudflare, Google, GitHub) | n/a |

> If you see `printf is not recognized`, `New-Item ... already exists` or `PS C:\` errors, you typed a 🐧 VPS command into the 🖥 Laptop. Connect to the VPS first (Part E).

### 0.4 Values used in this guide

| Placeholder | Meaning | Example / where to get it |
|---|---|---|
| `spmu-acpmp.tech` | Your public domain | Already in Cloudflare |
| `YOUR_VPS_IP` | The VPS public IPv4 address | hPanel → VPS → **Overview** (Part B) |
| `deploy` | Linux user that runs the system | Created in Part E |
| `/opt/spmu` | Folder on the VPS that holds the code | Created in Part F |
| `SYSTEM_GMAIL` | Gmail that sends OTP and reset emails | Chosen in Part G |

Whenever a command shows `YOUR_VPS_IP`, replace it with the real IP, for example `ssh deploy@203.0.113.25`.

### 0.5 What you need before starting

- [ ] Admin access to the GitHub repository `arbasagre-ctrl/capstone-spmu-acpmp` (or the owner available to help)
- [ ] A Hostinger account and a payment method
- [ ] Access to Cloudflare where `spmu-acpmp.tech` is added
- [ ] A Gmail account your **team** controls (Part G)
- [ ] Access to the Google Cloud project that owns the sign-in client, or the ability to create a new one (Part H)
- [ ] About 2–3 hours for the first deployment

### 0.6 Golden rules

1. **Never paste passwords, App Passwords, client secrets, tokens or `.env` files into chats, screenshots, GitHub or documents.** If you did, create new ones.
2. **Never run `docker compose down -v`.** The `-v` deletes the database and all uploaded files.
3. **Never change `APP_KEY` or the database passwords** after the system has real data.
4. **Take a backup before every update** (Part O).

---

## Part A. Put the deployment files on `main` (GitHub)

The VPS downloads the code from the `main` branch. The deployment files below must be on `main` before Part F:

- `docs/DEPLOYMENT-HOSTINGER-CLOUDFLARE.md` (this guide)
- `docker-compose.hostinger.yml`
- `tools/make-hostinger-env.sh`

**A1. Check whether `main` already has them.** 🌐 Browser:

1. Open <https://github.com/arbasagre-ctrl/capstone-spmu-acpmp>.
2. Make sure the branch selector (top left) shows **main**.
3. Look for `docker-compose.hostinger.yml` in the file list and `make-hostinger-env.sh` inside the `tools` folder.

If both exist, skip to Part B. If not, continue.

**A2. Push your branch.** 🖥 Laptop, in the project folder:

```bash
git status
```

It must say *nothing to commit, working tree clean*. If it doesn't, commit first (`git add -A`, then `git commit -m "..."`). Then push:

```bash
git push
```

**A3. Open and merge a pull request.** 🌐 Browser:

1. On the repository page, click the yellow **Compare & pull request** banner. If there's no banner, go to **Pull requests → New pull request**.
2. Set **base:** `main` and **compare:** your branch (for example `final-spmu-acpmp`).
3. Confirm it says **✓ Able to merge**.
4. Title: `Add Hostinger + Cloudflare deployment files`. Click **Create pull request**.
5. Click **Merge pull request → Confirm merge**.
6. Repeat A1 to confirm the files are now on `main`.

- [ ] Deployment files are on `main`

---

## Part B. Buy and set up the Hostinger VPS

### B1. Choose the plan

**Choose a KVM VPS. Do not buy shared hosting.** Premium, Business and Cloud hosting cannot run Docker, the background queue and scheduler, LibreOffice document conversion, or MariaDB 11.4.

| Plan | Specs | Use it? |
|---|---|---|
| KVM 1 | 1 vCPU, 4 GB RAM, 50 GB | No. Too slow to build the system; may run out of memory. |
| **KVM 2** | **2 vCPU, 8 GB RAM, 100 GB NVMe** | **Yes, recommended** |
| KVM 4 | 4 vCPU, 16 GB RAM, 200 GB | Works, but more than a capstone needs |

The promo price requires a long prepaid term, and renewal costs more. Check the total at checkout.

### B2. Buy and configure

🌐 Browser, <https://www.hostinger.com/ph/vps-hosting>:

1. Click **Choose plan** under **KVM 2**, pick the billing period, and pay.
2. In the setup wizard:
   - **Location:** the closest to the Philippines that is offered (Malaysia, Singapore or India).
   - **Operating system:** tab **OS with Application** (or *Applications*) → **Ubuntu 24.04 with Docker**. If it's missing, choose plain **Ubuntu 24.04 LTS**; Docker is installed in Part E.
   - **Root password:** a strong, unique password. Save it in a password manager.
   - **SSH key:** optional; you can skip it.
   - Skip any free malware scanner or panel offers.
3. Wait until the VPS status shows **Running** (a few minutes).

If the VPS was already created with a different OS: hPanel → **VPS → OS & Panel → Operating System** → choose **Ubuntu 24.04 with Docker** → **Change OS**. This **erases** the VPS, so only do it before deploying.

### B3. Write down the VPS IP

🌐 hPanel → **VPS** → your server → **Overview**. Copy the **IPv4 address**. This is `YOUR_VPS_IP`.

The same page has the **Browser terminal** button. It's an emergency way into the VPS that works even when SSH fails.

- [ ] KVM 2 VPS running Ubuntu 24.04 (with Docker), IP written down

---

## Part C. Hostinger firewall

The Cloudflare Tunnel connects **out** from the VPS, so the only port that needs to be open is **22 (SSH)**.

🌐 hPanel → **VPS** → your server → **Security → Firewall**:

1. Click **Add firewall** (or **Create firewall configuration**) and name it `allow only port 22 inbound`.
2. Open it. Under **Add firewall rule**, fill in:

   | Field | Value |
   |---|---|
   | Action | **Accept** |
   | Protocol | **TCP** |
   | Port (or range) | **22** |
   | Source | **Any** (not "Custom") |
   | Source details | leave empty |

3. Click **Add rule**.
4. In the rules table, **Accept · TCP · 22 · Any** must appear **above** **Drop · Any · Any**.
5. Click **Synchronize** (yellow button). Rules do nothing until you synchronize.
6. If the firewall has an on/off switch, make sure it's **active** for this VPS.

**Do not add ports 80, 443, 8080 or 3306.** The website goes through the tunnel, and the database must never be public.

Why "Any" and not your IP: home and school IP addresses change, and a "Custom" rule would lock you out. SSH still requires the password or key.

- [ ] Firewall: only TCP 22 accepted, synchronized

---

## Part D. Connect the domain to Cloudflare

### D1. Make the domain Active

🌐 Cloudflare dashboard → select **spmu-acpmp.tech** → **Overview**.

- If it says **Active**, go to D2.
- If it says **Pending** / *nameserver update*, Cloudflare shows two nameservers (like `xxx.ns.cloudflare.com` and `yyy.ns.cloudflare.com`). Set them where the domain was **bought**:
  - **Bought at Hostinger:** hPanel → **Domains** → `spmu-acpmp.tech` → **DNS / Nameservers** → **Change nameservers** → *Use custom nameservers* → enter the two Cloudflare nameservers → **Save**.
  - **Bought at get.tech or another registrar:** log in there → domain → **Nameservers** → custom → enter the two → save.
  - **Bought at Cloudflare Registrar:** already correct; it becomes Active by itself.
- Wait until Overview shows **Active** (minutes, up to 24 hours). Cloudflare also emails you.

### D2. Clean the DNS records

🌐 Cloudflare → **spmu-acpmp.tech** → **DNS → Records**.

- **Do not add an A record for the VPS IP.** The tunnel creates the main record in Part I.
- If there is an **A**, **AAAA** or **CNAME** record named `spmu-acpmp.tech` (or `@`) or `www`, **delete it** (click the row → **Delete**).
- Banner *"add a mail exchange (MX) record"*: ignore it and close it. MX is only for *receiving* email at `@spmu-acpmp.tech`. The system *sends* through Gmail.
- Banners *"A, AAAA or CNAME record ... required"*: ignore them for now. Part I fixes them.

### D3. Security and speed settings

The fastest way to find each setting is the **Quick search** box at the top left of the Cloudflare dashboard (**Ctrl+K**). Type the setting's name.

| Setting (type in Quick search) | Set to | Usual location |
|---|---|---|
| **SSL/TLS encryption** | **Full (strict)** | SSL/TLS → Overview → *Configure* |
| **Always Use HTTPS** | **On** | SSL/TLS → Edge Certificates |
| **Minimum TLS Version** | **TLS 1.2** | SSL/TLS → Edge Certificates |
| **Rocket Loader** | **Off** (it rewrites JavaScript and can break forms) | Speed → Optimization → Content Optimization |
| **Email Address Obfuscation** | **Off** (it changes pages that show email addresses) | Security → Settings, or Scrape Shield |

- [ ] Domain **Active**, no stray A/CNAME records, SSL/TLS settings done

---

## Part E. First login and server preparation

### E1. Connect to the VPS as root

🖥 Laptop (PowerShell or the VS Code terminal). Windows 11 already includes `ssh`:

```bash
ssh root@YOUR_VPS_IP
```

- First time: *"Are you sure you want to continue connecting (yes/no)?"* → type `yes` and press Enter.
- Enter the **root password** from B2. Nothing shows while you type; that's normal.
- Success: the prompt becomes `root@srv123456:~#`.

If SSH doesn't work, use hPanel → VPS → **Overview → Browser terminal**.

### E2. Update Ubuntu

🐧 VPS (as root):

```bash
apt update && apt -y upgrade
```

If a purple screen asks about restarting services or keeping a config file, press **Enter** to accept the default.

### E3. Install Docker (only if the OS was plain Ubuntu)

🐧 VPS. Check first:

```bash
docker --version && docker compose version
```

- If both print versions, go to E4. **Any** Docker Compose version **v2.24.4 or newer** works; **v3, v4 and v5 (for example `v5.6.0`) are all newer.**
- If you get *command not found*, install Docker:

```bash
curl -fsSL https://get.docker.com | sh
```

Run the version check again.

### E4. Create the `deploy` user

Working as root all the time is risky, so the system runs under a normal user called `deploy`.

🐧 VPS (as root):

```bash
adduser deploy
```

- **New password:** choose a strong password and save it. You'll use it to log in and for `sudo`.
- *Full Name, Room Number, ...*: press **Enter** for each, then `Y`.

Give it admin (`sudo`) and Docker rights:

```bash
usermod -aG sudo,docker deploy
```

### E5. Turn on Ubuntu's own firewall

🐧 VPS (as root):

```bash
ufw allow OpenSSH && ufw --force enable
```

```bash
ufw status
```

Expected: `Status: active`, with `OpenSSH` (and `OpenSSH (v6)`) **ALLOW**. Nothing else.

> Docker-published ports bypass UFW. That's why `docker-compose.hostinger.yml` publishes the website only on `127.0.0.1` (the VPS itself).

### E6. Log in again as `deploy`

🐧 VPS:

```bash
exit
```

🖥 Laptop:

```bash
ssh deploy@YOUR_VPS_IP
```

Enter the `deploy` password. The prompt becomes `deploy@srv123456:~$`. **From now on, always log in as `deploy`**, not root.

Check that Docker works without `sudo`:

```bash
docker ps
```

An empty table (headers only) is correct. *Permission denied* means the group change hasn't applied yet: type `exit`, log in again, and retry.

- [ ] Logged in as `deploy`; `docker ps` works; UFW active with only OpenSSH

---

## Part F. Download the code onto the VPS

The repository is private, so the VPS needs its own **read-only deploy key** to download it.

### F1. Create the key

🐧 VPS (as `deploy`). Run these one at a time:

```bash
mkdir -p ~/.ssh && chmod 700 ~/.ssh
```

```bash
ssh-keygen -t ed25519 -C "spmu-vps" -f ~/.ssh/spmu_deploy -N ""
```

Nothing in these commands needs to be changed. `-C "spmu-vps"` is just a label. `-N ""` means no passphrase, so the server can download updates without asking.

### F2. Copy the PUBLIC key

🐧 VPS:

```bash
cat ~/.ssh/spmu_deploy.pub
```

Select and copy the **whole single line**: it starts with `ssh-ed25519` and ends with `spmu-vps`.

This is the **public** key (`.pub`), which is safe to paste into GitHub. **Never** show or copy `~/.ssh/spmu_deploy` (without `.pub`).

### F3. Add it to GitHub

🌐 <https://github.com/arbasagre-ctrl/capstone-spmu-acpmp> → **Settings** → left menu **Deploy keys** → **Add deploy key**:

| Field | Value |
|---|---|
| Title | `Hostinger VPS` |
| Key | paste the line from F2 |
| Allow write access | **leave unchecked** |

Click **Add key**. If you don't see **Settings**, you're not an admin of the repository: send the public key line to the owner and ask them to add it.

### F4. Tell the VPS to use the key, then test it

🐧 VPS:

```bash
printf 'Host github.com\n  IdentityFile ~/.ssh/spmu_deploy\n' >> ~/.ssh/config && chmod 600 ~/.ssh/config
```

```bash
ssh -T git@github.com
```

Type `yes` to the first-time question. **Success message:** *"Hi arbasagre-ctrl/capstone-spmu-acpmp! You've successfully authenticated, but GitHub does not provide shell access."* (it says "exit 1"; that's normal). *Permission denied (publickey)* means F3 wasn't saved or the wrong line was pasted.

### F5. Clone the code into `/opt/spmu`

🐧 VPS:

```bash
sudo mkdir -p /opt/spmu && sudo chown deploy:deploy /opt/spmu
```

```bash
git clone git@github.com:arbasagre-ctrl/capstone-spmu-acpmp.git /opt/spmu
```

```bash
cd /opt/spmu && git switch main && git log --oneline -1
```

### F6. Confirm the deployment files are there

🐧 VPS:

```bash
ls docker-compose.hostinger.yml tools/make-hostinger-env.sh docs/DEPLOYMENT-HOSTINGER-CLOUDFLARE.md
```

All three names must print with no *No such file* error. If any is missing, Part A wasn't completed. Finish it, then run `git pull`.

- [ ] Code is in `/opt/spmu` on `main`, deployment files present

---

## Part G. Gmail account for OTP and reset emails

The system sends OTP codes, password-reset links and notifications from one Gmail account through Gmail SMTP. It can be **any Gmail your team controls**. Nothing in the program code is tied to a specific address.

### G1. Choose the account

- **Best:** a new, dedicated Gmail such as `spmu.acpmp.system@gmail.com`. It isn't tied to one member, so it keeps working after anyone graduates or leaves.
- **Don't** use a school `@my.cspc.edu.ph` account. Its admin often disables App Passwords.
- Share the login with the team in person or through a password manager, not in chat.

### G2. Turn on 2-Step Verification

🌐 Sign in to the chosen Gmail → <https://myaccount.google.com> → **Security** → **2-Step Verification** → **Turn on** (a phone number is enough). App Passwords don't exist without it.

### G3. Create the App Password

🌐 <https://myaccount.google.com/apppasswords> (or search **"App passwords"** in the Google Account search bar):

1. App name: `SPMU VPS` → **Create**.
2. Google shows **16 letters** (in 4 groups). Copy them now; Google shows them **only once**.
3. Keep them for Part J. Don't paste them into any chat.

### G4. Retire old App Passwords

If an older App Password for this system was ever shared (in chat, a screenshot or a file), the owner of that Gmail should delete it on the same App passwords page.

- [ ] Team-controlled Gmail with 2-Step Verification
- [ ] New 16-letter App Password copied (kept private)

---

## Part H. Google sign-in (Google Cloud Console)

"Continue with Google" needs an **OAuth client** (Client ID + Client Secret) in Google Cloud Console.

- The client can be owned by **any** Google account. It doesn't have to be the Gmail from Part G.
- Users sign in with **their own** Google accounts. They see your app name and support email, never the owner's account.

### H1. Choose: existing client or new client

- **If someone on the team can open** the existing client (`378600902181-...`): use it (H2). Create a **new secret** if the old one was ever shared.
- **If nobody on the team can open it:** create a new one (H3).

### H2. Using the existing client

🌐 <https://console.cloud.google.com> → select the project (top bar) → **Google Auth Platform** (or search "Google Auth Platform") → **Clients** → click the Web client:

1. **Authorized JavaScript origins → Add URI:** `https://spmu-acpmp.tech`
2. **Authorized redirect URIs → Add URI:** `https://spmu-acpmp.tech/auth/google/callback`
3. **Client secrets → Add secret.** Copy the new secret now; Google shows it only once.
4. Click **Save**.
5. **Later**, only after Google sign-in works on the live site (Part M), come back and **disable/delete the old secret**.

Then continue at H4.

### H3. Creating a new client

🌐 <https://console.cloud.google.com>, signed in with the team Gmail (recommended):

1. Top bar project picker → **New project** → name `SPMU-ACPMP` → **Create** → select it.
2. Search **Google Auth Platform** → **Get started**:
   - App name: `SPMU-ACPMP`
   - User support email: the team Gmail
   - Audience: **External**
   - Contact information: the team Gmail
   - Agree → **Create**
3. **Clients → Create client:**
   - Application type: **Web application**
   - Name: `SPMU-ACPMP Production`
   - Authorized JavaScript origins: `https://spmu-acpmp.tech`
   - Authorized redirect URIs: `https://spmu-acpmp.tech/auth/google/callback`
   - **Create**
4. Copy the **Client ID** (ends with `.apps.googleusercontent.com`) and the **Client secret**. Keep them for Part J.

Existing user accounts aren't affected by a new client: the system links Google sign-ins by email address.

### H4. Branding, audience and access (both cases)

🌐 Google Auth Platform:

1. **Branding:**
   - App name `SPMU-ACPMP`; support email = team Gmail.
   - **Application home page:** `https://spmu-acpmp.tech`
   - **Authorized domains → Add domain:** `spmu-acpmp.tech`
   - **Developer contact:** team Gmail → **Save**.
2. **Audience:**
   - *Testing* status: only accounts listed under **Test users** can sign in (up to 100). Click **Add users** and enter every panelist's and tester's Gmail.
   - Or click **Publish app** → *In production*: any Google account can sign in. The system only requests `openid`, `email` and `profile`, so no lengthy Google review is needed.
3. **Data access:** only `openid`, `.../auth/userinfo.email` and `.../auth/userinfo.profile` should be listed. Remove anything else.
4. **Share access with the team:** left menu **IAM & Admin → IAM → Grant access** → add teammates' Gmail addresses with role **Owner**, so the client is never locked to one person again.

Exact-match rules: `https`, **no** `www`, **no** slash at the end. Google can take a few minutes to apply changes.

- [ ] Client ID + (new) Client Secret ready
- [ ] Origin and redirect URI saved exactly as above
- [ ] Testers added, or app published; teammates added as Owners

---

## Part I. Cloudflare Tunnel

### I1. Create the tunnel

🌐 Cloudflare dashboard. The menu name has changed over time; any of these paths works:

- **Networking → Tunnels**, or
- **Zero Trust → Networks → Tunnels** (sometimes labelled **Connectors**)

First time in Zero Trust: choose a team name and the **Free** plan (a card may be requested for verification; Free is not charged).

1. Click **Create a tunnel** → type **Cloudflared** → **Next**.
2. Name: `spmu-acpmp` → **Save tunnel**.

### I2. Copy only the token

On the **Install and run a connector** page, choose **Docker**. Cloudflare shows a command like:

```
docker run cloudflare/cloudflared:latest tunnel --no-autoupdate run --token eyJhIjoi...very-long...
```

- **Copy only the token**: the long text after `--token `, starting with `eyJ`.
- **Do not run Cloudflare's command.** Our `docker-compose.hostinger.yml` runs the tunnel inside the same Docker network as the app. Cloudflare's own command would start a separate tunnel that can't find the app.

### I3. Save the token on the VPS

🐧 VPS:

```bash
cd /opt/spmu && nano tunnel.env
```

Type this line, pasting your token after the `=` (no spaces, no quotes):

```dotenv
TUNNEL_TOKEN=eyJhIjoi...your-whole-token...
```

Save in nano: **Ctrl+O**, **Enter**, then **Ctrl+X**. Then protect the file:

```bash
chmod 600 tunnel.env && cut -c1-20 tunnel.env
```

It should print `TUNNEL_TOKEN=eyJ...` (only the start). `tunnel.env` is excluded from Git and from the app image, so it is never uploaded.

### I4. Route the domain to the app ("Published application")

🌐 Cloudflare, continue the wizard (**Next**), or open the tunnel → **Routes** → **Add route** → **Published application**. Older screens call this *Public Hostname*.

| Field | Value |
|---|---|
| Subdomain | *(leave empty)* |
| Domain | **spmu-acpmp.tech** |
| Path | *(leave empty)* |
| Service type | **HTTP** |
| Service URL | **`app:80`** |

If the form has only one box (no Type dropdown), enter `http://app:80`.

Why `app:80`: `app` is the website's service name in `docker-compose.yml`, and Apache listens on port **80** inside that container. The tunnel container joins the same Docker network, so it finds `app` by name.

**Never** use `localhost`, `127.0.0.1`, `8080`, `https://...` or the VPS IP here.

Click **Save** / **Add route**. Then check 🌐 **DNS → Records**: a **CNAME** `spmu-acpmp.tech → <id>.cfargotunnel.com` with an **orange cloud** (Proxied) appears automatically.

- Domain missing from the dropdown: the domain isn't **Active** yet (Part D1).
- *"A record already exists"*: delete the old A/CNAME for `spmu-acpmp.tech` (Part D2) and add the route again.
- The website shows **Error 1033 or 502** until Part K. That's expected: the tunnel has nothing to connect to yet.

### I5. Send `www` to the main address

Sign-in only works on the **exact** `APP_URL` address, so everyone must end up on `https://spmu-acpmp.tech`.

1. 🌐 **DNS → Records → Add record:**

   | Field | Value |
   |---|---|
   | Type | **CNAME** |
   | Name | `www` |
   | Target | `spmu-acpmp.tech` |
   | Proxy status | **Proxied** (orange cloud) |
   | TTL | Auto |

   **Save**.
2. 🌐 **Rules → Overview** (or **Rules → Redirect Rules**) → **Templates** → **Redirect from WWW to root** → keep the defaults → **Deploy**.

- [ ] Tunnel created; token in `/opt/spmu/tunnel.env`
- [ ] Published application `spmu-acpmp.tech → app:80`; CNAME appeared
- [ ] `www` CNAME + WWW → root redirect deployed

---

## Part J. Create the configuration file (`.env.docker`)

`.env.docker` holds all settings and secrets for the running system. It lives **only on the VPS** and is never uploaded to GitHub.

### J1. Recommended: the helper script

🐧 VPS:

```bash
cd /opt/spmu && git pull && bash tools/make-hostinger-env.sh
```

It asks five questions:

| Question | What to type |
|---|---|
| Public domain without https:// [spmu-acpmp.tech] | Press **Enter** |
| Google OAuth Client ID | The Client ID from Part H (ends with `.apps.googleusercontent.com`) |
| NEW Google OAuth Client Secret (hidden) | The new secret from Part H. **Nothing appears while pasting; that's normal.** Press Enter. |
| Gmail sender address | The Gmail from Part G, for example `spmu.acpmp.system@gmail.com` |
| NEW Gmail App Password, 16 letters (hidden) | The 16 letters from Part G (spaces are removed automatically). Press Enter. |

The script:

- **generates** a new `APP_KEY` and strong database passwords on the server, so they never pass through chat
- writes every production value (table in J3)
- makes the file readable only by `deploy`
- backs up an existing `.env.docker` first, and warns you

It ends by printing the exact Google origin and redirect URI. Compare them with Part H.

### J2. Save the APP_KEY

🐧 VPS:

```bash
grep '^APP_KEY=' /opt/spmu/.env.docker
```

Copy the value into the team password manager. If the VPS is ever rebuilt, the same `APP_KEY` is needed to read existing sessions and encrypted data. **Never** change it on a running system.

### J3. What the file contains (and what changed from local)

You don't need to edit anything after the script. This table explains each value:

| Setting | Production value | Local/old value | Why |
|---|---|---|---|
| `APP_ENV` | `production` | `local` | Production behaviour and safety checks |
| `APP_DEBUG` | `false` | `true` | Never show error details to users |
| `APP_URL` | `https://spmu-acpmp.tech` | `http://127.0.0.1:...` / `192.168.x.x` | **The fix for other devices.** Every link and callback is built from this. |
| `APP_KEY` | newly generated | old key | The old one appeared in chats and screenshots |
| `SEED_DEMO_USERS` | `false` | `true` | Production refuses demo accounts anyway |
| `RUN_MIGRATIONS` | `true` | `false` | Builds the database on first start; applies new migrations on updates |
| `LOG_LEVEL` | `warning` | `debug` | Debug logs can record personal data |
| `DB_PASSWORD`, `MARIADB_PASSWORD`, `MARIADB_ROOT_PASSWORD` | newly generated | placeholders | Strong random passwords |
| `SESSION_SECURE_COOKIE` | `true` | `false` | Cookies only over HTTPS |
| `SESSION_IDLE_TIMEOUT` | `15` | `1` | `1` logged users out after one idle minute |
| `TRUSTED_PROXIES` | `172.30.0.0/24` | *(missing)* | **Required.** Without it, Google sign-in redirects to itself in a loop. |
| `MAIL_SCHEME` | `smtp` (+ `MAIL_TIMEOUT=15`) | `null` | Correct secure connection for Gmail port 587 |
| `MAIL_USERNAME`, `MAIL_FROM_ADDRESS` | the Gmail from Part G | old Gmail | The sender of OTP and reset emails |
| `MAIL_PASSWORD` | the new App Password | old App Password | The old one was shared |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | from Part H | old secret | The old secret was shared |
| `GOOGLE_REDIRECT_URI` | `https://spmu-acpmp.tech/auth/google/callback` | `http://localhost:8080/...` | Must equal `APP_URL` + `/auth/google/callback` and match Google exactly |
| `GOOGLE_ALLOWED_DOMAINS` | *(empty)* | *(empty)* | Empty allows employee and external Google registration (still SAO-reviewed) |
| `INSTITUTIONAL_EMAIL_DOMAINS` | `cspc.edu.ph` | `cspc.edu.ph,my.cspc.edu.ph` | A student `@my.cspc.edu.ph` address doesn't prove employee status (see [AUTH-PUBLIC-DEPLOYMENT.md](AUTH-PUBLIC-DEPLOYMENT.md)) |
| `EMAIL_VERIFICATION_BASE_URL` | *(not used)* | in older docs | Obsolete; links now come from `APP_URL` |

### J4. How to change a value later

🐧 VPS:

```bash
cd /opt/spmu && nano .env.docker
```

1. Use the arrow keys to move to the line and edit the text after `=`.
2. Save: **Ctrl+O**, **Enter**, **Ctrl+X**.
3. Apply the change. The settings are loaded only when a container starts:

```bash
docker compose up -d --force-recreate app queue scheduler
```

Rules for values: no spaces around `=`, and don't use the `$` character inside values (Docker treats `$` specially). Put quotes around values that contain spaces, like `MAIL_FROM_NAME="SPMU-ACPMP"`.

Example: if your team decides students may register as employees, change the line to `INSTITUTIONAL_EMAIL_DOMAINS=cspc.edu.ph,my.cspc.edu.ph`, save, and run the recreate command.

### J5. Manual alternative (only if you can't run the script)

🐧 VPS:

```bash
cd /opt/spmu && cp .env.docker.example .env.docker && chmod 600 .env.docker
```

Generate the secrets on the VPS (each command prints one value to copy into the file):

```bash
echo "base64:$(openssl rand -base64 32)"
```

```bash
openssl rand -hex 24
```

Run the second command twice: once for `DB_PASSWORD`/`MARIADB_PASSWORD` (same value in both) and once for `MARIADB_ROOT_PASSWORD`.

Then `nano .env.docker` and set every value exactly as in the J3 table. Also add these lines if they're missing:

```dotenv
SESSION_IDLE_TIMEOUT=15
SESSION_DOMAIN=null
SESSION_PATH=/
TRUSTED_PROXIES=172.30.0.0/24
```

Write `GOOGLE_REDIRECT_URI` in full (`https://spmu-acpmp.tech/auth/google/callback`), not as `${APP_URL}/...`.

- [ ] `.env.docker` created; `APP_KEY` saved in the password manager

---

## Part K. Start the system

### K1. Tell Docker to always use both compose files

🐧 VPS (one time; it's remembered for every future login):

```bash
echo 'export COMPOSE_FILE=docker-compose.yml:docker-compose.hostinger.yml' >> ~/.bashrc && source ~/.bashrc
```

```bash
echo $COMPOSE_FILE
```

It must print `docker-compose.yml:docker-compose.hostinger.yml`. From now on, plain `docker compose ...` commands in `/opt/spmu` include the tunnel and the safe port binding automatically.

### K2. Final pre-flight check

🐧 VPS:

```bash
cd /opt/spmu && ls -l .env.docker tunnel.env && docker compose config --quiet && echo CONFIG-OK
```

You must see both files (owner `deploy`, permissions `-rw-------`) and `CONFIG-OK`. Any error here means a typo in `.env.docker` or `tunnel.env`.

### K3. Build and start

🐧 VPS:

```bash
docker compose up -d --build
```

- The **first** build takes **5–15 minutes** (it installs PHP, LibreOffice, OCR and fonts). Wait until the prompt returns.
- On first start the app automatically **creates all database tables** and loads reference data (`spmu:bootstrap`).

### K4. Watch it come up

🐧 VPS:

```bash
docker compose ps
```

Wait 1–2 minutes and run it again until all five services are up:

| Service | Expected STATUS |
|---|---|
| `database` | `Up ... (healthy)` |
| `app` | `Up ... (healthy)` |
| `queue` | `Up` |
| `scheduler` | `Up` |
| `cloudflared` | `Up` |

- `queue` or `scheduler` may restart once or twice during the very first start while the database tables are being created. That's normal.
- `cloudflared` starts only after `app` is healthy.

To watch the app's startup messages (press **Ctrl+C** to stop watching; the system keeps running):

```bash
docker compose logs -f app
```

### K5. Check each link in the chain

🐧 VPS:

1. App answers on the VPS itself:

   ```bash
   curl -fsS http://127.0.0.1:8080/up >/dev/null && echo APP-OK
   ```

2. Tunnel is connected to Cloudflare:

   ```bash
   docker compose logs cloudflared | grep -i "registered tunnel connection"
   ```

   One or more lines must appear.

3. The tunnel's network can reach `app:80`. The tunnel image itself has no shell, so this starts a tiny temporary container on the same network:

   ```bash
   docker run --rm --network spmu_default busybox wget -qO- http://app:80/up >/dev/null && echo NETWORK-OK
   ```

   If it says the network doesn't exist, run `docker network ls` and use the name that ends in `_default`.

4. 🌐 Open **https://spmu-acpmp.tech** on your laptop. The SPMU-ACPMP login page must load with a padlock.

- [ ] All 5 services up; `APP-OK`, tunnel registered, `NETWORK-OK`
- [ ] https://spmu-acpmp.tech loads

---

## Part L. Create the first ICTU account

There are no demo accounts in production. Create the first real ICTU account from the command line.

🐧 VPS. Replace the email, name and employee number with real ones:

```bash
docker compose exec app php artisan spmu:user ictu.admin@cspc.edu.ph --name="ICTU Administrator" --employee-no="REAL-EMPLOYEE-NO" --unit=ICTU --role=ICTU
```

- It asks for a password: **12+ characters** with uppercase, lowercase, a number and a symbol. Nothing appears while typing.
- Use an email the person can actually receive mail at. The account may need OTP verification on first sign-in.

Then 🌐 sign in at https://spmu-acpmp.tech with that account and create the other staff accounts (SPMU Admin/Head, Action Officers) in **User Accounts**. Borrowers register themselves at `/register` or with Google, and are approved by the SAO in **Borrower Registrations**. Details: [DEPLOYMENT-ICTU.md](DEPLOYMENT-ICTU.md), step 8.

Other `spmu:user` options: `--role=BORROWER|SPMU|ICTU`, `--classification=BORROWER_ONLY|SPMU_HEAD|SPMU_OFFICER|ICTU_MAINTAINER`, `--employment-type=EMPLOYEE|FACULTY|STAFF`.

- [ ] First ICTU account created and able to sign in

---

## Part M. Verify everything (including two devices)

### M1. Authentication readiness

🐧 VPS:

```bash
docker compose exec app php artisan spmu:auth-readiness --json --check-smtp
```

Required result: `"gate": "PASS"` and every check `true`, including `"SMTP connection/authentication": true`. This logs in to Gmail but **sends no email**.

Send one real test email to yourself:

```bash
docker compose exec app php artisan spmu:auth-readiness --send-test-to=your.own.address@gmail.com
```

Check that inbox, **including Spam**. If it's in Spam, mark it **Not spam**.

### M2. General deployment readiness

🐧 VPS:

```bash
docker compose exec app php artisan spmu:deployment-readiness
```

Every row should say **PASS** except **"Private persistent evidence disk"**. That check expects S3 cloud storage, but on this VPS uploaded files are stored on the VPS disk in a persistent Docker volume, protected by backups (Part O). **NOT READY on that single row is expected.**

### M3. Real test on two devices

Use **Device A** (laptop on Wi-Fi) and **Device B** (phone on **mobile data**, not the same Wi-Fi; this proves the address is really public). Record pass/fail and the date.

| # | Test | Expected result |
|---|---|---|
| 1 | Open `https://spmu-acpmp.tech` on A and B | Login page with a padlock |
| 2 | Open `https://www.spmu-acpmp.tech` | Redirects to `https://spmu-acpmp.tech` |
| 3 | B: **Continue with Google** using a tester Gmail | Google consent screen, then back to the system (registration form or dashboard) |
| 4 | A: sign up manually at `/register` | OTP email arrives; typing the code on **B** is accepted |
| 5 | Wrong OTP 5 times, or an OTP older than 10 minutes | Rejected with a clear message; a new OTP can be requested |
| 6 | A: **Forgot password** | Reset email arrives; open the link on **B**, set a new password, then sign in on **A** with it |
| 7 | Use the same reset link again, or one older than 60 minutes | Rejected |
| 8 | SAO approves the pending registration | The user can sign in on both devices |
| 9 | Leave a signed-in page idle for over 15 minutes | Signed out; signing in again works |

One normal limit: a single Google sign-in must start and finish in the **same browser**. Signing in with Google works on every device.

- [ ] M1 PASS, test email received
- [ ] M2 PASS except the expected S3 row
- [ ] All 9 two-device tests pass
- [ ] Old Google client secret disabled (Part H2 step 5); old App Passwords deleted (Part G4)

---

## Part N. Everyday operations

Always run these 🐧 on the VPS, as `deploy`, inside `/opt/spmu` (`cd /opt/spmu`).

### N1. Update to the newest code from `main`

1. Take a backup (Part O).
2. Download, rebuild and restart:

   ```bash
   cd /opt/spmu && git pull && docker compose up -d --build
   ```

3. Check:

   ```bash
   docker compose ps
   ```

   ```bash
   docker compose logs --tail=50 app
   ```

With `RUN_MIGRATIONS=true`, new database changes are applied automatically when `app` restarts.

### N2. Useful commands

| Task | Command |
|---|---|
| Status of all services | `docker compose ps` |
| Live logs of the app (Ctrl+C to stop) | `docker compose logs -f app` |
| Last 100 lines of a service | `docker compose logs --tail=100 queue` |
| Restart one service | `docker compose restart app` |
| Apply `.env.docker` changes | `docker compose up -d --force-recreate app queue scheduler` |
| Stop everything (data kept) | `docker compose stop` |
| Start everything again | `docker compose up -d` |
| Run an artisan command | `docker compose exec app php artisan <command>` |
| Disk usage | `df -h` and `docker system df` |
| Remove old unused images (frees disk) | `docker image prune -f` |

**Never** run `docker compose down -v`, `docker volume rm`, or `docker system prune --volumes`. They delete the database and uploaded files.

### N3. After a VPS reboot

All services have `restart: unless-stopped`, so they come back by themselves. Check with `docker compose ps`.

### N4. Updating Ubuntu (monthly)

```bash
sudo apt update && sudo apt -y upgrade
```

If it says a reboot is required, run `sudo reboot`, wait 2 minutes, log in again and run `docker compose ps`.

---

## Part O. Backups

### O1. Hostinger automatic backups and snapshots

🌐 hPanel → VPS → **Backups & Monitoring**:

- **Weekly backups** are included automatically.
- **Snapshots:** click **Create snapshot** before every update or risky change. This saves the whole VPS so you can roll back.

### O2. Application backup (database + uploaded files)

The following runs on the VPS and makes one dated backup set. More detail: [BACKUP-RESTORE-RUNBOOK.md](BACKUP-RESTORE-RUNBOOK.md).

🐧 VPS. Run these one at a time:

```bash
cd /opt/spmu && STAMP=$(date +%Y%m%d-%H%M%S) && B=~/backups/spmu-$STAMP && mkdir -p "$B" && echo "$B"
```

```bash
docker compose stop scheduler
```

```bash
docker compose exec -T database sh -c 'MYSQL_PWD="$MARIADB_PASSWORD" mariadb-dump --single-transaction --routines --events --hex-blob -u "$MARIADB_USER" "$MARIADB_DATABASE"' > "$B/database.sql"
```

```bash
docker compose cp app:/var/www/html/storage/app/private "$B/protected-storage"
```

```bash
docker compose start scheduler
```

```bash
(cd "$B" && find . -type f ! -name SHA256SUMS.txt -exec sha256sum {} + > SHA256SUMS.txt) && tar czf "$B.tar.gz" -C ~/backups "spmu-$STAMP" && ls -lh "$B.tar.gz"
```

Check that `database.sql` isn't tiny: `ls -lh "$B/database.sql"` should show at least several hundred KB.

### O3. Copy the backup off the VPS

A backup that lives only on the VPS is lost if the VPS is lost. 🖥 Laptop (PowerShell), in a folder where you keep backups. Replace the file name with the one printed above:

```bash
scp deploy@YOUR_VPS_IP:~/backups/spmu-20261015-090000.tar.gz .
```

Store it in a private, access-restricted location (for example the team's private Google Drive folder). It contains personal data.

### O4. Clean up old backups on the VPS

🐧 VPS. This keeps the newest 7 sets and deletes older ones:

```bash
ls -1dt ~/backups/spmu-*.tar.gz | tail -n +8 | xargs -r rm -f; ls -1dt ~/backups/spmu-*/ | tail -n +8 | xargs -r rm -rf; ls ~/backups
```

### O5. Secrets backup

Keep these in the team password manager, **never** in Git or chat: `APP_KEY`, the whole `.env.docker`, `tunnel.env`, the VPS root and `deploy` passwords, and the Gmail/Google login details.

---

## Part P. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `printf is not recognized`, `New-Item ... already exists`, prompt shows `PS C:\` | VPS command typed on the Windows laptop | Run `ssh deploy@YOUR_VPS_IP` first (Part E6) |
| `ssh: connect to host ... timed out` | Firewall blocks port 22, or wrong IP | Check Part C (rule + **Synchronize**); use hPanel **Browser terminal** |
| `Permission denied (publickey)` when cloning | Deploy key missing or wrong | Redo F2–F4; the key line must start with `ssh-ed25519` |
| `permission denied ... docker.sock` | `deploy` was added to the docker group but hasn't logged in again | `exit`, then `ssh deploy@YOUR_VPS_IP` again |
| `docker compose config` error about `!override` | Docker Compose older than v2.24.4 | Update Docker (Part E3) |
| `env file ... tunnel.env not found` | Token file missing | Part I3 |
| Cloudflare **Error 1033** | Tunnel container not running or wrong token | `docker compose ps`; `docker compose logs cloudflared`; recreate the token in Cloudflare and update `tunnel.env`, then `docker compose up -d cloudflared` |
| Cloudflare **502 / Bad gateway** | Tunnel works but can't reach the app | Service URL must be `app:80` (Part I4); `app` must be healthy (K4); test K5 step 3 |
| `app` stays `unhealthy` / restarting | Wrong DB password, or an error at startup | `docker compose logs --tail=100 app database`; `DB_PASSWORD` must equal `MARIADB_PASSWORD` |
| Changed DB passwords after the first start and now the DB login fails | MariaDB keeps the password from its first start | Put the original passwords back (from the `.env.docker.before-*` backup), then recreate |
| Google: **Error 400: redirect_uri_mismatch** | Google Console URI ≠ `GOOGLE_REDIRECT_URI` | Both must be exactly `https://spmu-acpmp.tech/auth/google/callback`; wait a few minutes after saving |
| `/auth/google` reloads endlessly / *too many redirects* | `TRUSTED_PROXIES` missing or wrong, so the app sees `http` | `TRUSTED_PROXIES=172.30.0.0/24`, recreate (J4); `docker network inspect spmu_default` must show subnet `172.30.0.0/24` |
| *"Google sign-in needs server configuration"* | `GOOGLE_REDIRECT_URI` ≠ `APP_URL` + `/auth/google/callback` | Fix `.env.docker`, recreate (J4) |
| Google: **Access blocked** / *app has not completed verification* / *not a test user* | Consent screen is in Testing | Add the account under **Audience → Test users**, or **Publish app** (H4) |
| **419 Page Expired** after a form or Google | Cookie rejected: wrong proxy setting, or a host other than `APP_URL` | Fix `TRUSTED_PROXIES`; set up the `www` redirect (I5); use only `https://spmu-acpmp.tech` |
| OTP or reset email never arrives | Gmail login failed, or the mail is in Spam | Run M1; check Spam; create a new App Password (Part G) and update `MAIL_PASSWORD` |
| `SMTP connection/authentication: false` | Wrong Gmail or App Password, 2-Step Verification off, or port 587 blocked | Recheck Part G; if everything is right, ask Hostinger support to allow outbound port 587 |
| Reset link points to `127.0.0.1` or `localhost` | Old `APP_URL` | Set `APP_URL=https://spmu-acpmp.tech`, recreate (J4) |
| Changed the Docker network but nothing changed | The old network still exists | `docker compose down` (**without** `-v`), then `docker compose up -d` |
| Build stops with *killed* / out of memory | VPS too small | Use KVM 2 or larger |
| Disk full | Old images and backups | `docker image prune -f`; Part O4 |

When asking for help, share the **output of commands** (for example `docker compose ps`, `docker compose logs --tail=50 app`), **never** `.env.docker` or `tunnel.env`.

---

## Part Q. Security and go-live checklist

**Server**

- [ ] KVM 2 VPS, Ubuntu 24.04, Docker Compose ≥ v2.24.4
- [ ] Hostinger firewall: only TCP 22 accepted, synchronized
- [ ] UFW active with only OpenSSH
- [ ] Daily work done as `deploy`, not root

**Cloudflare**

- [ ] Domain Active; SSL/TLS **Full (strict)**; Always Use HTTPS; TLS 1.2 minimum
- [ ] Rocket Loader off; Email Address Obfuscation off
- [ ] Tunnel healthy; Published application `spmu-acpmp.tech → app:80`
- [ ] `www` CNAME + redirect to root

**Configuration**

- [ ] `.env.docker`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://spmu-acpmp.tech`
- [ ] `SEED_DEMO_USERS=false`, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES=172.30.0.0/24`
- [ ] New `APP_KEY` and DB passwords, stored in the password manager

**Google and email**

- [ ] New Google client secret in use; old secret disabled
- [ ] New Gmail App Password in use; old App Passwords deleted
- [ ] Origin and redirect URI exact; testers added or app published; teammates are Owners

**Verification**

- [ ] `spmu:auth-readiness --check-smtp` → PASS; test email received
- [ ] All Part M3 two-device tests pass (phone on mobile data)
- [ ] First ICTU account works; no demo accounts exist

**Recovery**

- [ ] First backup taken (Part O2) and copied off the VPS (O3)
- [ ] Hostinger snapshot taken after go-live

---

## Appendix 1. What each file does

| File | Where | Purpose |
|---|---|---|
| `docker-compose.yml` | Git | Defines `database`, `app`, `queue`, `scheduler` |
| `docker-compose.hostinger.yml` | Git | Adds `cloudflared`; binds the app to `127.0.0.1:8080` only; fixes the network to `172.30.0.0/24` |
| `Dockerfile` | Git | Builds the app image (PHP 8.4, Apache, LibreOffice, OCR) |
| `docker/entrypoint.sh` | Git | On start: prepares storage, caches config, runs migrations + `spmu:bootstrap` when `RUN_MIGRATIONS=true` |
| `tools/make-hostinger-env.sh` | Git | Writes the production `.env.docker` (Part J) |
| `.env.docker.example` | Git | Template with placeholder values |
| `.env.docker` | **VPS only** | Real settings and secrets. Never in Git. |
| `tunnel.env` | **VPS only** | Cloudflare Tunnel token. Never in Git. |
| `.env.docker.before-*` | **VPS only** | Automatic backups made by the helper script |

## Appendix 2. Command cheat sheet

🐧 VPS, in `/opt/spmu`:

```bash
ssh deploy@YOUR_VPS_IP                      # (run on the laptop) log in
cd /opt/spmu                                # go to the project
docker compose ps                           # status
docker compose logs -f app                  # live app logs (Ctrl+C to stop)
git pull && docker compose up -d --build    # update to the newest main
docker compose up -d --force-recreate app queue scheduler   # apply .env.docker changes
docker compose exec app php artisan spmu:auth-readiness --json --check-smtp   # auth check
nano .env.docker                            # edit settings (Ctrl+O, Enter, Ctrl+X)
```

Related documents: [AUTH-PUBLIC-DEPLOYMENT.md](AUTH-PUBLIC-DEPLOYMENT.md), [GOOGLE-EMAIL-SETUP.md](GOOGLE-EMAIL-SETUP.md), [DEPLOYMENT-ICTU.md](DEPLOYMENT-ICTU.md), [BACKUP-RESTORE-RUNBOOK.md](BACKUP-RESTORE-RUNBOOK.md).
