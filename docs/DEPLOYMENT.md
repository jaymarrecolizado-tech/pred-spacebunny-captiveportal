# Deployment — DICT Free Wi-Fi Customer Feedback Platform

**Status of this document.** The release and rollback scripts it describes are  
verified by a stubbed dry-run (`26/26` checks). The steps below that run *on the  
server* have not been executed yet: the external reconnaissance in §2 was done  
over the network, and the server-side facts marked **CONFIRM** still need to be  
read off the box.

---

## 1. Target

|           |                                                  |
| --------- | ------------------------------------------------ |
| Domain    | `fpiapulse.dictr2.cloud`                         |
| Address   | `187.77.150.203`                                 |
| Site user | `dictr2-fpiapulse`                               |
| Hosting   | Hostinger, nginx already serving this host       |
| Repo      | `platform/` in this project (git, branch `main`) |

## 2. What is already on the box

Checked over the network before touching anything:

| Finding                              | Evidence                                                                                    |
| ------------------------------------ | ------------------------------------------------------------------------------------------- |
| DNS resolves to the VPS              | `fpiapulse.dictr2.cloud` → `187.77.150.203`                                                 |
| SSH, HTTP and HTTPS are all open     | ports 22, 80, 443 reachable                                                                 |
| nginx is already serving this host   | `Server: nginx`                                                                             |
| Port 80 **forces HTTPS**             | `301 → https://fpiapulse.dictr2.cloud/`                                                     |
| TLS is a **self-signed** certificate | issuer `CN=fpiapulse.dictr2.cloud` equals the subject, single-cert chain, issued 2026-10-13 |
| Docroot is empty                     | serves the placeholder `Hello World :-)`                                                    |

### The one thing that will break the pilot

The certificate is self-signed, and port 80 redirects to it. A phone that joins  
the Free Wi-Fi and is handed the survey URL will get a **certificate warning**  
and never see the survey — the failure looks like "the portal does nothing",  
with no error on our side. This is the same silent failure mode the pilot gate  
in `docs/PORTAL_CONTRACT.md` exists to catch, and it has to be fixed before any  
device testing.

Two ways out, and the choice determines `PORTAL_PUBLIC_URL`:

- **Recommended — issue a trusted certificate.** hPanel → SSL → Let's Encrypt.  
  Keep the HTTP → HTTPS redirect, set `PORTAL_PUBLIC_URL=https://fpiapulse.dictr2.cloud`.
- **Or serve the portal over plain HTTP.** Remove the redirect (or exempt  
  `/portal/*`) and set `PORTAL_PUBLIC_URL=http://fpiapulse.dictr2.cloud`.  
  Acceptable for a closed pilot, not for production.

Do not test on a phone against the self-signed certificate; it will produce a  
false negative and you will chase the wrong bug.

## 3. Reconnaissance before any change (**CONFIRM**)

Plan.md Phase 2 §6.1 requires confirming the environment before touching it, and  
requires that an unrelated application on this VPS is not disturbed. Run these  
first and keep the output:

```bash
ssh dictr2-fpiapulse@187.77.150.203

# What is this machine, and is anything else running on it?
uname -a; cat /etc/os-release | head -3
free -h; df -h /; nproc

# What is already listening, and what web server is serving our host?
sudo ss -lntp | grep -E ':(80|443|3306)'
nginx -v 2>&1
ls /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ 2>/dev/null

# Which PHP, and which FPM service name? (php8.2-fpm is assumed everywhere below)
php -v | head -1
systemctl list-units --type=service | grep -i fpm

# Where does our host's docroot actually live, and who owns it?
grep -rn "fpiapulse.dictr2.cloud" /etc/nginx/ 2>/dev/null | head
id; echo "$HOME"; ls -la ~/domains/ 2>/dev/null || ls -la ~

# Is there a database server locally, and can this user create databases?
mysql --version 2>/dev/null || mariadb --version 2>/dev/null
```

