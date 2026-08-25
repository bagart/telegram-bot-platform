# Docker

Development and production Docker environments for Telegram Bot Manager.

## Quick Start

```bash
# Clone and setup
./cmd/dev/setup

# Start development stack
./cmd/docker/up

# Start with AI profile (OmniRoute)
./cmd/docker/up ai

# Start with STT profile (Local Whisper) — requires STT_WHISPER_ENABLED=true in .env
./cmd/docker/up stt
```

## Development Stack

Services: caddy, php-fpm, workspace, postgres, redis, swagger, xhprof-inbox

```bash
./cmd/docker/up              # start all dev services
./cmd/docker/up ai           # start with OmniRoute AI profile
./cmd/docker/up stt          # start with Local Whisper STT profile (opt-in)
./cmd/docker/down            # stop (keeps volumes)
./cmd/docker/down -v         # stop and remove volumes (destructive)
./cmd/docker/logs            # tail all logs
./cmd/docker/logs php-fpm    # tail specific service
./cmd/docker/logs whisper    # tail Local Whisper logs (when profile is on)
./cmd/docker/ps              # show container status
./cmd/docker/build           # rebuild images (uses cache)
./cmd/docker/build --no-cache  # rebuild without cache (slow)
./cmd/docker/scan            # security scan interface
```

## Production Stack

Services: web (nginx), php-fpm, postgres, redis

```bash
docker compose -f docker-compose.prod.yaml up --build -d
```

Production has health checks, restart policies, and immutable build artifacts.
No workspace, Xdebug, XHProf, Swagger, or OmniRoute.

## AI Profile (OmniRoute)

OmniRoute provides an OpenAI-compatible API at `/v1/*`.

```bash
# Set secrets in .env first
./cmd/docker/up ai

# Verify
curl http://localhost/v1/models
```

Required `.env` variables:
- `OMNIROUTE_JWT_SECRET`
- `OMNIROUTE_API_KEY_SECRET`
- `OMNIROUTE_PASSWORD`

The `/v1/*` namespace is reserved for OmniRoute.
Laravel routes must not claim this prefix.

## STT Profile (Local Whisper)

Opt-in OpenAI-compatible speech-to-text (speaches, faster-whisper on CPU).
Disabled by default: while `STT_WHISPER_ENABLED` is not `true`, no image is
pulled, no container starts and no model weights are downloaded — for both
dev and production stacks.

```bash
# 1. Set in .env (STT_WHISPER_API_KEY must be NON-empty — see gotchas)
STT_WHISPER_ENABLED=true
STT_WHISPER_MODEL=Systran/faster-whisper-small   # any faster-whisper HF ID

# 2. Start (pulls the ~2 GB image)
./cmd/docker/up stt          # dev
docker compose -f docker-compose.prod.yaml --profile stt up -d   # prod

# 3. Download model weights once (~500 MB small / ~3 GB large-v3)
curl -X POST -H "Authorization: Bearer $STT_WHISPER_API_KEY" \
     "http://localhost:${STT_WHISPER_PORT:-8000}/v1/models/$STT_WHISPER_MODEL"

# 4. Verify
curl -H "Authorization: Bearer $STT_WHISPER_API_KEY" \
     "http://localhost:${STT_WHISPER_PORT:-8000}/health"
```

Required/optional `.env` variables:
- `STT_WHISPER_ENABLED` — the opt-in switch; `false` (default) means nothing
  is pulled, started or downloaded
- `STT_WHISPER_API_KEY` — **must be non-empty**: current speaches images
  answer `403 Not authenticated` on every endpoint, `/health` included,
  when the configured key is blank; generate one with `openssl rand -hex 16`
- `STT_WHISPER_PORT` (default `8000`)
- `STT_WHISPER_MODEL` (HuggingFace ID)

Gotchas & notes:
- Current speaches images ignore `PRELOAD_MODELS` and do not download models
  lazily — use the `POST /v1/models/{model_id}` call above; weights persist
  in the `whisper-models` volume.
- speaches expects full HuggingFace model IDs (`Systran/faster-whisper-*`),
  so point the STT module at it via the admin custom provider JSON with
  `base_url http://localhost:8000/v1` (host) or `http://whisper:8000/v1`
  (from inside the docker network) and the same `model` value.
- Measured on a 24-thread dev box, 11 s speech sample, int8:
  `small` ≈ 6 s p50 (fits the module's 30 s budget), `large-v3` ≈ 28 s p50
  (RTF ~2.5 — over budget); both peak around 3.5–4.5 CPU threads,
  large-v3 resident RAM ≈ 3.5 GiB. Prefer `small`/`base` for CPU boxes.

## Ports

| Service    | Port  | Description              |
|------------|-------|--------------------------|
| Caddy      | 80    | HTTP (dev)               |
| Caddy      | 443   | HTTPS (dev)              |
| Postgres   | 5432  | Database                 |
| Vite       | 5173  | Frontend dev server      |
| OmniRoute  | 20128 | AI dashboard             |
| OmniRoute  | 20129 | AI API                   |
| Whisper    | 8000  | STT API (stt profile)    |

## Volumes

| Volume                    | Purpose               |
|---------------------------|-----------------------|
| postgres-data-development | Dev database data     |
| postgres-data-production  | Prod database data    |
| omniroute-data            | OmniRoute AI data     |
| whisper-models            | Local Whisper weights (stt profile, dev) |
| whisper-models-production | Local Whisper weights (stt profile, prod) |
| laravel-storage-production| Prod storage          |
| laravel-public-assets     | Prod frontend assets  |

## Caddy Routing

| URL                          | Target                  |
|------------------------------|-------------------------|
| `http://localhost/`          | Laravel app             |
| `http://telegram.localhost/` | Telegram bot webhooks   |
| `http://management.localhost/`| Bot management         |
| `http://xhprof.localhost/`   | XHProf viewer          |
| `http://swagger.localhost/`  | Swagger API docs       |
| `http://localhost/v1/*`      | OmniRoute (AI profile) |

## Troubleshooting

**Port already in use:**
```bash
./cmd/docker/ps  # check what's running
./cmd/docker/down  # stop everything
```

**AI profile fails:**
- Ensure `OMNIROUTE_JWT_SECRET`, `OMNIROUTE_API_KEY_SECRET`, `OMNIROUTE_PASSWORD` are set in `.env`
- Run `./cmd/dev/doctor` to check

**STT profile refused (`up stt` exits immediately):**
- Set `STT_WHISPER_ENABLED=true` in `.env` — this is the explicit opt-in switch

**Whisper answers `403 Not authenticated` on everything:**
- Set a non-empty `STT_WHISPER_API_KEY` (blank key = lockout on current
  speaches images), then recreate the container: `./cmd/docker/up stt`

**Whisper returns "model … is not installed locally":**
- Download weights once:
  `curl -X POST -H "Authorization: Bearer $STT_WHISPER_API_KEY" http://localhost:${STT_WHISPER_PORT:-8000}/v1/models/$STT_WHISPER_MODEL`

**Caddy 502 for OmniRoute:**
- Run `./cmd/docker/up ai` to start the OmniRoute container
- Without the AI profile, `/v1/*` returns a 503 with instructions
