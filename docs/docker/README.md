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
```

## Development Stack

Services: caddy, php-fpm, workspace, postgres, redis, swagger, xhprof-inbox

```bash
./cmd/docker/up              # start all dev services
./cmd/docker/up ai           # start with OmniRoute AI profile
./cmd/docker/down            # stop (keeps volumes)
./cmd/docker/down -v         # stop and remove volumes (destructive)
./cmd/docker/logs            # tail all logs
./cmd/docker/logs php-fpm    # tail specific service
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

## Ports

| Service    | Port  | Description              |
|------------|-------|--------------------------|
| Caddy      | 80    | HTTP (dev)               |
| Caddy      | 443   | HTTPS (dev)              |
| Postgres   | 5432  | Database                 |
| Vite       | 5173  | Frontend dev server      |
| OmniRoute  | 20128 | AI dashboard             |
| OmniRoute  | 20129 | AI API                   |

## Volumes

| Volume                    | Purpose               |
|---------------------------|-----------------------|
| postgres-data-development | Dev database data     |
| postgres-data-production  | Prod database data    |
| omniroute-data            | OmniRoute AI data     |
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

**Caddy 502 for OmniRoute:**
- Run `./cmd/docker/up ai` to start the OmniRoute container
- Without the AI profile, `/v1/*` returns a 503 with instructions