Write the answers into this file. In particular, **do not** add a second nginx  
vhost for a hostname that already has one — edit the existing server block, or  
add a `server_name` to it. `deploy/nginx.conf.example` is a reference for the  
Laravel front-controller directives, not a file to drop into `sites-enabled`.

Then check the pieces the app needs:

```bash
php -m | grep -E '^(bcmath|ctype|curl|dom|fileinfo|mbstring|openssl|pdo_mysql|tokenizer|xml)$'
composer --version
node --version && npm --version      # only needed if you build assets on the server
```

## 4. Layout

Laravel is not served from a repository checkout. Each release is an immutable  
directory and `current` is a symlink, so a deploy is atomic and a rollback is a  
symlink swap.

```
/home/dictr2-fpiapulse/feedback/
├── releases/
│   ├── 20261014-090000/
│   └── 20261014-120000/
├── shared/
│   ├── .env          <- lives here, never inside a release
│   └── storage/
└── current -> releases/20261014-120000
```

**CONFIRM** how the docroot is wired before choosing between these:

- If hPanel lets you set the document root, point it at  
  `/home/dictr2-fpiapulse/feedback/current/public`.
- Otherwise make `public_html` a symlink to `current/public`:  
  `ln -sfn ~/feedback/current/public ~/domains/fpiapulse.dictr2.cloud/public_html`  
  and make sure the release script's `current` swap is reflected. A symlinked  
  docroot is followed by nginx only if `disable_symlinks` is off (it is by  
  default).

## 5. One-time server setup

```bash
export APP_ROOT=~/feedback
mkdir -p "$APP_ROOT/releases" "$APP_ROOT/shared/storage"

# Shared storage, writable by the web server user.
mkdir -p "$APP_ROOT/shared/storage/"{app/public,framework/{cache/data,sessions,views},logs}
chmod -R 775 "$APP_ROOT/shared/storage"

# The environment file lives outside every release.
cp deploy/env.production.example "$APP_ROOT/shared/.env"
chmod 640 "$APP_ROOT/shared/.env"
php artisan key:generate --show     # run locally; paste into APP_KEY
```

Fill in `APP_KEY`, `DB_*` and the two portal values. Then create the database  
and a least-privilege account — never use the MariaDB `root` user:

```sql
CREATE DATABASE dict_feedback CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dict_feedback'@'127.0.0.1' IDENTIFIED BY '<generated>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
  ON dict_feedback.* TO 'dict_feedback'@'127.0.0.1';
FLUSH PRIVILEGES;
```

`CREATE`/`ALTER`/`INDEX`/`DROP` are needed for migrations. If your policy  
forbids that, run migrations as a separate migration account and grant the  
application account only `SELECT, INSERT, UPDATE, DELETE`.

## 6. Getting the code onto the server

`release.sh` clones from a git remote. There is no remote configured yet, and a  
bare repository on the VPS keeps the code off any third-party service:

```bash
# on the VPS, once
mkdir -p ~/repos && git init --bare ~/repos/feedback.git
```

```bash
# from the workstation, once
git -C platform remote add vps dictr2-fpiapulse@187.77.150.203:repos/feedback.git
git -C platform push vps main
```

`release.sh` then uses `REPO_URL=~/repos/feedback.git` (an absolute path, not a  
URL). Push `main` before each release.

## 7. First release

```bash
cd ~/feedback
APP_ROOT=~/feedback \
REPO_URL=/home/dictr2-fpiapulse/repos/feedback.git \
BRANCH=main \
PHP_FPM_SERVICE=php8.2-fpm \
  ~/feedback/current/deploy/release.sh
```

On the very first run there is no `current`, so copy the script out of a  
checkout first:

```bash
git clone --depth 1 --branch main ~/repos/feedback.git ~/feedback-tools
APP_ROOT=~/feedback REPO_URL=~/repos/feedback.git ~/feedback-tools/deploy/release.sh
```

