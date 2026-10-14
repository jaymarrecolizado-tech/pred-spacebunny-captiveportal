#!/usr/bin/env bash
#
# Atomic release for the DICT Free Wi-Fi Customer Feedback Platform.
#
# Run ON the VPS as the deploy user, not from a workstation:
#
#   REPO_URL=git@github.com:dict/feedback-platform.git deploy/release.sh
#
# Layout it expects (create it once, see docs/DEPLOYMENT.md):
#
#   /var/www/dict-feedback/
#   ├── releases/   one directory per release, immutable once deployed
#   ├── shared/     .env and storage/, shared across releases
#   └── current     symlink -> the release that is live
#
# The swap is a single `ln -sfn`, so the change is atomic and rollback.sh is
# instant. Nothing here touches the database schema destructively.
#
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/dict-feedback}"
REPO_URL="${REPO_URL:-}"
BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.2-fpm}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-1}"
SMOKE_TIMEOUT="${SMOKE_TIMEOUT:-15}"

SHARED="$APP_ROOT/shared"
RELEASES="$APP_ROOT/releases"
CURRENT="$APP_ROOT/current"
STAMP="$(date +%Y%m%d-%H%M%S)"
NEW="$RELEASES/$STAMP"
RELOAD_FAILED=0

log()  { printf '\n\033[1m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m  ! %s\033[0m\n' "$*" >&2; }
die()  { printf '\n\033[1;31mFATAL: %s\033[0m\n' "$*" >&2; exit 1; }

envval() {
    sed -n "s/^$1=//p" "$SHARED/.env" | tail -n 1 \
        | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

# ---------------------------------------------------------------------------
# Preconditions. These are the mistakes that turn a deploy into an incident, so
# they are checked before anything is written.
# ---------------------------------------------------------------------------
log "Checking preconditions"

[[ -n "$REPO_URL" ]] || die "Set REPO_URL to the git remote, e.g. REPO_URL=git@github.com:dict/feedback-platform.git"
[[ -f "$SHARED/.env" ]] || die "Missing $SHARED/.env. Create it from deploy/env.production.example first."
[[ -d "$RELEASES" ]] || die "Missing $RELEASES. Run the one-time server setup in docs/DEPLOYMENT.md."
command -v "$PHP_BIN" >/dev/null || die "$PHP_BIN is not on PATH."
command -v "$COMPOSER_BIN" >/dev/null || die "$COMPOSER_BIN is not on PATH."
command -v "$NPM_BIN" >/dev/null || die "$NPM_BIN is not on PATH."

APP_ENV_V="$(envval APP_ENV)"
[[ "$APP_ENV_V" == "production" ]] || die "APP_ENV in $SHARED/.env is '$APP_ENV_V'; expected 'production'."

[[ "$(envval APP_DEBUG)" == "false" ]] \
    || die "APP_DEBUG must be false in production; it exposes stack traces and environment values."

[[ -n "$(envval APP_KEY)" ]] \
    || die "APP_KEY is empty. Generate one with: $PHP_BIN artisan key:generate --show"

PUBLIC_URL="$(envval PORTAL_PUBLIC_URL)"
[[ -n "$PUBLIC_URL" ]] \
    || die "PORTAL_PUBLIC_URL is empty. Every captive portal handoff URL is built from it."
case "$PUBLIC_URL" in
    *localhost*|*127.0.0.1*)
        die "PORTAL_PUBLIC_URL ($PUBLIC_URL) is not reachable by a captive device." ;;
esac

if [[ "$(envval PORTAL_SIMULATOR_ENABLED)" == "true" ]]; then
    die "PORTAL_SIMULATOR_ENABLED must be false in production; the simulator can mint survey sessions."
fi

if [[ "$(envval DB_CONNECTION)" == "sqlite" ]]; then
    warn "DB_CONNECTION is sqlite. A single-file database is not appropriate for this deployment."
fi

echo "  env=$APP_ENV_V  public_url=$PUBLIC_URL  branch=$BRANCH"

# ---------------------------------------------------------------------------
# Build the new release
# ---------------------------------------------------------------------------
log "Fetching $BRANCH into releases/$STAMP"
git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$NEW"

log "Linking shared state"
ln -sfn "$SHARED/.env" "$NEW/.env"
rm -rf "$NEW/storage"
ln -sfn "$SHARED/storage" "$NEW/storage"

log "Installing PHP dependencies (no dev)"
"$COMPOSER_BIN" install --working-dir="$NEW" \
    --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

log "Building front-end assets"
# public/build is gitignored, so every release must build its own.
( cd "$NEW" && "$NPM_BIN" ci --no-audit --no-fund && "$NPM_BIN" run build && rm -rf node_modules )

