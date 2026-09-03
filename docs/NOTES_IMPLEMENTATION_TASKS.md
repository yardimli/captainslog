# Notes Section Implementation Tasks

This checklist tracks the work required to finish the Total Record Notes section.

## Scope

- Notes are a standalone knowledge library.
- Notes may link to an existing daily log at an exact date and time.
- A link may optionally point to a specific existing log block.
- Notes may contain lightweight checklist tasks linked to a note.
- Task scheduling, reminders, recurrence, and calendar events remain owned by the Log section and are intentionally excluded from Notes.

## Database foundation

- [x] Create notebook stack storage.
- [x] Create notebooks with ownership, ordering, default, pinning, and soft deletion.
- [x] Create rich notes with HTML/structured content, searchable plain text, source metadata, location, templates, archive state, pinning, and soft deletion.
- [x] Create immutable note version snapshots.
- [x] Create hierarchical tags and the note/tag pivot.
- [x] Create note-to-note links for internal links and backlinks.
- [x] Create date/time links from notes to daily logs and optional log blocks.
- [x] Create private note attachment metadata and extracted-text storage.
- [x] Create saved searches and polymorphic shortcuts.
- [x] Create spaces, space membership, and space items.
- [x] Create invited shares and hashed public links.
- [x] Create per-user Notes preferences.
- [x] Create lightweight Notes task records without scheduling, reminders, or recurrence fields.
- [x] Add a migration test that verifies every required Notes table and the date/time log-link contract.
- [ ] Extend the migration test to verify important indexes and foreign-key deletion rules.
- [ ] Decide whether deleted notebook/stack/tag names can be reused, then adjust unique indexes if required.
- [ ] Add service-level validation that a space item references exactly one note or notebook.
- [ ] Add service-level validation that a share has exactly one recipient user or recipient email.
- [ ] Add service-level validation that a linked log block belongs to the selected daily log and user.

## Log simplification prerequisite

- [x] Remove manual image, audio, and video upload controls from log entries.
- [x] Remove browser audio and video recording controls from log entries and tracked events.
- [x] Remove manual log attachment upload/delete routes.
- [x] Remove log audio transcription routes and controls.
- [x] Convert existing long-text attachments into ordinary text log entries before removing their table.
- [x] Remove long-text models, controllers, routes, relationships, rendering, and composer controls.
- [x] Preserve AI-generated log images and their authenticated private-file route.
- [x] Stop showing attachment totals on calendar days.

## Models and authorization

- [x] Create Eloquent models for every Notes entity table; the `note_tag` pivot intentionally uses Laravel's pivot support.
- [x] Add typed relationships between stacks, notebooks, notes, tags, links, logs, attachments, versions, spaces, shares, and settings.
- [x] Add casts for JSON, booleans, dates, coordinates, and soft-deletion fields.
- [ ] Add model factories for Notes records.
- [x] Add and register the owner-only Note policy.
- [ ] Add policies for notebooks, stacks, tags, attachments, spaces, and shares before their mutation endpoints are introduced.
- [ ] Centralize permission levels in enums or constants instead of accepting arbitrary strings.
- [ ] Guarantee that every query is scoped to the authenticated user or a valid share.
- [x] Create a default notebook and Notes settings when a user first opens Notes.
- [ ] Define ownership-transfer behavior before deleting an owner of shared content.

## Notes navigation and workspace

- [x] Add an icon-only Notes item to authenticated navigation and a labelled menu fallback.
- [x] Add permanent routes for the initial Notes list, create, show, update, and delete flows.
- [ ] Add permanent routes for templates, shared content, and files.
- [x] Add owned routes for notebooks, tags, lightweight tasks, and Trash recovery/permanent deletion.
- [x] Build a dense, full-width desktop navigation/card-list/editor layout while preserving the existing themes and fonts.
- [x] Filter the note list by owned notebook, highlight the active notebook, and preserve the filter while creating or opening notes.
- [x] Make the initial workspace collapse responsively for tablet and mobile widths.
- [x] Contain the Notes workspace inside the viewport and use internal scrolling so the Windows browser window does not overflow.
- [x] Add all-notes and Trash views.
- [ ] Add recent, pinned, archived, templates, shared-with-me, and files views.
- [x] Add the initial compact card display.
- [ ] Add snippet, compact-list, and expanded-list displays.
- [ ] Add sorting by title, creation date, update date, and custom order.
- [ ] Add multi-select and bulk move, tag, archive, restore, and delete actions.
- [ ] Persist sidebar, sorting, density, and list-view preferences.
- [ ] Add keyboard navigation and a command palette.

## Note CRUD and autosave

