# Captain's Log revision verification

Last reviewed: 2026-08-17

This document records the requested revisions, how they are currently implemented, the files involved, and the evidence used to verify them. A later request sometimes superseded part of an earlier request; those cases are called out explicitly.

## 1. Hide and Delete actions at the bottom of the editor

**Status:** Implemented and source-verified.

The Hide/Restore and Delete controls were removed from timeline cards and placed in the action area at the bottom of the log-entry aside. The action area is displayed only while editing an existing entry with valid visibility and deletion URLs.

**Files:**

- `resources/views/logs/show.blade.php` — owns `#composer-entry-actions`, `data-composer-visibility`, and `data-composer-delete` at the bottom of the aside.
- `resources/js/app.js` — `configureComposer()` supplies the current entry's visibility/delete URLs and shows the action area only in edit mode.

**Verification:** The daily-log feature test checks for `data-composer-entry-actions`; source inspection confirms the buttons are inside the aside after the composer content.

## 2. Authenticated session keep-alive over AJAX

**Status:** Implemented and tested.

Authenticated pages expose a protected keep-alive URL through a body data attribute. JavaScript posts to it every five minutes and also pings when a stale background tab becomes visible. The request includes the CSRF token and same-origin credentials. A `401` or `419` stops the timer, and temporary network errors do not interrupt the page.

**Files:**

- `app/Http/Controllers/SessionKeepAliveController.php` — updates `_keep_alive_at` in the session and returns HTTP 204.
- `routes/web.php` — registers `POST /session/keep-alive` inside the authenticated route group.
- `resources/views/layouts/app.blade.php` — exposes `data-session-keepalive-url` only in the authenticated layout.
- `resources/js/app.js` — implements `startSessionKeepAlive()`.
- `tests/Feature/SessionKeepAliveTest.php` — verifies authenticated access and guest rejection.

## 3. JavaScript-generated HTML moved into Blade templates; Blade components removed

**Status:** Implemented and source-verified.

Reusable DOM structures are defined as native HTML `<template>` blocks. JavaScript clones a template and fills values, text, state, and attributes without defining the HTML structure or Tailwind class lists. The former Breeze `<x-...>` component calls were replaced with normal Blade layouts, includes, sections, and HTML, and the obsolete component files were removed.

**Files:**

- `resources/views/partials/javascript-templates.blade.php` — owns toast, modal, option, AJAX method, time-slot row, and time-picker dialog templates.
- `resources/js/app.js` — uses `cloneTemplate()` and DOM value/state updates.
- `resources/views/layouts/app.blade.php`, `resources/views/layouts/guest.blade.php`, and `resources/views/welcome.blade.php` — include the JavaScript template partial.
- `resources/views/auth/*.blade.php`, `resources/views/profile/**/*.blade.php`, `resources/views/layouts/*.blade.php`, and the other application views — use normal HTML rather than `<x-...>` components.
- `resources/views/components/` — the former component files were removed.

**Verification:** Source searches find no `<x-...>` tags, no `createElement`, no `innerHTML` assignment, and no `insertAdjacentHTML` in `resources/js/app.js`. The component directory contains no remaining Blade component files.

## 4. Attach Media collapsed by default

**Status:** Implemented and tested.

The media controls are inside a native `<details>` disclosure. New entries start collapsed. Existing entries also start collapsed unless `data-has-media` reports at least one attachment, in which case the disclosure opens automatically. The Show/Hide label follows the disclosure state.

**Files:**

- `resources/views/logs/partials/composer.blade.php` — defines the Attach Media `<details>` panel and its upload/recording controls.
- `resources/views/logs/show.blade.php` — exposes `data-has-media` on recorded timeline entries.
- `resources/js/app.js` — `configureComposer()` sets `mediaPanel.open` only for edited entries that already have media; disclosure initialization updates its label.
- `tests/Feature/CaptainsLogTest.php` — verifies entries expose media state and the composer media panel.

## 5. Descriptive IDs and semantic first classes for `<div>` elements

**Status:** Implemented and regression-tested.

One-off container/message divs have descriptive, unique IDs. Structures rendered repeatedly use a purpose-specific class as their first class, for example `timeline-item`, `block-attachment`, `calendar-day-summary`, `event-definition-name`, and `modal-panel`.

**Files:**

- `resources/views/**/*.blade.php` — the convention is applied throughout layouts, authentication, calendar, API usage, dashboard, events, logs, profiles, settings, event setup, the landing page, and JavaScript templates.
- `tests/Unit/BladeDivSemanticsTest.php` — scans every Blade `<div>`, rejects anonymous/utility-first divs, and rejects duplicate static div IDs within a view.

