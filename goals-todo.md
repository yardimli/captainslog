# Goals implementation plan

## Product model

A goal is a dated target owned by one user. It has a name, emoji, color, target point count, optional start and end dates, and one period mode:

- **Daily / weekly / monthly** goals reset at the beginning of each matching calendar period. Weekly periods respect the user's configured first day of the week.
- **One time** goals use one continuous period. They may have a start, an end, both, or neither. A one-time goal is complete when its target is reached and is hidden after the completion day. Its historical state remains visible when browsing an earlier date.

An omitted start means the goal is open in the past. An omitted end means it is open in the future. An end date limits all modes. Period snapshots are calculated for the calendar's focused date, so browsing an old day/week/month shows the points that existed in that period, not today's total.

## Progress sources

Each goal can enable any combination of the three supported source types:

1. **Existing event** — every recorded `TaskEvent` for the selected event definition is worth +1.
2. **GitHub projects** — every unique commit in a synced GitHub log block whose repository/project matches one of the configured projects is worth +1. Existing synced project names are offered in setup; names can also be entered manually.
3. **Manual input** — the user records a note and chooses its positive point value from the goal detail page.

Goal entries are immutable dated facts. Automatic entries keep a stable source key (`task-event:{id}` or `github:{sha}`), preventing double counting. Creating or editing a goal runs a backward synchronization over matching existing events and GitHub blocks, including an open past when no start date is set. Normal calendar/day activity also synchronizes automatic sources so new records are reflected.

## Pages and behavior

- **Goal setup (`/goals`)** mirrors Event setup: goals are listed on the left and a right-side panel creates or edits the selected goal.
- **Calendar** shows goal bubbles above the calendar grid for the focused date. Each bubble links to goal details and shows name, period progress, and relative time since the latest matching activity.
- **Goal detail (`/goals/{goal}`)** shows the selected period, progress, dates/source configuration, recent activity, historical period summaries, and the manual-entry form when enabled.
- Deleting a goal deletes only its goal configuration and derived/manual progress entries. Original event and GitHub log data is never changed.

## Persistence

- `goals`: presentation, target, date bounds, period mode, manual-source flag, and one-time completion timestamp.
- `goal_sources`: links a goal to event definitions or GitHub project/repository names.
- `goal_entries`: dated points with optional note and stable external key.

## Implementation checklist

- [x] Add goal/source/entry schema and model relationships.
- [x] Add period calculation, historical snapshots, automatic backfill, and completion behavior.
- [x] Add goal setup CRUD and right-panel editor.
- [x] Add manual progress recording and goal detail/history page.
- [x] Add calendar goal bubbles and navigation entry.
- [x] Synchronize goal progress after an event is recorded and after GitHub activity is loaded.
- [x] Add feature coverage for ownership, all source types, reset/history behavior, open bounds, completion, and UI links.
- [ ] Later: query GitHub directly for repository choices before any commits have been synced.
- [ ] Later: allow multiple event definitions per goal (the first release supports one event definition plus multiple GitHub projects).
