<p align="center">
  <img src="https://raw.githubusercontent.com/yardimli/captainslog/main/docs/images/captains-log-logo.png" alt="Captain's Log logo" width="190">
</p>

<h1 align="center">Captain's Log</h1>

<p align="center">
  A private, media-rich daily journal for recording ordinary missions, recurring events, and AI-assisted reflections.
</p>

<p align="center">
  <img alt="Laravel 10" src="https://img.shields.io/badge/Laravel-10-ff2d20?logo=laravel&logoColor=white">
  <img alt="PHP 8.1+" src="https://img.shields.io/badge/PHP-8.1%2B-777bb4?logo=php&logoColor=white">
  <img alt="Tailwind CSS" src="https://img.shields.io/badge/Tailwind_CSS-3-06b6d4?logo=tailwindcss&logoColor=white">
  <img alt="MariaDB" src="https://img.shields.io/badge/MariaDB-ready-003545?logo=mariadb&logoColor=white">
</p>

Captain's Log combines a calendar, block-based daily journal, private media library, repeatable event tracking, and OpenRouter tools in a responsive Laravel application. Every calendar and log URL is refreshable, while AJAX updates keep everyday interactions quick.

![Captain's Log landing page and guest simulation](https://raw.githubusercontent.com/yardimli/captainslog/main/docs/images/landing-demo.png)

## Features

### Calendar and daily logs

- Monthly, weekly, and daily calendar modes, with weekly view as the default.
- The selected calendar mode is remembered in browser local storage.
- Stable, refreshable URLs such as `/calendar/2026-08-16?view=week` and `/logs/2026-08-16`.
- Previous, Today, Next, and Calendar navigation on daily log pages.
- Responsive layouts designed for desktop, tablet, and mobile screens.
- Light and dark themes with the user's preference remembered locally.

### Block-based journal

- Daily logs are timelines made from independently managed content blocks.
- Free-text, event, chat, image, audio, video, and transcription content can live together on the same day.
- Every block records its creation and last-updated timestamps.
- Text blocks can be edited or deleted without replacing the rest of the daily log.
- Updates use normal HTML forms and vanilla-JavaScript AJAX while retaining usable, refreshable pages.

![Interactive daily log guest demo](https://raw.githubusercontent.com/yardimli/captainslog/main/docs/images/daily-log-demo.png)

### Repeating events and buttons

- Define reusable events with a friendly name, custom browser-selected color, and active status.
- Repeat events every day, on selected weekdays, or on selected days of the month.
- Assign one or more 24-hour time slots to the same repeating event.
- Make frequently used events sticky so their buttons appear in the daily timeline at their scheduled times.
- Access non-sticky events from an easy-to-reach dropdown.
- Clicking an event commits its timestamp immediately and increases its daily counter.
- Sticky buttons occupy their scheduled position in a 24-hour timeline, while completed events are placed at their actual click time.
- Optionally add notes, photos, uploaded audio, or newly recorded audio after an event is tracked.
- Events remain recorded even if the optional notes screen is abandoned.
- Add required dropdown values to an event, such as a stress level from 1 to 5 or the pet receiving medication.

### Images, audio, and video

- Upload image, audio, and video files to a daily log.
- Take photos and capture audio or video using browser media APIs where supported.
- Preview recording state and supported browser formats before uploading.
- Track every file through a dedicated attachment ledger associated with its owner, day, and log block.
- Store uploaded and generated media on Laravel's private local disk.
- Serve attachments only through authenticated, ownership-checked routes; no public storage symlink is required.

> Recording requires browser permission and a secure context. `localhost` and `127.0.0.1` are accepted secure development contexts by modern browsers.

### OpenRouter integration

Each user supplies their own OpenRouter API key from **Settings**. The key is encrypted in the database, is never returned to client-side JavaScript, and is used only by the Laravel backend.

- Chat with an OpenRouter model and append both sides of the conversation to the selected daily log.
- Include uploaded images in multimodal chat requests.
- Generate images and attach the results to the current day.
- Transcribe uploaded or recorded audio into a daily-log text block.
- Fetch available chat and image-generation models.
- Remember fetched model catalogs and each user's selected chat and image models in local storage.
- Record each API operation, model, status, request ID, token usage, reported cost, duration, and error details.
- Review all OpenRouter API calls, token counts, costs, and linked log days on the dedicated API usage page.

The integration follows OpenRouter's APIs for [chat completions](https://openrouter.ai/docs/api/api-reference/chat/create-a-chat-completion), [image generation](https://openrouter.ai/docs/api/api-reference/images/generate-an-image), [image models](https://openrouter.ai/docs/api/api-reference/images/list-image-generation-models), [speech-to-text](https://openrouter.ai/docs/api/api-reference/stt/create-transcription), and the [model catalog](https://openrouter.ai/docs/api/api-reference/models/list-all-models-and-their-properties).

### Automatic sensors

- Link GitHub to add commit project names at their commit times. Commits for the same project and hour share one timeline entry, with every individual commit available in its side panel.
- Install the included Manifest V3 Chrome extension from `public/captainslog-chrome-extension` to track active browsing hostnames, including subdomains.
- Browsing activity is grouped into one log entry per hour, with full-hostname totals available from the entry's side panel.
- The extension sends a one-minute heartbeat while Chrome is active; three minutes without activity closes the session.
- Pairing uses a random extension key opened against the configured Captain's Log URL. Laravel stores only its SHA-256 hash.
- The extension defaults to `http://127.0.0.1:8016/`, and its options page supports a different app URL.
- Only the active site's origin is transmitted; page paths, query strings, and page titles never leave the extension.

### Live guest simulation

The landing page runs the real journal code without requiring registration.

- Every browser receives an isolated guest account through a one-year HTTP-only cookie.
- Only a hash of the opaque guest token is stored in the database.
- The demo is populated with the previous seven days plus the real current day.
- Demo entries parody spacefaring television through the life of a yoga instructor and anger-management therapist who is trying to lose weight while caring for two medicated dogs and one insubordinate cat.
- Guest notes, edits, deletions, predefined events, and counters persist across refreshes for that browser.

### Authentication and account security

- Laravel Breeze registration, login, password reset, email verification, and password confirmation flows.
- Branded, responsive authentication pages with full light/dark support.
- Per-user authorization for daily logs, blocks, events, media, and API-call history.
- Encrypted per-user OpenRouter credentials.
- Account profile, password, and account deletion controls.

## Technology

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.1+, Laravel 10, Laravel Breeze |
| Views | Blade templates and semantic HTML |
| Frontend | Tailwind CSS, vanilla JavaScript, Fetch/AJAX |
| Database | MariaDB/MySQL migrations and Eloquent ORM |
| Media | `MediaRecorder`, `getUserMedia`, private Laravel storage |
| AI | OpenRouter chat, image generation, model discovery, and speech-to-text APIs |
| Build | Vite and npm |
| Tests | PHPUnit with Laravel feature tests |

## Local installation

### Requirements

- PHP 8.1 or newer with the extensions required by Laravel and MariaDB/MySQL
- MariaDB or MySQL
- Composer
- Node.js and npm

### 1. Install dependencies

```bash
git clone https://github.com/yardimli/captainslog.git
cd captainslog
composer install
npm install
```

Copy `.env.example` to `.env`, then generate the application key:

```bash
cp .env.example .env
php artisan key:generate
```

On Windows PowerShell, use `Copy-Item .env.example .env` instead of `cp`.

### 2. Configure MariaDB

Create an empty database named `captainslog`, then update the database section in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=captainslog
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

Run the Laravel migrations:

```bash
php artisan migrate
```

### 3. Build and run

For development, run Vite and Laravel in separate terminals:

```bash
npm run dev
```

```bash
php artisan serve --port=8016
```

Open [http://127.0.0.1:8016](http://127.0.0.1:8016) to try the guest simulation, or register an account to use the complete application.

For a production asset build, use:

```bash
npm run build
```

## OpenRouter setup

1. Register or sign in.
2. Open **Settings**.
3. Paste your own OpenRouter API key and save it.
4. Open a daily log and fetch the available models.
5. Choose chat and image models; the browser remembers those choices locally.

OpenRouter usage is billed by OpenRouter according to the selected model. Captain's Log stores the usage and cost metadata returned by the API so it can be reviewed by day.

## Verification

```bash
php artisan test
vendor/bin/pint --test
npm run build
php artisan view:cache
```

The feature suite covers authentication, refreshable and user-owned logs, block CRUD authorization, immediate event tracking, custom event colors, private media persistence, recording controls, isolated guest workspaces, and OpenRouter response and cost logging.

## Project assets

- Generated brand artwork: `docs/images/captains-log-logo.png`
- Application logo component: `resources/views/components/application-logo.blade.php`
- Browser favicon: `public/favicon.svg`
- README screenshots: `docs/images/landing-demo.png` and `docs/images/daily-log-demo.png`
