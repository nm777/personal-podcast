# Tasks: Podcast Subscribe Buttons

**Input**: Design documents from `/specs/001-subscribe-buttons/`
**Prerequisites**: plan.md (required), spec.md (required), research.md (required)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Frontend**: `src/resources/js/components/`, `src/resources/js/types/`
- **Tests**: `src/tests/Feature/`

---

## Phase 1: Setup

**Purpose**: No project initialization needed — existing Laravel/React project with all dependencies in place.

- [x] T001 Verify existing tests pass on `001-subscribe-buttons` branch by running `php artisan test` in `src/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared helper needed before any subscribe button can be implemented.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T002 Refactor `getFeedUrl()` helper in `src/resources/js/components/feed-list.tsx` into a shared utility function `getFullRssUrl(feed: Feed)` that returns the absolute RSS URL (with token for private feeds), extract to `src/resources/js/lib/subscribe-urls.ts` — all three user stories depend on this

**Checkpoint**: Shared URL utility ready — user story implementation can now begin in parallel

---

## Phase 3: User Story 1 — Subscribe via Apple Podcasts (Priority: P1) 🎯 MVP

**Goal**: iPhone users tap a button that opens Apple Podcasts via `podcast://` URL scheme and subscribes to the feed.

**Independent Test**: Tap the Apple Podcasts button on a feed card → verify it navigates to `podcast://` URL with the correct RSS feed URL. For private feeds, verify token is included.

### Implementation for User Story 1

- [x] T003 [US1] Add `getApplePodcastsUrl(feed: Feed): string` to `src/resources/js/lib/subscribe-urls.ts` that returns `podcast://` + full RSS URL (dropping `https://` prefix), per research.md Decision 1
- [x] T004 [US1] Add Apple Podcasts subscribe button to feed card in `src/resources/js/components/feed-list.tsx` — use `lucide-react` `Podcast` icon (or `Apple` icon), wrap in existing `Tooltip` component from `src/resources/js/components/ui/tooltip.tsx`, link to `getApplePodcastsUrl(feed)`, include `target="_blank"` for desktop fallback

**Checkpoint**: Apple Podcasts button is functional and testable independently

---

## Phase 4: User Story 2 — Subscribe via Google Podcasts / Android (Priority: P2)

**Goal**: Android users tap a button that opens Google Podcasts subscribe intent URL.

**Independent Test**: Tap the Android button on a feed card → verify it navigates to `https://podcasts.google.com/subscribe?url=<encoded_rss_url>`. For private feeds, verify token is included in the encoded URL.

### Implementation for User Story 2

- [x] T005 [P] [US2] Add `getGooglePodcastsUrl(feed: Feed): string` to `src/resources/js/lib/subscribe-urls.ts` that returns `https://podcasts.google.com/subscribe?url=` + `encodeURIComponent(fullRssUrl)`, per research.md Decision 2
- [x] T006 [US2] Add Google Podcasts / Android subscribe button to feed card in `src/resources/js/components/feed-list.tsx` — use `lucide-react` `Smartphone` icon, wrap in Tooltip, link to `getGooglePodcastsUrl(feed)`, include `target="_blank"`

**Checkpoint**: Both Apple and Android subscribe buttons work independently

---

## Phase 5: User Story 3 — Copy RSS URL (Priority: P3)

**Goal**: Users on any platform can tap a copy button to copy the full RSS URL to clipboard with toast confirmation.

**Independent Test**: Click copy button → verify clipboard contains the full RSS URL. For private feeds, verify the real token is copied (not masked). Verify toast confirmation appears.

### Implementation for User Story 3

- [x] T007 [US3] Retain and reposition the existing `handleCopyUrl` function in `src/resources/js/components/feed-list.tsx` — update it to use the shared `getFullRssUrl(feed)` from `src/resources/js/lib/subscribe-urls.ts` instead of the local `getFeedUrl`, keeping the existing clipboard API + fallback logic and toast notification
- [x] T008 [US3] Update the copy button in feed card in `src/resources/js/components/feed-list.tsx` — use `lucide-react` `Copy` icon (already imported), wrap in Tooltip with label "Copy RSS URL", position as last button in the subscribe button group

