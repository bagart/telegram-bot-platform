# Locate the repository root from the script's physical location.
# Works regardless of the caller's current directory and when invoked
# through a symlink or a global PATH entry.
#
# Source after defining your own BASH_SOURCE-relative logic; sets REPO_ROOT.
# Marker-based detection (composer.json) makes resolution robust even when
# BASH_SOURCE contains ".." segments or the script is reached via symlinks.

_lib_root_dir() {
  local dir
  dir="$(cd -P "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
  while [[ ! -f "$dir/composer.json" && "$dir" != "/" ]]; do
    dir="$(dirname "$dir")"
  done
  [[ -f "$dir/composer.json" ]] && echo "$dir"
}

REPO_ROOT="${REPO_ROOT:-$(_lib_root_dir)}"
if [[ -z "$REPO_ROOT" ]]; then
  echo "ERROR: unable to locate repository root (composer.json not found)" >&2
  exit 4
fi
export REPO_ROOT