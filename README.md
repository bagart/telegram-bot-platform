#todo
-telegram-bot-management-lib - Postamt
-telegram-bot-lib - Postbote
-telegram-bot-basic lib - PostboteLaravel
# Telegram Bot Manager

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Development](#development)
- [Docker](#docker)
- [Quick Commit](#quick-commit)
- [Technical Details](#technical-details)
- [Structure](#structure)
- [Contributing](#contributing)
- [License](#license)

## Overview

**Telegram Bot Manager** is a platform for hosting Telegram bots and managing their modules.

## Quick Start

```bash
# Setup (installs deps, builds assets, generates key, runs migrations)
./cmd/dev/setup

# Start development environment
./cmd/docker/up

# Check environment health
./cmd/dev/doctor

# Run checks
./cmd/dev/check

# Fix formatting
./cmd/dev/fix

# Commit
./cmd/git/quick-commit "fix: description"
```

## Development

### Library Developers

Libraries live in `misc/BAGArt/`. Clone them first:

```bash
mkdir -p misc/BAGArt
cd misc/BAGArt
git clone git@github.com:bagart/async-kernel.git php-async-kernel-lib
git clone git@github.com:bagart/ask-client.git php-async-kernel-client
git clone git@github.com:bagart/ask-client-redis.git php-async-kernel-client-redis
git clone git@github.com:bagart/telegram-bot-lib.git
git clone git@github.com:bagart/telegram-bot-basic-lib.git
git clone git@github.com:bagart/telegram-bot-management-lib.git
```

Then run setup — it auto-detects local libs and configures Composer path repositories:

```bash
./cmd/dev/setup
```

### Colleagues

No need to clone libraries manually. Just:

```bash
./cmd/dev/setup
```

Libraries install from published [Packagist](https://packagist.org/packages/bagart/) packages automatically.

## Docker

```bash
./cmd/docker/up              # start dev stack
./cmd/docker/up ai           # start with OmniRoute AI profile
./cmd/docker/down            # stop (keeps volumes)
./cmd/docker/logs            # tail logs
./cmd/docker/ps              # container status
./cmd/docker/build           # rebuild images
```

See [docs/docker/README.md](docs/docker/README.md) for full Docker documentation.

## Quick Commit

Run fast local validation and create a commit:

```bash
./cmd/git/quick-commit "fix: description"
```

The command does not bypass Git hooks or security checks.
CI remains authoritative.

### Other Commands

```bash
./cmd/dev/setup        # bootstrap repository
./cmd/dev/doctor       # diagnose environment
./cmd/dev/check        # run baseline checks
./cmd/dev/fix          # auto-fix formatting
./cmd/dev/test         # run tests
./cmd/dev/lint         # run linters
./cmd/dev/security     # run security checks
./cmd/deps/audit       # audit dependencies
./cmd/deps/outdated    # check for updates
./cmd/ci/validate      # validate repository integration
```

## Test of Work

### With Laravel
Monitor of not processed message by 1 token
```bash
 ./artisan tg:fetch [[TOKEN]] --show
```

ping-pong by 1 token
```bash
 ./artisan tg:fetch [[TOKEN]] --echo
```

### Without Laravel

Example with DTO
LongPolling "getUpdate" from Telegram Bot Api
mode: --echo (ping-pong)
mode: --show (pry traffic)

```bash
export TELEGRAM_BOT_TOKEN=xxx:xxx
php misc/BAGArt/TelegramBotBasic/RawExamples/GetUpdateDTOWithPollerExample.php --echo --show
```

## Technical Details

- **PHP**: Version **8.5 FPM** is used for optimal performance in both development and production environments.
- **Laravel Framework**: is simple and useful Framework based on PHP. 
- **PostgreSQL**: Version **17** is most Powerful SQL DB.
- **Redis**: Used for caching, session management and queues.
- **Caddy**: Used as the web server to serve the Laravel application with automatic HTTPS.
- **Docker Compose**: Orchestrates the services, simplifying the process of starting and stopping the environment.
- **Health Checks**: Implemented in the Docker Compose configurations and Laravel application to ensure all services are operational.

## Structure

- **root**: Laravel application
- **cmd/**: Developer CLI
  - `cmd/dev/` — developer lifecycle (setup, doctor, check, fix, test, lint, security)
  - `cmd/docker/` — Docker operations (up, down, build, logs, ps, scan)
  - `cmd/git/` — Git workflow (commit, quick-commit, prepush)
  - `cmd/deps/` — dependency operations (audit, outdated, update)
  - `cmd/ci/` — CI validation (check, validate)
  - `cmd/lib/` — shared shell infrastructure
- **docker/**: Dockerfiles and configuration
  - `docker/caddy/` — Caddy web server config
  - `docker/common/` — shared PHP-FPM
  - `docker/production/` — production nginx
- **docs/**: Documentation
- **misc/BAGArt/**: BAGArt libraries (path repositories)
- **app/Services**: DDD Services
- **modules**: Telegram Bot Modules

## Contributing

Contributions are welcome! Whether you find a bug, have an idea for improvement, or want to add a new feature, your input is valuable.

### How to Contribute

1. **Fork the Repository:**

   Click the "Fork" button at the top right of this page to create your own copy of the repository.

2. **Clone Your Fork:**

```bash
    git clone https://github.com/your-user-name/TelegramBotManager.git
    cd TelegramBotManager
```

3. Create a Branch:

```bash
    git checkout -b your-feature-branch
```

4. Make Your Changes.

   Implement your changes or additions.
   Please, do not use Facades

5. Commit Your Changes:

```bash
git commit -m "Description of changes"
```

6. Push to Your Fork:

```bash
    git push origin feature-branch
```

7. Submit a Pull Request:
    - Go to the original repository.
    - Click on "Pull Requests" and then "New Pull Request."
    - Select your fork and branch, and submit your pull request.

## License

This project is licensed under the MIT License. See the LICENSE file for more details.