**Verification:** Both Blade div-semantics tests pass.

## 6. Stable time-picker height and position

**Status:** Implemented; the manual-mode portion was superseded by revision 9.

The time-picker panel uses `max-h-[80dvh]`, and its positioning logic no longer writes a pixel `max-height` or varies the maximum between modes. The original manual/visual mode switch was first fixed to hide the wheel correctly, but the manual input was later removed entirely as requested in revision 9. The stable 80% viewport maximum remains in the final picker.

**Files:**

- `resources/views/partials/javascript-templates.blade.php` — applies `max-h-[80dvh]` to the time dialog.
- `resources/js/app.js` — positions the dialog without mutating its CSS maximum height.
- `tests/Feature/CaptainsLogTest.php` — verifies the `80dvh` class and absence of the removed manual control.

## 7. Open timeline slots default to their start time

**Status:** Implemented and tested.

Click position is no longer interpolated into a five-minute value between the slot boundaries. Every click on an open slot passes the slot's exact `data-from` value to the composer.

**Files:**

- `resources/views/logs/show.blade.php` — renders `data-from` and `data-to` on open timeline slots.
- `resources/js/app.js` — uses `configureComposer({time: timelineItem.dataset.from})`.
- `tests/Feature/CaptainsLogTest.php` — verifies the start-time behavior and confirms the old `gapTime()` calculation is absent.

## 8. Delete modal layering and full-day AJAX refresh

**Status:** Implemented and browser/test verified.

Confirmation modals render at z-index 120, above the composer overlay at z-index 80. After an entry is deleted, the aside closes and JavaScript fetches the current dated-log URL, parses the response, and replaces both the complete daily heading and complete daily planner container. It does not patch only one timeline card and does not reload a dated-log page. The same full-day refresh is used after edits and other day mutations so event counts, schedules, gaps, and entries remain synchronized.

**Files:**

- `resources/views/partials/javascript-templates.blade.php` — sets the modal backdrop to z-index 120.
- `resources/views/logs/show.blade.php` — marks `#daily-log-page-heading` and `#daily-log-page-container` as day-view fragments.
- `resources/js/app.js` — implements `refreshDayView()`, `refreshDayViewOrReload()`, AJAX delete/edit handling, overlay closing, and fragment replacement.
- `app/Http/Controllers/LogBlockController.php` and `app/Http/Controllers/TaskEventController.php` — return JSON responses used by the AJAX workflow.
- `tests/Feature/CaptainsLogTest.php` — checks the fragments, refresh implementation, and modal layer.

## 9. Simplified live-update time picker

**Status:** Implemented and browser/test verified.

The bottom Manual input, Cancel, and Use this time controls were removed. Wheel scrolling/clicking updates the underlying time immediately. The single top Cancel restores the exact value from before the dialog opened. Clicking outside keeps the latest selection and closes. After one explicit hour click and one explicit minute click, the chosen time is committed and the dialog closes automatically. In 24-hour mode there is no AM/PM column; 12-hour mode retains that column.

**Files:**

- `resources/views/partials/javascript-templates.blade.php` — contains only the wheel UI and top `data-time-dialog-cancel` action.
- `resources/js/app.js` — implements live value updates, original-value rollback, outside close, and hour-plus-minute auto-close.
- `tests/Feature/CaptainsLogTest.php` — verifies the top cancel exists and the removed manual/apply controls do not.

## 10. Stable scrollbar gutter while overlays are open

**Status:** Implemented and browser-verified.

The document root permanently reserves scrollbar space. Adding `overflow-hidden` to the body while an aside is open no longer changes the calendar/page width or causes a horizontal jump.

**Files:**

- `resources/css/app.css` — applies `scrollbar-gutter: stable` to `html` in the base layer.
- `resources/js/app.js` — overlay open/close still toggles body overflow without affecting the reserved gutter.

**Verification:** Browser computed style reports `scrollbarGutter: "stable"`.

## 11. OpenRouter image-generation `compact()` error

**Status:** Fixed and regression-tested.

The image request previously called `compact('model', 'prompt')` inside an arrow-function closure. PHP could not resolve those captured variables for `compact()`, producing `compact(): Undefined variable $model`. The payload now uses explicit `model` and `prompt` keys. The same latent closure/`compact()` problem was removed from speech-to-text's `input_audio` payload.

**Files:**

- `app/Services/OpenRouterService.php` — builds explicit image and transcription request arrays.
- `app/Http/Controllers/OpenRouterController.php` — validates the selected model/prompt, creates the generated-image block, stores returned image data, and creates the attachment.
- `tests/Feature/CaptainsLogTest.php` — sends `bytedance-seed/seedream-5-0-lite` with `a coffee mug with a lid`, verifies the outgoing OpenRouter payload, stored attachment, and API-call log.

