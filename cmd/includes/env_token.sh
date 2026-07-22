# Load TELEGRAM_BOT_TOKEN from the project .env (run from project root) when the
# variable is not already set in the environment. Respects `"quoted"` values.
if [[ -z "${TELEGRAM_BOT_TOKEN:-}" && -f ".env" ]]; then
  _tg_token="$(sed -n 's/^TELEGRAM_BOT_TOKEN=//p' .env | tail -n 1)"
  _tg_token="${_tg_token%\"}"
  _tg_token="${_tg_token#\"}"
  if [[ -n "$_tg_token" ]]; then
    export TELEGRAM_BOT_TOKEN="$_tg_token"
  fi
  unset _tg_token
fi