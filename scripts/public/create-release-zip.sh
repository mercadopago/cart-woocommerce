#!/usr/bin/env bash
# Builds an installable plugin ZIP from a source checkout (with or without .git), with the same
# content rules as the official WordPress.org package: the `package` profile of
# release-artifacts.json, production Composer dependencies, minified assets only, no Composer
# manifests, no agent metadata and no credentials. Run `npm run build` first.
set -euo pipefail
umask 077

script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
repo_root=$(cd -- "$script_dir/../.." && pwd -P)
output=''
while (($#)); do
  [[ $# -ge 2 && -n "$2" ]] || { echo 'Usage: create-release-zip.sh --output <absolute-path-to-new.zip>' >&2; exit 2; }
  case "$1" in
    --output) output=$2 ;;
    *) echo 'Invalid option.' >&2; exit 2 ;;
  esac
  shift 2
done
[[ -n "$output" ]] || { echo 'Usage: create-release-zip.sh --output <absolute-path-to-new.zip>' >&2; exit 2; }
[[ "$output" == /* ]] || { echo 'Use an absolute path for the output ZIP.' >&2; exit 2; }
[[ "$output" != *"/../"* && "$output" != *"/.." ]] || { echo 'The output path must not contain "..".' >&2; exit 2; }
[[ "$output" == *.zip ]] || { echo 'The output must be a .zip file.' >&2; exit 2; }
[[ ! -e "$output" && ! -L "$output" ]] || { echo 'The output ZIP already exists; choose a new path.' >&2; exit 1; }
[[ -d "$(dirname -- "$output")" ]] || { echo 'The output directory does not exist.' >&2; exit 1; }

for variant in super-token super-token-v2 super-token-v2.1; do
  for suffix in js asset.php; do
    file="build/$variant/bootstrap.ts.$suffix"
    [[ -s "$repo_root/$file" ]] || { echo "Missing build output: $file (run \`npm run build\` first)." >&2; exit 1; }
  done
done

staging=$(mktemp -d "${TMPDIR:-/tmp}/woocommerce-mercadopago-package.XXXXXX")
trap 'rm -rf -- "$staging"' EXIT
payload="$staging/woocommerce-mercadopago"
mkdir "$payload"
package_items=(assets build i18n src templates index.php readme.txt woocommerce-mercadopago.php composer.json composer.lock)
for item in "${package_items[@]}"; do
  [[ -e "$repo_root/$item" ]] || { echo "Missing package item: $item" >&2; exit 1; }
done

copy_file() {
  local file=$1 source_file="$repo_root/$1"
  # Finder metadata is never package content (and the ZIP step would drop it, breaking the inventory check).
  [[ "${file##*/}" != .DS_Store ]] || return 0
  [[ "$file" != /* && "$file" != *"../"* ]] || { echo "Unsafe path in the package: $file" >&2; exit 1; }
  if [[ -L "$source_file" ]]; then echo "Symbolic link in the package: $file" >&2; exit 1; fi
  [[ -f "$source_file" ]] || { echo "Missing package file: $file" >&2; exit 1; }
  mkdir -p -- "$payload/$(dirname -- "$file")"
  cp -- "$source_file" "$payload/$file"
}

# With .git, only versioned files of the package items are copied (local leftovers never ship).
# Without .git (for example, GitHub's "Download ZIP"), every file under the package items is used.
# The mode follows the checkout itself, never a git failure: if .git exists but git cannot read it
# (for example "dubious ownership"), the build stops instead of silently switching to the files on disk.
files="$staging/files"
if [[ -e "$repo_root/.git" ]]; then
  git_top=$(git -C "$repo_root" rev-parse --show-toplevel) || {
    echo 'Git could not read this checkout; fix the repository access (for example safe.directory) or remove .git to build from the files on disk.' >&2
    exit 1
  }
  # The tracked-files path needs a repository rooted at the source tree, not an ancestor's index.
  [[ "$(cd -- "$git_top" && pwd -P)" == "$repo_root" ]] || { echo 'The .git of this checkout does not belong to the source tree.' >&2; exit 1; }
  echo 'Package source: tracked files (git)'
  git -C "$repo_root" ls-files -z -- "${package_items[@]}" > "$files"
else
  echo 'Package source: files on disk (no .git)'
  (cd "$repo_root" && find "${package_items[@]}" \( -type f -o -type l \) -print0) > "$files"
fi
while IFS= read -r -d '' file; do copy_file "$file"; done < "$files"

# Generated outputs required by the `package` profile are not versioned; they come from the build.
required="$staging/required"
node -e '
  const { readManifest } = require(process.argv[1]);
  const files = readManifest(process.argv[2]).profiles.package.requiredFiles;
  process.stdout.write(files.map((file) => file + "\0").join(""));
' "$script_dir/validate-release-artifact.js" "$repo_root/release-artifacts.json" > "$required"
while IFS= read -r -d '' file; do
  [[ -e "$payload/$file" ]] || copy_file "$file"
done < "$required"
node "$script_dir/credential-check.js" "$payload"

# Agent and local-environment metadata shipped by dependencies is not runtime code.
internal_names=(-name .claude -o -name .codex -o -name .agents -o -name CLAUDE.md -o -name AGENTS.md -o -name '.env*')
(
  cd "$payload"
  composer install --no-dev --no-interaction --prefer-dist
  composer dump-autoload -o --no-interaction
  rm -f composer.json composer.lock
  [[ ! -d vendor ]] || find vendor \( "${internal_names[@]}" \) -prune -exec rm -rf -- {} +
  find ./assets -type f -name '*.css' ! -name '*.min.css' -delete
  find ./assets -type f -name '*.js' ! -name '*.min.js' -delete
  find . -type f -name .DS_Store -delete
)
leaked=$(cd "$payload" && find . \( "${internal_names[@]}" \) -print)
[[ -z "$leaked" ]] || { echo "Internal content in the package: $(echo $leaked)" >&2; exit 1; }
# Scan again after Composer: dependencies can add files the first scan never saw.
node "$script_dir/credential-check.js" "$payload"

node "$script_dir/verify-integrity.js" --root "$payload" --manifest "$repo_root/integrity-manifest.json"
node "$script_dir/validate-release-artifact.js" --root "$payload" --profile package

# The ZIP is built and checked in a private folder next to the output, then hard-linked into place:
# the link fails if the path appeared meanwhile, so another process's file is never touched.
pending=$(mktemp -d "$(dirname -- "$output")/.woocommerce-mercadopago-zip.XXXXXX")
trap 'rm -rf -- "$staging" "$pending"' EXIT
candidate="$pending/package.zip"
(
  cd "$staging"
  zip -q -rX "$candidate" woocommerce-mercadopago -x '*/.git/*'
)

# The ZIP must contain exactly the validated payload: nothing dropped, nothing extra.
(cd "$staging" && find woocommerce-mercadopago -type f | LC_ALL=C sort) > "$staging/expected-inventory"
unzip -Z1 "$candidate" | grep -v '/$' | LC_ALL=C sort > "$staging/zip-inventory"
if ! cmp -s "$staging/expected-inventory" "$staging/zip-inventory"; then
  echo 'The ZIP differs from the validated package inventory; no ZIP was kept.' >&2
  exit 1
fi
if ! ln -- "$candidate" "$output" 2>/dev/null; then
  if [[ -e "$output" || -L "$output" ]]; then echo 'The output ZIP already exists; choose a new path.' >&2
  else echo 'Could not place the ZIP at the output path (the folder must allow hard links).' >&2; fi
  exit 1
fi
shasum -a 256 "$output"
echo "Package created: $output"