- [x] Create blank or populated notes in the user's default or selected notebook.
- [x] Implement safe plain-text title and body editing as the initial editor.
- [x] Implement debounced autosave with visible unsaved, saving, saved, and error states; offline and conflict states remain future work.
- [x] Serialize browser autosave requests so an older request cannot overwrite a newer local edit.
- [x] Add restore and permanent-delete actions for trashed notes.
- [ ] Add duplicate, move, merge, and archive actions.
- [ ] Add optimistic UI with recovery when a request fails.
- [x] Generate excerpts and searchable plain text from editor content.
- [x] Update last-viewed timestamps without generating unnecessary versions.
- [x] Add a live word count; character count remains future work.
- [ ] Recover unsaved local drafts after a crash or refresh.

## Rich editor

- [x] Select Tiptap 3 for the vanilla JavaScript/Vite editor and store both its JSON document and generated semantic HTML.
- [ ] Sanitize all stored and rendered HTML.
- [x] Support paragraphs and semantic H1-H3 headings.
- [x] Support bold, italic, underline, strike-through, text color, and multicolor highlighting.
- [x] Support left/center/right alignment, subscript, and superscript.
- [ ] Add indentation and outdentation controls.
- [x] Support bulleted lists, numbered lists, and task checklists.
- [x] Support quotes, dividers, tables, code blocks, inline code, and KaTeX-rendered LaTeX formulas.
- [ ] Support collapsible headings and generated tables of contents.
- [ ] Support drag-and-drop block ordering.
- [ ] Add slash commands and Markdown-style typing shortcuts.
- [x] Add editor undo and redo.
- [ ] Add paste cleanup, plain-text paste, and find/replace.
- [x] Add links to websites.
- [ ] Add links to notes and note headings.
- [ ] Render note links as a URL, title, preview, or embedded card.
- [ ] Keep internal links valid after note titles and notebooks change.
- [ ] Make the editor fully usable with keyboard, touch, and screen readers.

## Log linking

- [ ] Add a note action to link the note to an existing log date and exact time.
- [ ] Allow an optional link to a specific log block from that day.
- [ ] Add a log action to create or attach an existing note at the selected entry time.
- [ ] Display linked log dates/times in the note metadata panel.
- [ ] Display linked notes in the relevant daily-log timeline or side panel.
- [ ] Use the user's configured 12/24-hour time format.
- [ ] Define behavior when a linked log block is deleted while preserving the date/time link.
- [ ] Prevent cross-user and guest-account links.
- [ ] Test date boundaries and timezone behavior.

## Notebooks, stacks, tags, templates, and shortcuts

- [x] Allow users to create named, colored notebooks from the Notes workspace.
- [ ] Complete notebook rename, ordering, pinning, default selection, and soft deletion.
- [x] Allow users to assign an accent color to each note and show it in the note list/editor.
- [ ] Define whether deleting a notebook moves its notes or deletes them.
- [ ] Build stack CRUD and notebook movement between stacks.
- [x] Build initial owned tag create, assign, filter, and safe-delete flows.
- [x] Add multi-tag editing for an individual note.
- [ ] Add hierarchical tags, tag rename/merge, and bulk tagging.
- [ ] Save an existing note as a template.
- [ ] Create notes from templates and manage pinned templates.
- [ ] Add/remove/reorder shortcuts for notes, notebooks, stacks, tags, and saved searches.

## Attachments, recording, scanning, and OCR

- [ ] Reuse the private-storage conventions from Log attachments without exposing files publicly.
- [ ] Build note attachment upload, authorization, download, rename, replace, and delete endpoints.
- [ ] Support drag-and-drop, clipboard paste, and device file selection.
- [ ] Support images, PDFs, documents, audio, video, and generic files.
- [ ] Add inline previews and compact attachment cards.
- [ ] Reuse browser camera, audio, and video capture where appropriate.
- [ ] Add image resize, crop, rotate, and annotation tools.
- [ ] Add multi-page document scanning and PDF assembly.
- [ ] Add background OCR/extraction jobs and processing/error states.
- [ ] Store OCR text in `note_attachments.extracted_text` and include it in search.
- [ ] Add storage limits, file validation, malware defenses, and orphan-file cleanup.
- [ ] Ensure a database rollback also removes newly stored files when appropriate.

## Search and discovery

- [ ] Index note titles, plain text, tags, notebook names, sources, and attachment-extracted text.
- [ ] Implement fast keyword and exact-phrase search.
- [ ] Add filters for notebook, tags, author, dates, source, location, attachment type, archive state, and template state.
- [ ] Add Boolean operators, exclusions, and advanced search syntax.
- [ ] Add match highlighting and in-note result navigation.
- [ ] Add saved-search CRUD and shortcuts.
- [ ] Ensure search returns only content the user can access.
- [ ] Add related-note suggestions.
- [ ] Add semantic search only after regular search permissions and indexing are proven reliable.

