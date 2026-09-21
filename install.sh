#!/usr/bin/env bash
# Evorxa Cloud for WHMCS - install or update from the latest GitHub release.
#
#   curl -fsSL https://raw.githubusercontent.com/ScaleBit-Technologies/evorxa-whmcs/master/install.sh | bash -s -- /path/to/whmcs
#
# Optional second argument: a version to install instead of the latest, e.g. 1.0.0.
# Only modules/servers/evorxa and modules/addons/evorxa_manager are written. An existing
# install is backed up first (outside the web root) and your lang/overrides are kept.
set -euo pipefail

REPO="ScaleBit-Technologies/evorxa-whmcs"
WHMCS="${1:-}"
VERSION="${2:-latest}"

die() { echo "Error: $*" >&2; exit 1; }
say() { echo "==> $*"; }

[ -n "$WHMCS" ] || die "tell me where WHMCS is installed, e.g.: bash -s -- /var/www/whmcs"
WHMCS="$(cd "$WHMCS" 2>/dev/null && pwd)" || die "folder not found: $1"
[ -f "$WHMCS/init.php" ] && [ -d "$WHMCS/modules/servers" ] && [ -d "$WHMCS/modules/addons" ] \
    || die "$WHMCS is not a WHMCS root folder (init.php, modules/servers and modules/addons are expected there)"
for tool in curl unzip tar; do
    command -v "$tool" >/dev/null 2>&1 || die "'$tool' is required - install it and run this again"
done

if [ "$VERSION" = "latest" ]; then
    URL="https://github.com/$REPO/releases/latest/download/evorxa-whmcs.zip"
else
    URL="https://github.com/$REPO/releases/download/v${VERSION#v}/evorxa-whmcs-${VERSION#v}.zip"
fi
URL="${EVX_ZIP_URL:-$URL}"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

say "Downloading $URL"
curl -fsSL --retry 3 -o "$TMP/evorxa.zip" "$URL" || die "download failed"
unzip -tq "$TMP/evorxa.zip" >/dev/null 2>&1 || die "the download is not a valid zip file"
unzip -q "$TMP/evorxa.zip" -d "$TMP/pkg"
[ -f "$TMP/pkg/modules/servers/evorxa/evorxa.php" ] && [ -f "$TMP/pkg/modules/addons/evorxa_manager/evorxa_manager.php" ] \
    || die "unexpected package layout"
NEW_VERSION="$(grep -oE "VERSION = '[0-9.]+'" "$TMP/pkg/modules/servers/evorxa/lib/Api/Client.php" | grep -oE '[0-9.]+' || true)"

if [ -d "$WHMCS/modules/servers/evorxa" ] || [ -d "$WHMCS/modules/addons/evorxa_manager" ]; then
    BACKUP_DIR="${EVX_BACKUP_DIR:-$HOME}"
    BACKUP="$BACKUP_DIR/evorxa-whmcs-backup-$(date +%Y%m%d-%H%M%S).tar.gz"
    say "Backing up the current install to $BACKUP"
    ( cd "$WHMCS" && tar -czf "$BACKUP" $(ls -d modules/servers/evorxa modules/addons/evorxa_manager 2>/dev/null) )
    ACTION="Updated"
else
    ACTION="Installed"
fi

say "Copying the module into $WHMCS"
cp -R "$TMP/pkg/modules/." "$WHMCS/modules/"

# Give the files to the same owner as the rest of WHMCS (only possible when running as root).
if [ "$(id -u)" = "0" ]; then
    OWNER="$(stat -c '%U:%G' "$WHMCS/init.php" 2>/dev/null || stat -f '%Su:%Sg' "$WHMCS/init.php")"
    chown -R "$OWNER" "$WHMCS/modules/servers/evorxa" "$WHMCS/modules/addons/evorxa_manager"
fi

echo
say "$ACTION Evorxa Cloud for WHMCS ${NEW_VERSION:-}"
if [ "$ACTION" = "Installed" ]; then
    cat <<'EOF'
Next steps (full guide: modules/servers/evorxa/docs/INSTALL.md):
  1. WHMCS admin > System Settings > Addon Modules: activate "Evorxa Manager".
  2. Evorxa console > API tokens: create a token with
     instances:read instances:write analytics:read shield:read shield:write wallet:read
  3. System Settings > Servers > Add New Server: module "Evorxa Cloud",
     hostname api.evorxa.com, paste the token into Password, Test Connection.
  4. Addons > Evorxa Manager > Settings, then Plans & Import.
  5. Run the WHMCS cron every 5 minutes.
EOF
else
    echo "Settings, servers, logs and lang/overrides were kept. Open Addons > Evorxa Manager once to apply any database update."
fi
