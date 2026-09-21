#!/usr/bin/env bash
# Build dist/evorxa-whmcs-<version>.zip (upload-ready: extract into the WHMCS root).
set -euo pipefail

SRC="$(cd "$(dirname "$0")/.." && pwd)"
VERSION=$(grep -oE "VERSION = '[0-9.]+'" "$SRC/modules/servers/evorxa/lib/Api/Client.php" | grep -oE "[0-9.]+")
OUT="$SRC/dist/evorxa-whmcs-$VERSION.zip"

mkdir -p "$SRC/dist"
rm -f "$OUT"
cd "$SRC"
for f in $(find modules -name '*.php'); do php -l "$f" >/dev/null; done
zip -qr "$OUT" modules README.md CHANGELOG.md -x 'modules/servers/evorxa/lang/overrides/*' -x '*.DS_Store'
echo "built $OUT ($(du -h "$OUT" | cut -f1))"
