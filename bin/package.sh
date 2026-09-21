#!/usr/bin/env bash
# Build dist/evorxa-whmcs-<version>.zip - the same package as Evorxa Manager > Install / Export:
# modules/servers/evorxa (incl. docs + screenshots), modules/addons/evorxa_manager and INSTALL.txt.
set -euo pipefail

SRC="$(cd "$(dirname "$0")/.." && pwd)"
VERSION=$(grep -oE "VERSION = '[0-9.]+'" "$SRC/modules/servers/evorxa/lib/Api/Client.php" | grep -oE "[0-9.]+")
OUT="$SRC/dist/evorxa-whmcs-$VERSION.zip"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$SRC/dist"
rm -f "$OUT"
cd "$SRC"
for f in $(find modules -name '*.php'); do php -l "$f" >/dev/null; done
sed "s/{version}/$VERSION/" modules/servers/evorxa/docs/INSTALL.txt > "$TMP/INSTALL.txt"
zip -qr "$OUT" modules -x 'modules/servers/evorxa/lang/overrides/*' -x '*.DS_Store'
( cd "$TMP" && zip -q "$OUT" INSTALL.txt )
echo "built $OUT ($(du -h "$OUT" | cut -f1), $(unzip -l "$OUT" | tail -1 | awk '{print $2}') files)"