The script refuses to run unless the environment is safe. It will stop on  
`APP_ENV` not being `production`, `APP_DEBUG=true`, an empty `APP_KEY`, an empty  
or loopback `PORTAL_PUBLIC_URL`, or `PORTAL_SIMULATOR_ENABLED=true`. Those checks  
exist because each one is a way to publish a broken or exposed deployment.

## 8. Verify

The script runs these itself; run them by hand if anything looks wrong.

```bash
curl -sS https://fpiapulse.dictr2.cloud/api/v1/public/health
# {"status":"ok","checks":{"database":"ok"},...}

curl -sSI https://fpiapulse.dictr2.cloud/ | head -1          # 200
curl -sSI https://fpiapulse.dictr2.cloud/admin/login | head -1   # 200
curl -sSI https://fpiapulse.dictr2.cloud/portal/unavailable | head -1  # 200
curl -sSI https://fpiapulse.dictr2.cloud/simulator | head -1 # 302/403/404, never 200
```

Then confirm the handoff URL is on the public host, which is the check that  
catches a misconfigured `PORTAL_PUBLIC_URL`:

```bash
curl -sS -X POST https://fpiapulse.dictr2.cloud/api/v1/integrations/portal/init \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -H "X-Integration-Id: $ID" -H "X-Integration-Secret: $SECRET" \
  -d '{"site_code":"<a real site>"}' | grep -o '"survey_url":"[^"]*"'
```

## 9. Rollback

```bash
deploy/rollback.sh --list        # what is available
deploy/rollback.sh               # previous release
deploy/rollback.sh 20261014-090000
```

**Rollback does not roll back the database.** Migrations run forward; pointing  
`current` at older code leaves newer schema in place. The script lists any  
migrations the live release has that the target does not, so you can judge  
whether they were additive. A release that dropped a column cannot be rolled  
back — roll forward with a fix instead.

## 10. What must never run in production

| Never                           | Why                                                                                                                                                                                      |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `php artisan migrate:fresh`     | Destroys all data.                                                                                                                                                                       |
| `php artisan db:seed`           | `DatabaseSeeder` now skips demo data outside local/testing, and `DemoSeeder` throws, so this is guarded — but do not rely on the guard alone. Demo accounts use the password `password`. |
| `php artisan migrate --seed`    | Same seeding path. Use `migrate --force` alone.                                                                                                                                          |
| `PORTAL_SIMULATOR_ENABLED=true` | The simulator can mint survey sessions. The release script refuses to deploy with it on.                                                                                                 |
| `APP_DEBUG=true`                | Exposes stack traces, paths and environment values.                                                                                                                                      |

The first admin account is created through the panel, not a seeder.

## 11. Walled-garden requirements

These are gateway-side and network-side, and no amount of application code  
compensates for them:

1. **DNS**: captive clients must be able to resolve `fpiapulse.dictr2.cloud`  
   *before* they are authenticated. If the gateway's DNS is restricted, add the  
   record (or the IP) to the pre-authentication resolver.
2. **Allow-list**: the gateway must permit outbound access to  
   `187.77.150.203:443` (or `:80`) for unauthenticated clients.
3. **Certificate**: must be trusted by the client device — see §2.
4. **No port 80 → 443 redirect for `/portal/*`** if you chose the plain-HTTP  
   route.

## 12. Backups

```bash
mysqldump --single-transaction dict_feedback | gzip > dict_feedback-$(date +%F).sql.gz
tar czf storage-$(date +%F).tar.gz -C ~/feedback/shared storage
```


```

Run daily, keep 14 days, store off the VPS. Nothing else on the box is
irreplaceable: releases are rebuilt from git, and `shared/.env` should be in the
DICT secret store, not only on disk.

## 13. After the deploy

The SSH key used for this deployment is being rotated afterwards, which is the
right call. Two things to do at the same time:

- Confirm no key material was committed: `git log --all -p | grep -i "BEGIN OPENSSH"`.
- Re-run `deploy/release.sh` once after the rotation, using the new key, so the
  deploy path is known to work without the old one.
```
