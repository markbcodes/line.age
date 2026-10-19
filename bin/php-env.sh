# bin/php-env.sh — resolves SV_PHP for Git Bash on the Shader local, where
# `php` is not on PATH (F1=no, docs/ENVIRONMENTS.md) and .shader/bin/php.cmd
# is a cmd wrapper. Sourced by build.sh, deploy-plugin.sh, verify.sh.
#
# Resolution order:
#   1. $SV_PHP already set by the caller
#   2. `php` on PATH (hosted shells, most CI)
#   3. the php.exe that .shader/bin/php.cmd wraps, with its -c ini
#
# The extracted paths must not contain spaces (true for Shader's install
# path); if that ever changes, quote them at the call sites.

sv_resolve_php() {
  [ -n "${SV_PHP:-}" ] && return 0

  if command -v php >/dev/null 2>&1; then
    SV_PHP=php
    return 0
  fi

  local phpcmd="$ROOT/.shader/bin/php.cmd"
  if [ -f "$phpcmd" ]; then
    local exe ini
    exe=$(tr -d '\r' < "$phpcmd" | sed -n 's/^"\([^"]*\)".*/\1/p' | head -1)
    ini=$(tr -d '\r' < "$phpcmd" | sed -n 's/.* -c "\([^"]*\)".*/\1/p' | head -1)
    if [ -f "$exe" ]; then
      SV_PHP="$exe"
      [ -f "$ini" ] && SV_PHP="$SV_PHP -c $ini"
      return 0
    fi
  fi

  echo "no PHP found. Set SV_PHP." >&2
  return 1
}
