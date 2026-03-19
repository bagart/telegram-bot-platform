# Telegram Bot Manager

## Table of Contents

- [Overview](#overview)
- [Setup](#Setup)
    - [Run Laravel](#run-laravel)
        - [Development Environment](#development-environment)
        - [Production Environment](#production-environment)
    - [Init bot](#init-bot)
- [Technical Details](#technical-details)
- [Test Coverage](#test-coverage)
- [Structure](#structure)
- [Contributing](#contributing)
    - [How to Contribute](#how-to-contribute)
- [License](#license)

## Overview

**Telegram Bot Manager** is a useful platform for hosting TG bots and managing their modules.

## Setup

## load lib

```bash
mkdir -p misc/BAGArt
cd misc/BAGArt 
git clone git@github.com:bagart/telegram-bot-management-lib.git
git clone git@github.com:bagart/telegram-bot-basic-lib.git
git clone git@github.com:bagart/telegram-bot-lib.git
```

### Run Laravel

Docker env: https://docs.docker.com/guides/frameworks/laravel/development-setup/

#### Development Environment
```bash

#In case of a problem: UID/GUID
#cp .env.example .env
#nano .etc
#At the end of the file - uncommit changes and fix: UID/GID

docker compose up -d
docker compose exec workspace bash
```
inside container
```bash
composer install
npm install
npm run dev
```

#### Production Environment

```bash
docker compose -f compose.prod.yaml up --build -d
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
- **Nginx**: Used as the web server to serve the Laravel application and handle HTTP requests.
- **Docker Compose**: Orchestrates the services, simplifying the process of starting and stopping the environment.
- **Health Checks**: Implemented in the Docker Compose configurations and Laravel application to ensure all services are operational.

## Structure

- **root**: Laravel
- **./docker**: Docker Files https://docs.docker.com/guides/frameworks/laravel/development-setup/
  - common
  - dev
  - production
- **./app/Services**: DDD Services
  - **TelegramBot**: TelegramBot core (RAD. It will probably be allocated to the library)
  - **TelegramBotManagement**: TelegramBot Management with DB (@todo: UI)
- **./modules**: TelegramBot Modules (RAD. It will probably be allocated to the library)

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