**Checkpoint**: All three subscribe actions (Apple, Android, Copy) work independently

---

## Phase 6: Polish & Cleanup

**Purpose**: Final cleanup across all user stories — remove deprecated UI, ensure visual consistency, verify tests.

- [x] T009 Remove the plain URL text display (`<a href={getFeedUrl(feed)}>...</a>`) and the reveal-token toggle button (`toggleReveal` / `revealedFeeds` state) from `src/resources/js/components/feed-list.tsx` — per spec FR-006 these are replaced by the subscribe buttons
- [x] T010 Remove unused imports and dead code from `src/resources/js/components/feed-list.tsx` — remove `EyeOff`, `Rss` (if only used in empty state, keep), and the `revealedFeeds` / `toggleReveal` / `getDisplayUrl` functions
- [x] T011 Verify button group layout and visual consistency in `src/resources/js/components/feed-list.tsx` — subscribe buttons should be visually grouped together using a flex container with gap, edit/delete buttons separated visually, all buttons same size (`sm`)
- [x] T012 Run full backend test suite `php artisan test` in `src/` to confirm no regressions from the frontend change
- [x] T013 Build frontend assets with `npm run build` in `src/` to verify no TypeScript or build errors

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — verify clean state
- **Foundational (Phase 2)**: Depends on Phase 1 — BLOCKS all user stories
- **User Stories (Phase 3-5)**: All depend on Phase 2 completion
  - US1 and US2 can proceed in parallel (different URL schemes, same pattern)
  - US3 depends on Phase 2 for shared URL utility but is otherwise independent
- **Polish (Phase 6)**: Depends on all user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Depends on T002 (shared URL helper). No dependencies on other stories.
- **User Story 2 (P2)**: Depends on T002 (shared URL helper). No dependencies on other stories.
- **User Story 3 (P3)**: Depends on T002 (shared URL helper). No dependencies on other stories.

### Within Each User Story

- URL helper function before button implementation
- Button implementation depends on URL helper being available

### Parallel Opportunities

- T005 (Google URL helper) and T003 (Apple URL helper) can run in parallel — different functions, same file but no conflicts
- T004 (Apple button) and T006 (Android button) can run in parallel — same file, but additive changes to different parts of the JSX
- All three user stories (US1, US2, US3) can be worked on in parallel after Phase 2 completes

---

## Parallel Example: User Stories 1 & 2

```bash
# Launch URL helpers for both platforms together:
Task: "Add getApplePodcastsUrl to src/resources/js/lib/subscribe-urls.ts"
Task: "Add getGooglePodcastsUrl to src/resources/js/lib/subscribe-urls.ts"

# Then launch both button implementations together:
Task: "Add Apple Podcasts button to feed card in src/resources/js/components/feed-list.tsx"
Task: "Add Google Podcasts button to feed card in src/resources/js/components/feed-list.tsx"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup — verify tests pass
2. Complete Phase 2: Foundational — shared URL helper
3. Complete Phase 3: User Story 1 — Apple Podcasts button
4. **STOP and VALIDATE**: Tap Apple Podcasts button on a feed card, verify it opens `podcast://` URL
5. Deploy/demo if ready

### Incremental Delivery

1. Setup + Foundational → Shared URL utility ready
2. Add User Story 1 → Apple Podcasts button works → Deploy (MVP!)
3. Add User Story 2 → Android button works → Deploy
4. Add User Story 3 → Copy button repositioned → Deploy
5. Polish → Remove old URL display, cleanup, verify tests

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1 (Apple Podcasts)
   - Developer B: User Story 2 (Google Podcasts)
   - Developer C: User Story 3 (Copy URL)
3. All three complete independently, then Polish phase merges and cleans up

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- This is a frontend-only feature — no backend changes, no migrations, no new API endpoints
- The existing `Feed` type in `src/resources/js/types/index.d.ts` already has `user_guid`, `slug`, `is_public`, and `token` fields needed for URL construction
- Private feed tokens must be included in ALL subscribe URLs (Apple, Google, Copy) per spec FR-005
- The `Tooltip` component already exists at `src/resources/js/components/ui/tooltip.tsx`
- The `useToast` hook is already used in the component for copy confirmation
