# Captain's Log

A Laravel 10, Blade, Tailwind, and vanilla-JavaScript daily journal. The app combines refreshable day/calendar URLs with AJAX updates, repeating event buttons, private media, browser recording, and OpenRouter-powered chat, image generation, and speech-to-text.

## Requirements

- PHP 8.1+ with SQLite (or another Laravel-supported database)
- Composer
- Node.js and npm

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create an empty `database/database.sqlite`, then run:

```bash
php artisan migrate
npm install
npm run dev
php artisan serve
```

Register a user, open **Settings**, and save that user's OpenRouter API key. Uploaded and generated media is stored on Laravel's private `local` disk and served only through authenticated, ownership-checked routes; no public storage symlink is needed.

## Main behavior

- `/` is a fully interactive guest simulation. A one-year, HTTP-only opaque cookie maps each browser to an isolated guest account; only the token hash is stored in the database. Each guest receives a rolling demo covering the previous seven days plus the real current day.
- `/calendar/{YYYY-MM-DD}?view=day|week|month` and `/logs/{YYYY-MM-DD}` are refreshable URLs.
- Calendar mode, fetched model catalogs, selected chat model, selected image model, and theme are remembered in local storage.
- Each log block has created/updated timestamps and can be edited or deleted independently.
- Task clicks are committed immediately. Notes and media are an optional second step, so abandoning that page does not lose the event.
- Task definitions may be sticky or accessed from the daily dropdown, and may require a configured value.
- Image, audio, and video uploads share an attachment ledger; audio/video can also be recorded with `MediaRecorder` in a supported browser.
- Every OpenRouter request is recorded with operation, model, status, token usage, cost, duration, request ID, and error, and day-associated calls appear on that daily log.

## Verification

```bash
php artisan test
vendor/bin/pint --test
npm run build
php artisan view:cache
```

The feature suite covers dated/owned logs, block CRUD authorization, atomic task tracking, private media persistence, and OpenRouter response/cost logging.
