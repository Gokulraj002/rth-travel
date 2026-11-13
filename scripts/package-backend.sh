#!/bin/sh
# ─────────────────────────────────────────────────────────────────────────────
# Reyaan Trip Holiday: package the PHP admin + API for Hostinger.
#
#   npm run backend:zip          (or:  sh scripts/package-backend.sh)
#
# Produces dist/backend.zip laid out EXACTLY like a Hostinger site folder
# (/home/uXXXX/domains/admin.yourdomain.com/), so it is extracted in place — no SSH, no symlinks:
#   app/ bin/ config/ database/ storage/ .env.example .htaccess   ← private (outside the web root)
#   public_html/                                                   ← = backend/public (the web root)
#   - everything in backend/ (app, bin, config, database/*.sql, public → public_html, storage)
#   - WITHOUT the local .env (secrets), log files, cache files or uploaded images
#   - KEEPS the .gitkeep / .htaccess placeholders so the empty folders and the
#     security rules arrive on the server
# Upload and extract it as described in docs/DEPLOY-HOSTINGER.md (Part B).
# POSIX sh; works with the stock macOS / Linux `zip`.
# ─────────────────────────────────────────────────────────────────────────────
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SRC="$ROOT/backend"
OUT_DIR="$ROOT/dist"
OUT="$OUT_DIR/backend.zip"

if [ ! -d "$SRC" ]; then
  echo "error: $SRC not found" >&2
  exit 1
fi
if ! command -v zip >/dev/null 2>&1; then
  echo "error: the 'zip' command is not installed" >&2
  exit 1
fi

for f in database/schema.sql database/seed.sql .env.example public/.htaccess public/uploads/.htaccess; do
  if [ ! -f "$SRC/$f" ]; then
    echo "warning: backend/$f is missing$( [ "$f" = database/seed.sql ] && printf ' (run: npm run backend:seed)')" >&2
  fi
done

STAGE=$(mktemp -d "${TMPDIR:-/tmp}/rth-backend.XXXXXX")
trap 'rm -rf "$STAGE"' EXIT HUP INT TERM

# 1. Copy the backend into a staging folder (cp -R keeps dotfiles such as .htaccess).
cp -R "$SRC" "$STAGE/backend"
B="$STAGE/backend"

# 2. Secrets: drop every .env* file except the template.
find "$B" -type f -name '.env*' ! -name '.env.example' -exec rm -f {} +

# 3. Runtime folders: empty them but keep the placeholders (.gitkeep / .htaccess).
for d in storage/logs storage/cache public/uploads; do
  mkdir -p "$B/$d"
  find "$B/$d" -mindepth 1 -maxdepth 1 ! -name '.gitkeep' ! -name '.htaccess' -exec rm -rf {} +
  [ -f "$B/$d/.gitkeep" ] || : > "$B/$d/.gitkeep"
done

# 4. Junk files from macOS / editors, and any stray logs or dumps elsewhere.
find "$B" \( -name '.DS_Store' -o -name '._*' -o -name '*.log' -o -name '*.swp' -o -name '*~' \) -type f -exec rm -f {} +
find "$B" -type d \( -name '.git' -o -name 'node_modules' -o -name '.idea' -o -name '.vscode' \) -prune -exec rm -rf {} +

# 5. Sensible permissions for shared hosting: folders 755, files 644.
find "$B" -type d -exec chmod 755 {} +
find "$B" -type f -exec chmod 644 {} +

# 6. Hostinger's web root is always called public_html → rename public/ (the app detects either name).
mv "$B/public" "$B/public_html"

# 7. Zip the CONTENTS (no wrapper folder) so it extracts straight into the site folder (-X: no macOS attrs).
mkdir -p "$OUT_DIR"
rm -f "$OUT"
(cd "$B" && zip -rqX "$OUT" .)

COUNT=$(cd "$B" && find . -type f | wc -l | tr -d ' ')
SIZE=$(du -h "$OUT" | cut -f1 | tr -d ' ')
echo "Created dist/backend.zip ($COUNT files, $SIZE)."
echo "Next: upload it with Hostinger File Manager. See docs/DEPLOY-HOSTINGER.md, Part B."
