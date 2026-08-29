# Kalbek API

Kalbek API is the Laravel backend for Kalbek, a Lithuanian speaking-practice product. It is intended to become the source of truth for learner accounts, scenarios, characters, progress, content publishing, and AI orchestration.

The application exposes two surfaces:

- **API routes** under `/api/v1` for the learner frontend.
- **Web routes** for the internal CMS, rendered with Inertia, React, and TypeScript.

## Stack

- Laravel 13
- Laravel AI SDK
- Laravel Sanctum
- Inertia 3
- React 19 + TypeScript
- MySQL 8.4
- Redis
- Nginx + PHP-FPM
- Docker Compose

## Local Setup

For a first-time setup, run:

```sh
make setup
```

For later starts, run:

```sh
make start
```

If you prefer to run the first-time steps manually:

```sh
cp .env.example .env
make build-and-start
make key
make migrate
```

The local services are available at:

- CMS: `http://localhost:8080/cms`
- API health: `http://localhost:8080/api/v1/health`
- Vite dev server: `http://localhost:5173`
- Mailpit: `http://localhost:8025`
- MySQL: `localhost:3307`
- Redis: `localhost:6380`

## Common Commands

```sh
make start          # Start containers
make restart        # Restart containers
make stop           # Stop containers
make build          # Build Docker images
make build-start    # Build images and start containers
make setup          # First-time setup: env file, build, start, key, migrate
make logs           # Tail container logs
make shell          # Open a shell in the app container
make key            # Generate APP_KEY
make migrate        # Run migrations
make test           # Run PHPUnit
make npm-build      # Build the Inertia CMS frontend
make format         # Format PHP with Pint
make lint           # Check PHP formatting with Pint
```

## Current Routes

- `GET /` redirects to `/cms`
- `GET /cms` renders the CMS dashboard
- `GET /api/v1/health` returns service health
- `GET /api/v1/user` returns the authenticated Sanctum user

## Notes

The CMS is intentionally minimal at this stage. The next phase should introduce the domain schema for scenarios, scenes, goals, characters, translations, publishing state, and AI prompt/agent configuration.
