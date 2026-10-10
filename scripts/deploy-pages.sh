#!/usr/bin/env bash
# Publishes a clean build of a git ref to the gh-pages branch, served by GitHub Pages at
# https://ahmedsalemawbal-cmd.github.io/aHMED/ (a temporary address until a real domain).
#
#   scripts/deploy-pages.sh [ref]      # default: HEAD
#
# The build comes from `git archive <ref>`, never from the working tree, so unfinished
# local files are never published. It reads VITE_SUPABASE_URL and VITE_SUPABASE_ANON_KEY
# from .env.local (not in git). The anon key is the publishable key: it ships inside the
# client bundle of any build, and RLS protects the data.
set -euo pipefail

ROOT=$(git rev-parse --show-toplevel)
REF=${1:-HEAD}
BASE=${PAGES_BASE:-/aHMED/}
BRANCH=${PAGES_BRANCH:-gh-pages}
SHA=$(git -C "$ROOT" rev-parse --short "$REF")

if [[ ! -f "$ROOT/.env.local" ]]; then
  echo "Missing .env.local with VITE_SUPABASE_URL and VITE_SUPABASE_ANON_KEY" >&2
  exit 1
fi

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$WORK/app"
git -C "$ROOT" archive "$REF" | tar -x -C "$WORK/app"
ln -s "$ROOT/node_modules" "$WORK/app/node_modules"
cp "$ROOT/.env.local" "$WORK/app/.env.local"

echo "Checking types of $SHA…"
(cd "$WORK/app" && npx tsc -p tsconfig.app.json --noEmit)

echo "Building $SHA for $BASE…"
(cd "$WORK/app" && npx vite build --base="$BASE" --outDir "$WORK/site" --emptyOutDir)

# Deep links (/aHMED/leads/…) reach GitHub Pages as unknown paths: serve the app for them too.
cp "$WORK/site/index.html" "$WORK/site/404.html"
# Publish files as they are (no Jekyll processing).
touch "$WORK/site/.nojekyll"

cd "$WORK/site"
git init -q -b "$BRANCH"
git add -A
git commit -q -m "Deploy $SHA to GitHub Pages"
git push -q --force "$(git -C "$ROOT" remote get-url origin)" "HEAD:refs/heads/$BRANCH"
echo "Published $SHA to $BRANCH."
