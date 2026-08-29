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
- `POST /api/v1/auth/register` creates a learner account, profile, and API token
- `POST /api/v1/auth/login` returns a learner API token
- `GET /auth/google/redirect` starts Laravel-owned Google OAuth
- `GET /auth/google/callback` handles the Google OAuth callback and redirects to the frontend
- `POST /api/v1/auth/oauth/exchange` exchanges the frontend callback code for a learner API token
- `GET /api/v1/auth/user` returns the authenticated learner and profile
- `POST /api/v1/auth/logout` revokes the current learner token
- `GET /api/v1/profile` returns learner profile, XP, hearts, streak, and avatar
- `PATCH /api/v1/profile` updates profile display name and avatar character
- `GET /api/v1/lesson-progress` lists authenticated learner progress
- `GET /api/v1/scenarios` lists published scenarios for the learner app
- `GET /api/v1/scenarios/{scenario}` returns the full published scene graph
- `POST /api/v1/lessons/{scenario}/complete` records completion, XP, attempts, best score, and streak progress
- `GET /api/v1/tts?text=...` returns cached/generated Lithuanian audio
- `POST /api/v1/speak-check` transcribes and evaluates an authenticated learner recording

## Notes

The backend now includes the first source-of-truth schema for characters, scenarios, scenes, NPC lines, goals, props, learner profiles, lesson progress, speaking attempts, AI prompts, generated audio, and AI evaluations. `DatabaseSeeder` imports the current restaurant MVP scenario so the API can serve migrated content immediately after seeding. TTS and speech evaluation now run through Laravel AI SDK entry points.

For local development without spending provider tokens, keep AI dev mode enabled:

```sh
KALBEK_AI_MODE=fake
KALBEK_FAKE_SPEECH_TRANSCRIPT="Ar turite maisto?"
KALBEK_FAKE_SPEECH_PASS=true
KALBEK_FAKE_SPEECH_FEEDBACK="Dev AI mode: speech accepted without calling an AI provider."
```

In fake mode, `/api/v1/tts` returns generated local WAV audio and `/api/v1/speak-check` returns deterministic feedback without calling OpenAI, OpenRouter, or any other AI provider. Set `KALBEK_AI_MODE=live` when you want the app to use configured AI providers.

For Google sign-in, configure these values in `.env`:

```sh
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
KALBEK_FRONTEND_URL=http://localhost:3000
```

The Google Cloud OAuth redirect URI must match `GOOGLE_REDIRECT_URI`.

The CMS is intentionally minimal at this stage. The next phase should build the Inertia React CMS resources around the new schema.
