#!/usr/bin/env bash
#
# Roll back to a previous release. Run ON the VPS as the deploy user.
#
#   deploy/rollback.sh                    # previous release
#   deploy/rollback.sh 20261014-091500    # a specific release
#   deploy/rollback.sh --list             # show what is available
#
# Rollback is a symlink swap plus a PHP-FPM reload, so it takes about a second.
#
# THE ONE THING THIS DOES NOT DO IS ROLL BACK THE DATABASE SCHEMA. Migrations
# are forward-only in practice: a release that dropped or renamed a column
# cannot be undone by pointing `current` at older code, because the data is
# already gone. Before rolling back across a release that included a migration,
# read the migration and decide:
#
#   * additive only (new nullable column, new table)  -> safe to roll back
#   * anything destructive                            -> do not roll back;
#                                                        roll forward with a fix
#
# If the schema really must be reverted, run the specific migration down
# deliberately — never `migrate:rollback` with a large --step:
#
#   php artisan migrate:rollback --step=1 --force
#
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/dict-feedback}"
PHP_BIN="${PHP_BIN:-php}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.2-fpm}"

RELEASES="$APP_ROOT/releases"
CURRENT="$APP_ROOT/current"

log()  { printf '\n\033[1m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m  ! %s\033[0m\n' "$*" >&2; }
die()  { printf '\n\033[1;31mFATAL: %s\033[0m\n' "$*" >&2; exit 1; }

release_dirs() {
    ls -1dt "$RELEASES"/*/ 2>/dev/null || true
}

[[ -d "$RELEASES" ]] || die "Missing $RELEASES."

LIVE="$(readlink -f "$CURRENT" 2>/dev/null || true)"

if [[ "${1:-}" == "--list" || "${1:-}" == "-l" ]]; then
    log "Releases (newest first)"
    while IFS= read -r dir; do
        if [[ -z "$dir" ]]; then
            continue
        fi
        NAME="$(basename "$dir")"
        if [[ "$(readlink -f "$dir")" == "$LIVE" ]]; then
            printf '  %s  <- live\n' "$NAME"
        else
            printf '  %s\n' "$NAME"
        fi
    done < <(release_dirs)
    exit 0
fi

TARGET_ARG="${1:-}"

if [[ -n "$TARGET_ARG" ]]; then
    TARGET="$RELEASES/$TARGET_ARG"
    if [[ ! -d "$TARGET" ]]; then
        die "No such release: $TARGET_ARG (try --list)"
    fi
else
    # The newest release that is not the live one.
    TARGET=""
    while IFS= read -r dir; do
        if [[ -z "$dir" ]]; then
            continue
        fi
        if [[ "$(readlink -f "$dir")" != "$LIVE" ]]; then
            TARGET="$dir"
            break
        fi
    done < <(release_dirs)

    if [[ -z "$TARGET" ]]; then
        die "There is no earlier release to roll back to."
    fi
fi

if [[ "$(readlink -f "$TARGET")" == "$LIVE" ]]; then
    die "$(basename "$TARGET") is already live."
fi

# A release missing its shared links would come up broken.
[[ -e "$TARGET/.env" ]] || die "$(basename "$TARGET")/.env is missing or broken."
[[ -e "$TARGET/storage" ]] || die "$(basename "$TARGET")/storage is missing or broken."
[[ -f "$TARGET/public/build/manifest.json" ]] \
    || die "$(basename "$TARGET") has no Vite manifest; the survey pages would 500."

log "Rolling back"
echo "  from: $(basename "${LIVE:-none}")"
echo "  to:   $(basename "$TARGET")"

# Warn about migrations present in the live release but not in the target: those
# have already run forward and will not be undone by this swap.
if [[ -n "$LIVE" && -d "$LIVE/database/migrations" ]]; then
    NEWER="$(comm -23 \
        <(ls -1 "$LIVE/database/migrations" 2>/dev/null | sort) \
        <(ls -1 "$TARGET/database/migrations" 2>/dev/null | sort) || true)"

    if [[ -n "$NEWER" ]]; then
        warn "The live release has migrations this release does not:"
        while IFS= read -r m; do
            if [[ -n "$m" ]]; then
                printf '      %s\n' "$m" >&2
            fi
        done <<< "$NEWER"
        warn "Those changes stay in the database. Confirm they were additive first."
    fi
fi

ln -sfn "$TARGET" "$CURRENT"

log "Reloading $PHP_FPM_SERVICE"
if sudo systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null; then
    echo "  reloaded"
else
    warn "Could not reload $PHP_FPM_SERVICE. Run it manually:"
    warn "  sudo systemctl reload $PHP_FPM_SERVICE"
fi

log "Rolled back to $(basename "$TARGET")"
echo "  verify: $PHP_BIN $CURRENT/artisan about"