if [[ "$RUN_MIGRATIONS" == "1" ]]; then
    log "Running migrations"
    ( cd "$NEW" && "$PHP_BIN" artisan migrate --force )
else
    warn "RUN_MIGRATIONS=0 — schema not migrated. Only correct if this release adds no migration."
fi

log "Caching config, routes and views"
# storage/ is shared between releases, so stale compiled views must be cleared
# before they are rebuilt, or the previous release's templates stay live.
( cd "$NEW" && "$PHP_BIN" artisan view:clear )
( cd "$NEW" && "$PHP_BIN" artisan config:cache )
( cd "$NEW" && "$PHP_BIN" artisan view:cache )
# route:cache fails if any package registers a closure route. Not fatal.
( cd "$NEW" && "$PHP_BIN" artisan route:cache ) || warn "route:cache skipped (closure routes present)."

log "Sanity-checking the release"
[[ -f "$NEW/public/build/manifest.json" ]] || die "Vite manifest missing: the survey pages would 500."
[[ -f "$NEW/bootstrap/cache/config.php" ]] || warn "config cache not written."
( cd "$NEW" && "$PHP_BIN" artisan --version >/dev/null ) || die "artisan does not run in the new release."

# ---------------------------------------------------------------------------
# Swap and reload
# ---------------------------------------------------------------------------
PREVIOUS="$(readlink -f "$CURRENT" 2>/dev/null || true)"

log "Switching current -> releases/$STAMP"
ln -sfn "$NEW" "$CURRENT"

log "Reloading $PHP_FPM_SERVICE"
# Required even for a symlink swap: with opcache.validate_timestamps=0 the old
# bytecode stays resident until FPM is reloaded.
if sudo systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null; then
    echo "  reloaded"
else
    RELOAD_FAILED=1
    warn "Could not reload $PHP_FPM_SERVICE. The new code will NOT be served until you run:"
    warn "  sudo systemctl reload $PHP_FPM_SERVICE"
fi

# ---------------------------------------------------------------------------
# Smoke checks, against the new release
# ---------------------------------------------------------------------------
log "Smoke checks against $PUBLIC_URL"

http_code() { curl -s -o /dev/null -w '%{http_code}' --max-time "$SMOKE_TIMEOUT" "$1" || echo "000"; }

HEALTH_CODE="$(http_code "$PUBLIC_URL/api/v1/public/health")"
if [[ "$HEALTH_CODE" == "200" ]]; then
    echo "  health           200"
else
    warn "health endpoint returned $HEALTH_CODE (expected 200) at $PUBLIC_URL/api/v1/public/health"
fi

for path in "/" "/admin/login" "/portal/unavailable"; do
    CODE="$(http_code "$PUBLIC_URL$path")"
    if [[ "$CODE" == "200" ]]; then
        printf '  %-17s 200\n' "$path"
    else
        warn "$path returned $CODE (expected 200)"
    fi
done

# The simulator must not be reachable. 302 is Filament's login redirect, which
# is the expected answer for an anonymous request; 404 means it is switched off.
SIM_CODE="$(http_code "$PUBLIC_URL/simulator")"
case "$SIM_CODE" in
    302|403|404) printf '  %-17s %s (not exposed)\n' "/simulator" "$SIM_CODE" ;;
    *)           warn "/simulator returned $SIM_CODE — check PORTAL_SIMULATOR_ENABLED." ;;
esac

# ---------------------------------------------------------------------------
# Prune
# ---------------------------------------------------------------------------
log "Pruning old releases (keeping $KEEP_RELEASES)"
CURRENT_TARGET="$(readlink -f "$CURRENT")"
PRUNED=0
while IFS= read -r old; do
    if [[ -z "$old" ]]; then
        continue
    fi
    if [[ "$(readlink -f "$old")" == "$CURRENT_TARGET" ]]; then
        continue
    fi
    rm -rf "$old"
    PRUNED=$((PRUNED + 1))
done < <(ls -1dt "$RELEASES"/*/ 2>/dev/null | tail -n +$((KEEP_RELEASES + 1)) || true)
echo "  removed $PRUNED release(s)"

# ---------------------------------------------------------------------------
# Report
# ---------------------------------------------------------------------------
log "Released $STAMP"
if [[ -n "$PREVIOUS" ]]; then
    echo "  previous: $(basename "$PREVIOUS")"
    echo "  rollback: deploy/rollback.sh $(basename "$PREVIOUS")"
fi

if [[ "$RELOAD_FAILED" == "1" ]]; then
    printf '\n\033[1;31mRelease is live on disk but PHP-FPM was not reloaded. Run the reload before testing.\033[0m\n' >&2
    exit 1
fi
