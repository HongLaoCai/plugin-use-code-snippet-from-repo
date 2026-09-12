#!/usr/bin/env bash
# Build a WordPress-installable plugin ZIP.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NAME="repo-code-snippets"
OUT="$ROOT/dist/${NAME}.zip"
STAGE="$(mktemp -d)/${NAME}"

mkdir -p "$ROOT/dist" "$STAGE"
rsync -a \
  --exclude='.git' \
  --exclude='dist' \
  --exclude='snippets' \
  --exclude='.DS_Store' \
  --exclude='assets/image-*' \
  --exclude='*.png' \
  "$ROOT/" "$STAGE/"

rm -f "$OUT"
(cd "$(dirname "$STAGE")" && zip -r "$OUT" "$NAME")
rm -rf "$(dirname "$STAGE")"

echo "Created: $OUT"
unzip -l "$OUT" | head -20
