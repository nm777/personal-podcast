# Implementation Plan: Podcast Subscribe Buttons

**Branch**: `001-subscribe-buttons` | **Date**: 2026-04-11 | **Spec**: spec.md
**Input**: Feature specification from `/specs/001-subscribe-buttons/spec.md`

## Summary

Replace the plain RSS URL display on feed cards with dedicated subscribe buttons: Apple Podcasts (`podcast://` scheme), Google Podcasts/Android (`podcasts.google.com/subscribe` intent URL), and a clipboard copy button. This is a frontend-only change to the `FeedList` component — no backend API changes or database migrations required.

## Technical Context

**Language/Version**: PHP 8.4 (Laravel 12), TypeScript (React 19)
**Primary Dependencies**: Laravel Framework, Inertia.js v2, Tailwind CSS v4, Pest v3
**Storage**: SQLite (dev), MySQL (prod), file storage for media
**Testing**: Pest PHP (backend), no frontend test runner currently configured
**Target Platform**: Web application with Docker containerization
**Project Type**: Web application (Laravel backend + React/Inertia frontend)
**Performance Goals**: N/A — purely UI change, no new API calls
**Constraints**: No backend changes needed; purely frontend component update
**Scale/Scope**: Update to single component (`feed-list.tsx`)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- [x] **API-First**: N/A — no new API endpoints, consuming existing feed data
- [x] **Media Processing**: N/A — no media processing involved
- [x] **Test-Driven**: Frontend component change; no backend test changes needed. Existing tests must continue to pass.
- [x] **Feed Standards**: N/A — RSS feed generation unchanged
- [x] **Security**: Subscribe URLs for private feeds include token (same as current copy behavior)
- [x] **Performance**: N/A — no new API calls

## Project Structure

### Documentation (this feature)

```text
specs/001-subscribe-buttons/
├── plan.md              # This file
├── research.md          # Phase 0 output
└── spec.md              # Feature specification
```

### Source Code (repository root)

```text
src/resources/js/
├── components/
│   └── feed-list.tsx        # PRIMARY FILE — update subscribe buttons
└── types/
    └── index.d.ts           # Feed type (no changes needed)

tests/
└── Feature/
    └── FeedManagementTest.php  # Existing tests must still pass
```

**Structure Decision**: Single component change in `feed-list.tsx`. Remove the plain URL `<a>` display, remove the reveal-token button, and add three subscribe buttons in a button group.

## Complexity Tracking

No constitution violations. This is a frontend-only change with no new backend endpoints or data models.