## Version history and conflicts

- [x] Debounce snapshots and skip version creation when an autosave contains no actual changes.
- [x] Create an initial version on note creation and a new version after each manual save or changed autosave.
- [ ] Record manual saves, imports, AI edits, restores, and collaborative edits with an appropriate change source.
- [x] Show when a note has previous versions and open its history in a dialog.
- [x] Compare any two versions with an added/removed word diff.
- [x] Restore an old version as a new latest version while preserving every existing version.
- [x] Offer an explicitly confirmed destructive undo that restores a version and removes every newer version.
- [ ] Add version retention and pruning rules.
- [ ] Detect concurrent edits and provide a conflict-resolution UI.

## Capture and import

- [ ] Add a global quick-note action and scratch pad.
- [ ] Allow text or media from a daily log to be copied into a note while retaining a log date/time link.
- [ ] Build a browser clipper or extend the existing Chrome extension with explicit note capture.
- [ ] Support article, simplified article, full-page, bookmark, selection, screenshot, and PDF clips.
- [ ] Preserve clip source URL, title, author, and capture time.
- [ ] Add mobile share-target support if the app becomes a PWA.
- [ ] Add email-to-note only after inbound email security and abuse controls are designed.
- [ ] Import ENEX, Markdown, HTML, plain text, and supported document formats.
- [ ] Preserve source timestamps, tags, notebooks, internal links, and attachments during imports.
- [ ] Add duplicate detection, progress reporting, cancellation, and import error reports.

## Export and backup

- [ ] Export individual notes and whole notebooks.
- [ ] Support Markdown, HTML, PDF, plain text, and an attachment-inclusive archive.
- [ ] Add ENEX-compatible export if round-trip compatibility is required.
- [ ] Preserve metadata, timestamps, tags, links, and attachments.
- [ ] Add printable note styling.
- [ ] Include Notes in complete account-data downloads and account deletion.
- [ ] Document backup and restore procedures.

## Sharing and spaces

- [ ] Implement note and notebook invitations by registered user or email.
- [ ] Implement view, edit, and edit/invite permissions.
- [ ] Store only hashes of invitation and public-link tokens.
- [ ] Add public view-only links; evaluate editable anonymous links separately.
- [ ] Add access revocation, expiration, link rotation, and access requests.
- [ ] Build Shared with Me and sharing-status displays.
- [ ] Build personal/shared spaces and space membership management.
- [ ] Add notes and notebooks to spaces with deterministic ordering.
- [ ] Add activity history before enabling broad collaboration.
- [ ] Add real-time editing, presence, and cursors only after conflict handling is complete.

## AI features

- [x] Reuse OpenRouter configuration, remembered model selection, and API usage accounting for note writing assistance.
- [x] Send the current authorized note as context and append generated text through the rich editor.
- [ ] Summarize, rewrite, proofread, translate, shorten, and expand selected content.
- [ ] Extract decisions and action-item text into lightweight Notes tasks without creating schedules or reminders.
- [x] Generate a note title on demand; tag, notebook, and related-note suggestions remain future work.
- [ ] Ask questions about one selected note.
- [ ] Ask questions across authorized notes with cited source-note links.
- [ ] Reuse transcription for note audio/video and attach transcript text to the note.
- [ ] Add speaker recognition and meeting summaries if supported by the selected model/provider.
- [ ] Require clear consent before sending note or attachment content to an AI provider.
- [ ] Add per-feature AI toggles, error recovery, privacy messaging, and cost visibility.

## Offline and synchronization

- [ ] Define the PWA/local database architecture before implementing offline edits.
- [ ] Cache the app shell and selected notes/notebooks.
- [ ] Queue offline mutations and attachment uploads.
- [ ] Display per-note and per-notebook offline status.
- [ ] Resume interrupted sync and uploads.
- [ ] Apply permission changes and revoked shares promptly to cached content.
- [ ] Provide storage usage and clear-offline-data controls.
- [ ] Test multi-tab, multi-device, offline, and reconnect conflict scenarios.

## Accessibility, security, and performance

- [ ] Meet WCAG keyboard, focus, label, contrast, reduced-motion, and screen-reader requirements.
- [ ] Add authorization and validation tests for every endpoint.
- [ ] Rate-limit public links, sharing invitations, search, uploads, OCR, and AI operations.
- [ ] Prevent cross-user access through IDs, links, attachments, search indexes, versions, and backlinks.
- [ ] Audit sharing and permission changes.
- [ ] Paginate or virtualize large note lists.
- [ ] Load large attachments lazily.
- [ ] Benchmark large notes, thousands of notes, tags, version history, and OCR search.
- [ ] Add queue monitoring and retry behavior for extraction, imports, exports, and AI work.