## 12. Save edited log entries when the aside closes; red Cancel discards

**Status:** Implemented and browser/test verified.

Edit mode no longer shows Save changes. Close, backdrop click, and Escape send one AJAX PATCH, close the aside after success, and refresh the full day view. A red Cancel button beside Close discards the text/time draft without sending it. Pending drafts are saved before Hide, media upload, or recording so those actions do not silently lose edits. Delete intentionally removes the entry without first saving its discarded draft.

This behavior supersedes the earlier immediate event autosave inside the aside; standalone event-edit pages retain their own autosave behavior.

**Files:**

- `resources/views/logs/show.blade.php` — provides the top-right red `data-composer-cancel` button beside Close.
- `resources/views/logs/partials/composer.blade.php` — provides the edit/create form and status message.
- `resources/js/app.js` — `configureComposer()` hides submit in edit mode; `saveComposerDraft()` handles close-to-save; Cancel closes without saving; Close/backdrop/Escape save and refresh.
- `app/Http/Controllers/LogBlockController.php` and `app/Http/Controllers/TaskEventController.php` — accept the AJAX PATCH updates.
- `tests/Feature/CaptainsLogTest.php` — verifies the edit controls and close-to-save implementation markers.

## 13. Image entries use the log-entry edit and delete flow

**Status:** Implemented, regression-tested, and browser-verified.

Image entries no longer display their attachment filename, such as `generated.png`, or a separate **Delete media** link on the timeline. Clicking an image log entry opens the normal edit aside, automatically expands its media section, and shows thumbnails for the entry's attached images. The existing bottom **Delete entry** action is now the single deletion path for an image entry.

Deleting the entry closes the aside and refreshes the complete day timeline through AJAX. The backend deletes the stored image file and its attachment database record before deleting the log block, preventing the attachment row from being left orphaned by the nullable foreign key.

**Files:**

- `resources/views/logs/partials/block.blade.php` — renders images without their original filenames or per-image delete links; filenames and individual media actions remain available for non-image attachment types.
- `resources/views/logs/show.blade.php` — exposes each edited entry's image URLs through `data-edit-media`.
- `resources/views/logs/partials/composer.blade.php` — provides the existing-media thumbnail container inside the aside's media disclosure.
- `resources/views/partials/javascript-templates.blade.php` — provides the Blade-owned image-preview template used by the aside.
- `resources/js/app.js` — fills the preview template with the entry's image URLs when edit mode opens.
- `app/Http/Controllers/LogBlockController.php` — removes attachment files and records as part of deleting their log entry.
- `tests/Feature/CaptainsLogTest.php` — verifies the timeline omits the image filename/delete link and that deleting the block removes the stored file, attachment row, and log block.

**Browser verification:** Confirmed that an image timeline entry displays only its time and media label, opens with one thumbnail in the aside, and disappears after confirming **Delete entry**. A database and storage check confirmed that both the attachment record and image file were removed.

## Final verification

**Result: All requested revisions above are verified in the current working tree.**

Final checks run on 2026-08-17:

- `php artisan test` — **PASS:** 56 tests, 315 assertions.
- `php artisan view:cache` — **PASS:** every Blade template compiled successfully.
- `npm.cmd run build` — **PASS:** Vite produced the production CSS/JavaScript bundle. Vite emitted the existing non-blocking `postcss.config.js` module-type warning.
- `vendor/bin/pint --test` — **PASS:** 106 PHP files satisfy the configured Laravel formatting rules.
- `git diff --check` — **PASS:** no whitespace errors or conflict markers.
- Blade component audit — **PASS:** no `<x-...>` tags and no files remain in `resources/views/components/`.
- JavaScript HTML-construction audit — **PASS:** no `createElement`, `innerHTML` assignment, or `insertAdjacentHTML` remains in `resources/js/app.js`; reusable HTML is supplied by the Blade `<template>` partial.
- Blade div semantics audit — **PASS:** all one-off container/message divs have IDs, all repeated divs use an approved semantic first class, and static div IDs are unique within their source view.

Relevant browser verification performed during implementation also confirmed:

- modal z-index 120 appears above the composer z-index 80;
- edit and delete replace the complete day fragments without navigating;
- Close saves an edited entry and red Cancel discards it;
- the time picker keeps `80dvh`, rolls back from top Cancel, closes outside, and auto-closes after an hour and minute click;
- repeated open-slot clicks use the same exact slot start time;
- computed `scrollbar-gutter` is `stable`;
- image entries omit filenames and per-image delete links, show their thumbnails in the edit aside, and delete the log block, attachment row, and stored image together.
