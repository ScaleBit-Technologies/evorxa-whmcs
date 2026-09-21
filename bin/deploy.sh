#!/usr/bin/env bash
# Copy the module into a WHMCS install.
#   bin/deploy.sh /path/to/whmcs [owner:group]
# --delete is scoped to the two module directories only; lang/overrides is never touched.
set -euo pipefail

TARGET="${1:?usage: bin/deploy.sh /path/to/whmcs [owner:group]}"
OWNER="${2:-}"
SRC="$(cd "$(dirname "$0")/.." && pwd)"

[ -f "$TARGET/init.php" ] || { echo "Not a WHMCS root: $TARGET" >&2; exit 1; }

for dir in modules/servers/evorxa modules/addons/evorxa_manager; do
  mkdir -p "$TARGET/$dir"
  rsync -a --delete --exclude 'lang/overrides/' "$SRC/$dir/" "$TARGET/$dir/"
  if [ -n "$OWNER" ]; then
    chown -R "$OWNER" "$TARGET/$dir"
  fi
  find "$TARGET/$dir" -type d -exec chmod 755 {} +
  find "$TARGET/$dir" -type f -exec chmod 644 {} +
  echo "deployed $dir"
done