## Testing and release

- [ ] Add unit tests for parsing, sanitization, permissions, search syntax, and version creation.
- [x] Add feature tests for initial owned note CRUD, default workspace creation, version creation, XSS-safe plain text, and cross-account rejection.
- [x] Add feature tests for notebook/tag organization, lightweight tasks, Trash restoration/permanent deletion, and cross-account rejection.
- [ ] Add feature tests for linking, upload, sharing, import, and export flows as they are implemented.
- [ ] Add browser tests for autosave, editor behavior, drag-and-drop, responsive layouts, and conflicts.
- [ ] Test MySQL/MariaDB migrations as well as the SQLite test database.
- [ ] Add representative seed/demo Notes content.
- [ ] Add Notes to the guest demo only after data isolation is covered by tests.
- [ ] Run `php artisan test`, Pint, the production asset build, and Blade view caching before release.
- [ ] Update the README with Notes features, storage behavior, supported formats, and privacy details.

## Explicitly not part of Notes

- Task due dates, recurrence, priorities, assignments, or scheduling automation.
- Notes-owned reminder records or reminder notifications.
- Notes-owned calendar events or calendar synchronization.
- A second scheduling system.

Notes may preserve action text as a lightweight checklist item linked to a note, but scheduling remains in the existing Log section.

## Changelog

### 2026-08-20 — Dense workspace, tags, tasks, and Trash

- Reworked Notes into a full-width, viewport-contained workspace inspired by the supplied references while retaining the existing Tailwind themes and fonts.
- Tightened spacing and reduced corner radii across the sidebar, note cards, editor chrome, dialogs, tasks, tags, and Trash.
- Added more compact note cards with tag chips plus task and version counts.
- Added owned tag creation, color selection, note assignment, tag filtering, and safe deletion.
- Added lightweight note tasks with completion state and optional note links, without due dates, reminders, recurrence, or calendar data.
- Added a Trash view with owned-note recovery and explicitly confirmed permanent deletion.
- Added feature and schema coverage for the new organization, task, and Trash flows.

### 2026-08-20 — Autosave and contextual AI writing

- Made notebook rows functional filters with active state, owned query scoping, accurate counts, and filter-preserving note links.
- Removed the Notes page header and moved New note into the left navigation.
- Removed manual create/save controls in favor of serialized, debounced autosave with visible state and error feedback.
- Save content-only drafts as Untitled and avoid redundant history versions when nothing changed.
- Added an AI toolbar dialog that sends the current note as context, appends generated writing, or generates a title.
- Added automatic title generation for Untitled notes after an eight-second editing pause, without overwriting manual titles or applying stale responses.
- Fixed automatic title generation waiting for asynchronously loaded model choices and allowed retrying the same Untitled content after a transient save or AI failure.
- Reused OpenRouter model discovery and API accounting, and persist the selected writing model as the account default.

### 2026-08-20 — Rich editing, notebook creation, colors, and version comparison

- Added Tiptap 3 with a dynamically loaded vanilla JavaScript editor and JSON/HTML persistence.
- Added headings, inline styles, colors, highlighting, lists, task checklists, quotes, code, tables, alignment, links, subscript/superscript, undo/redo, and KaTeX formulas.
- Added colored notebook creation from the Notes workspace.
- Added per-note accent colors in the list and editor.
- Contained Notes scrolling inside the viewport to prevent the Windows browser window from overflowing.
- Added previous-version indicators, a version-history dialog, two-version word diffing, and source/timestamp labels.
- Added history-preserving restore-as-latest and confirmed destructive undo-to-version modes.
- Added focused authorization and restore-mode tests.

### 2026-08-19 — Notes foundation and initial workspace

- Added the complete Notes schema and schema regression tests.
- Added Laravel models, casts, and relationships for all Notes entity tables.
- Added an owner-only Note policy and cross-account authorization coverage.
- Added a responsive three-column Notes workspace with notebook navigation, recent-note list, and a safe plain-text editor.
- Added initial note creation, viewing, manual saving, version snapshots, soft deletion, default notebook setup, excerpts, and searchable plain text.
- Added the Notes icon to the primary navigation and Notes to the account menu.
- Removed manual log attachments, media recording, audio transcription, and long-text entry features.
- Added a data-preserving migration that converts long-text attachments to regular text entries before dropping the legacy table.
- Kept authenticated AI-generated images available in log entries.
