# PodKeep Code Review

**Date:** 2026-04-09

Status legend: `[ ]` pending | `[x]` completed | `[-]` skipped

---

## 1. Correctness & Bugs

### 1.1 [x] ~~CRITICAL — Double-hashed passwords prevent login~~ (FALSE POSITIVE)
- **Files:** `src/app/Http/Controllers/Settings/PasswordController.php:34`, `src/app/Http/Controllers/Auth/RegisteredUserController.php:42`
- **Verdict:** Laravel 12's `hashed` cast calls `Hash::isHashed()` before hashing. When `Hash::make()` is already called, the cast detects the value is already hashed and skips re-hashing. No bug exists.

### 1.2 [x] ~~CRITICAL — YouTube metadata never persisted~~ (FALSE POSITIVE)
- **File:** `src/app/Services/YouTube/YouTubeFileProcessor.php:87-93`
- **Verdict:** Although `updateLibraryItemWithMetadata()` doesn't call `save()`, the subsequent `$libraryItem->update()` call at `YouTubeProcessingService.php:96` calls `save()` internally, which persists ALL dirty attributes including the metadata. Verified with test.

### 1.3 [x] CRITICAL — Regex pattern uses comma instead of pipe
- **File:** `src/app/Http/Requests/LibraryItemRequest.php:35`
- The pattern `/\.(mp3|mp4,m4a,wav,ogg)(\?.*)?$/i` had a comma instead of a pipe between `mp4` and `m4a`. This caused `.mp4`, `.m4a`, `.wav`, and `.ogg` URLs to be rejected by validation. Only `.mp3` URLs worked.
- **Fix:** Changed `mp4,m4a` to `mp4|m4a`. Added tests for all five extensions plus query parameters.

### 1.4 [x] HIGH — YouTube items hard-deleted on failure instead of marked failed
- **File:** `src/app/Services/YouTube/YouTubeProcessingService.php:41,77`
- `$libraryItem->delete()` was called on invalid URL and failed download. Changed to mark as FAILED with error details. Users can now see failed items and retry them.
- **Fix:** Replaced `$libraryItem->delete()` with `$libraryItem->update(['processing_status' => FAILED, ...])` in both locations.

### 1.5 [x] HIGH — Feed slug collision causes unhandled 500
- **File:** `src/app/Http/Controllers/FeedController.php:39`
- `Str::slug()` could produce identical slugs causing unique constraint violation. Added `generateUniqueSlug()` that appends incrementing suffix on collision.
- **Fix:** Added `generateUniqueSlug()` method, used in both `store()` and `update()`.

### 1.6 [x] HIGH — FeedRequest doesn't validate library item ownership (IDOR)
- **File:** `src/app/Http/Requests/FeedRequest.php:30`
- Any user's library items could be added to any feed. Added closure validation rule checking `user_id` matches authenticated user.
- **Fix:** Added ownership check closure to `items.*.library_item_id` validation rule.

### 1.7 [x] HIGH — MediaDownloader has infinite recursion risk
- **File:** `src/app/Services/MediaProcessing/MediaDownloader.php:78`
- `handleHtmlRedirect()` called `downloadFromUrl()` recursively with no depth limit. Added `$maxRedirects` parameter (default 5) that decrements on each redirect.
- **Fix:** Added `$maxRedirects` parameter, throws when limit reached.

### 1.8 [x] HIGH — Temp files not cleaned up on error
- **File:** `src/app/Services/MediaProcessing/MediaProcessingService.php:38-42`
- Temp file created from URL download was never cleaned up if `processFromFile()` threw. Added try/catch around the call with cleanup in the catch.
- **Fix:** Wrapped in try/catch that deletes temp file on failure before re-throwing.

### 1.9 [x] HIGH — DeleteConfirmDialog closes before async operation completes
- **File:** `src/resources/js/components/delete-confirm-dialog.tsx:29-32`
- `handleConfirm` called both `onConfirm()` and `onClose()` immediately. Removed premature `onClose()` call — parent components should manage dialog lifecycle via onSuccess callbacks.

### 1.10 [x] MEDIUM — Media player event listeners never cleaned up (memory leak)
- **File:** `src/resources/js/components/media-player.tsx:49-58`
- Added named handler functions and a cleanup function in the `useEffect` return that calls `removeEventListener` for both `error` and `canplay`.

### 1.11 [x] MEDIUM — Race condition in YouTube title fetching
- **File:** `src/resources/js/components/media-upload-button.tsx:44-60`
- Multiple concurrent YouTube title fetches could arrive out of order. Added `AbortController` ref that aborts the previous request before starting a new one. Ignored `AbortError` in the catch block.

### 1.12 [x] MEDIUM — Handler `$levels` configuration is meaningless
- **File:** `src/app/Exceptions/Handler.php`
- The `$levels` property mapped log level strings to themselves (e.g., `'emergency' => 'emergency'`). Removed the meaningless property.

### 1.13 [x] MEDIUM — Admin reject dialog state management bug
- **File:** `src/resources/js/pages/admin/users/index.tsx`
- Each table row rendered its own `<Dialog>` component competing for state. Refactored to a single controlled Dialog outside the table, controlled by `rejectingUser` state via `open`/`onOpenChange` props.

### 1.14 [x] MEDIUM — Library delete dialog closes on success
- **File:** `src/resources/js/pages/Library/Index.tsx:104-113`
- Added `setDeleteDialogOpen(false)` and `setItemToDelete(null)` to the `onSuccess` callback.

### 1.15 [ ] MEDIUM — Shared Inertia form for delete, update, and retry operations
- **File:** `src/resources/js/pages/Library/Index.tsx:70-81`
- A single `useForm` instance is used for delete, retry, and edit operations. Shared `processing` state could cause confusing UI behavior.
- **Fix:** Use separate `useForm` instances for the edit form and the delete/retry operations.

### 1.16 [x] MEDIUM — Drag-and-drop list uses array index as React key
- **File:** `src/resources/js/pages/feeds/edit.tsx:240`
- Changed `key={index}` to `key={item.library_item_id}`.

### 1.17 [x] LOW — Duplicate `onChange` and `onInput` handlers on form inputs
- **Files:** `src/resources/js/pages/auth/login.tsx`, `src/resources/js/pages/auth/register.tsx`, `src/resources/js/pages/auth/reset-password.tsx`
- Both handlers called `setData` with the same value, causing redundant state updates. Removed all `onInput` handlers — `onChange` is sufficient for React controlled inputs.

### 1.18 [x] LOW — `addLibraryItem` uses sentinel `id: 0` that could conflict
- **File:** `src/resources/js/pages/feeds/edit.tsx:87`
- Changed `id: 0` to `id: Date.now()` for unique temporary IDs.

### 1.19 [x] LOW — `urlCheckTimeout` stored in `useState` causes unnecessary re-renders
- **File:** `src/resources/js/components/media-upload-button.tsx:27`
- Changed from `useState` to `useRef`. Updated all usages to `.current` property.

---

## 2. Code Design & Architecture

### 2.1 [x] HIGH — Inconsistent authorization — no LibraryItemPolicy
- **File:** `src/app/Http/Controllers/LibraryController.php:61-63,90-92,122-124`
- LibraryController used manual `if ($libraryItem->user_id !== Auth::user()->id) { abort(403); }`. Created `LibraryItemPolicy` with `update`, `delete`, and `retry` methods. Registered in `AuthServiceProvider`. Controller now uses `Gate::authorize()` consistently with FeedController.

### 2.2 [x] HIGH — SourceProcessorFactory bypasses DI container
- **File:** `src/app/Services/SourceProcessors/SourceProcessorFactory.php:11-28`
- Replaced `new` instantiation with `app()` resolution for all dependencies. Strategies and processors are now resolved through the DI container, improving testability.

### 2.3 [ ] HIGH — Duplicate detection logic scattered across UrlSourceProcessor and UnifiedDuplicateProcessor
- **Files:** `src/app/Services/SourceProcessors/UrlSourceProcessor.php:22-64`, `src/app/Services/MediaProcessing/UnifiedDuplicateProcessor.php:17-38`
- URL duplicate detection happens in both `UrlSourceProcessor` and `UnifiedDuplicateProcessor`. Two parallel paths for duplicate handling that can diverge.
- **Fix:** Consolidate all duplicate detection into `UnifiedDuplicateProcessor`.

### 2.4 [x] HIGH — Massive dashboard form duplication with CreateFeedForm
- **Files:** `src/resources/js/pages/dashboard.tsx:91-146`, `src/resources/js/components/create-feed-form.tsx`
- Dashboard now uses `CreateFeedForm` component with `renderTrigger` and `showCard` props. Eliminated ~60 lines of duplicate form code. Both components now use `route('feeds.store')`.

### 2.5 [x] HIGH — Duplicate interface definitions across multiple files
- **Files:** `src/resources/js/types/index.d.ts`, `src/resources/js/pages/dashboard.tsx`, `src/resources/js/pages/feeds/edit.tsx`, `src/resources/js/components/feed-list.tsx`, `src/resources/js/pages/Library/Index.tsx`, `src/resources/js/components/media-player.tsx`
- Consolidated `Feed`, `LibraryItem`, `MediaFile`, `FeedItem` interfaces into `types/index.d.ts`. Added `is_admin`, `approval_status` to `User`. Removed `[key: string]: unknown` index signatures. Removed `'use client'` directives from Inertia files.

### 2.6 [ ] MEDIUM — MediaFile model coupled to service layer
- **File:** `src/app/Models/MediaFile.php:65-76`
- `MediaFile::isDuplicate()` calls `DuplicateDetectionService`, coupling the model to a service. The method name implies a boolean return but actually returns `?MediaFile`.
- **Fix:** Move to `DuplicateDetectionService` and rename to `findDuplicateByFile()`.

### 2.7 [ ] MEDIUM — Cross-user media sharing without reference counting
- **File:** `src/app/Services/MediaProcessing/UnifiedDuplicateProcessor.php:153-173`
- When user A duplicates user B's file, user A's library item links to user B's `MediaFile`. If user B deletes their account, user A gets a dangling reference.
- **Fix:** Create separate `MediaFile` records per user pointing to the same physical file, or implement reference counting.

### 2.8 [x] MEDIUM — approval_status uses magic strings instead of enum
- **File:** `src/app/Models/User.php:70,78,86,94-95,108`
- Created `App\Enums\ApprovalStatusType` with `PENDING`, `APPROVED`, `REJECTED` cases. Added enum cast to User model. Updated all model methods to use enum values instead of hardcoded strings.

### 2.9 [ ] MEDIUM — Library/Index.tsx too large (365 lines, 7 responsibilities)
- **File:** `src/resources/js/pages/Library/Index.tsx`
- Manages listing, delete dialog, edit dialog, retry, media playback, flash messages, and auto-refresh polling in one component.
- **Fix:** Extract `LibraryItemCard`, `LibraryEditDialog`, and `useLibraryPolling` hook.

### 2.10 [ ] MEDIUM — media-upload-button.tsx too large (460 lines, 6+ responsibilities)
- **File:** `src/resources/js/components/media-upload-button.tsx`
- Handles file upload, URL import, YouTube import, drag-and-drop, URL duplicate checking, YouTube title fetching, and feed assignment.
- **Fix:** Extract into `useFileUpload`, `useUrlImport`, `useYouTubeImport` hooks and separate presentational sub-components.

### 2.11 [x] MEDIUM — Duplicate utility functions across files
- **Files:** `src/resources/js/pages/feeds/edit.tsx`, `src/resources/js/pages/Library/Index.tsx`, `src/resources/js/components/media-upload-button.tsx`
- Extracted `formatFileSize` and `formatDuration` into `src/resources/js/lib/format.ts`. Removed three inline implementations. All three files now import from the shared module.

### 2.12 [x] MEDIUM — Starter kit navigation links left in production code
- **File:** `src/resources/js/components/app-header.tsx:26-37`
- Emptied `rightNavItems` array. The Laravel React Starter Kit GitHub and docs links no longer render.

### 2.13 [x] LOW — Empty footer navigation rendered unnecessarily
- **File:** `src/resources/js/components/app-sidebar.tsx:84-85`
- `footerNavItems` is `[]` but `NavFooter` still rendered wrapping elements. Added conditional: `{footerNavItems.length > 0 && ...}`.

### 2.14 [x] LOW — FeedController and LibraryController lack return type declarations
- **Files:** `src/app/Http/Controllers/FeedController.php`, `src/app/Http/Controllers/LibraryController.php`
- Added explicit return type declarations to all public and private methods in both controllers.

---

## 3. Performance

### 3.1 [x] HIGH — MediaDownloader streams to disk instead of loading into memory
- **File:** `src/app/Services/MediaProcessing/MediaDownloader.php`
- `downloadFromUrl()` now streams directly to a temp file via Guzzle's `sink` option and returns the storage path. Only reads first 4096 bytes for header/redirect checks. Full file never loaded into PHP memory.

### 3.2 [x] HIGH — MediaValidator only reads file header for signature check
- **File:** `src/app/Services/MediaProcessing/MediaValidator.php`
- `validate()` now reads only the first 4096 bytes via `file_get_contents($path, false, null, 0, 4096)` for signature validation. Uses `filesize()` instead of `strlen($content)`.

### 3.3 [x] HIGH — MediaStorageManager::moveTempFile avoids loading full file
- **File:** `src/app/Services/MediaProcessing/MediaStorageManager.php`
- `moveTempFile()` now uses `hash_file()` and `filesize()` on the existing file, then `Storage::move()` to relocate. No `file_get_contents()` call.

### 3.4 [x] HIGH — All feeds loaded on every Inertia request
- **File:** `src/app/Http/Middleware/HandleInertiaRequests.php:54-56`
- Changed from `fn (): array` to `Inertia::defer(fn(): array)` so feeds are loaded via deferred props instead of blocking every page response.

### 3.5 [x] HIGH — Dashboard loads all items without pagination
- **File:** `src/routes/web.php:28-35`
- Added `->limit(50)` for feeds and `->limit(100)` for library items on the dashboard route.

### 3.6 [x] MEDIUM — FeedController::edit loads ALL user library items
- **File:** `src/app/Http/Controllers/FeedController.php:59`
- Added `->limit(100)` to the library items query on the feed edit page.

### 3.7 [x] MEDIUM — Duplicate file hash calculated twice per upload
- **File:** `src/app/Services/DuplicateDetectionService.php:90-104`
- `analyzeFileUpload()` called both `findUserDuplicate()` and `findGlobalDuplicate()`, each computing the hash independently plus a third call for the result array. Refactored to compute hash once and pass it as `$precomputedHash` to both lookup methods.

### 3.8 [x] MEDIUM — FeedController::syncFeedItems executes N queries in a loop
- **File:** `src/app/Http/Controllers/FeedController.php:114-135`
- Replaced `foreach` + `updateOrCreate()` loop with a single `upsert()` call using the unique `(feed_id, library_item_id)` constraint.

### 3.9 [x] MEDIUM — Polling effect tears down/recreates interval on every reload
- **File:** `src/resources/js/pages/Library/Index.tsx:55-68`
- `useEffect` depended on `libraryItems` array (new reference every reload). Extracted `hasProcessingItems` into `useMemo` so the interval is only recreated when the boolean value actually changes (items start/stop processing).

### 3.10 [x] MEDIUM — use-toast useEffect causes listener churn
- **File:** `src/resources/js/hooks/use-toast.ts:173-181`
- Changed dependency array from `[state]` to `[]`. Listener now registered once on mount.

### 3.11 [x] LOW — CleanupOrphanedMediaFiles loads all orphaned files at once
- **File:** `src/app/Jobs/CleanupOrphanedMediaFiles.php:22`
- Replaced `->get()` with `->chunkById(100, ...)` to process orphaned files in batches, preventing memory exhaustion on large libraries.

### 3.12 [x] LOW — ProcessingStatusHelper instantiated 5-6 times per item render
- **File:** `src/resources/js/pages/Library/Index.tsx:219-235`
- Extracted `const status = ProcessingStatusHelper.from(item.processing_status)` once at the top of the map callback. Replaced 8 inline instantiations with the single `status` variable.

### 3.13 [x] LOW — useIsMobile starts with undefined state (layout flash)
- **File:** `src/resources/js/hooks/use-mobile.tsx:6`
- Added lazy initializer: `useState<boolean>(() => typeof window !== 'undefined' && window.innerWidth < MOBILE_BREAKPOINT)`.

---

## 4. Security

### 4.1 [x] HIGH — CSRF protection disabled for POST endpoint
- **File:** `src/bootstrap/app.php:33-35`
- `check-url-duplicate` POST route was exempted from CSRF verification. Removed the exemption — Inertia/Axios already includes CSRF tokens automatically.

### 4.2 [x] HIGH — Feed token exposed in API resource
- **File:** `src/app/Http/Resources/FeedResource.php:25`
- The feed `token` was always included in the API response. Changed to use `$this->when()` conditional — token now only included when the requesting user owns the feed.

### 4.3 [x] HIGH — Permissive Content-Security-Policy in nginx
- **File:** `src/docker/nginx/default.conf:39`
- Replaced permissive CSP with granular policy. Allows `'unsafe-inline'` for scripts/styles (required by Inertia's inline page data bootstrap) and `https://fonts.bunny.net` for fonts.
- **Fix:** `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self' data: https://fonts.bunny.net; connect-src 'self'`

### 4.4 [x] HIGH — Settings routes lack email verification requirement
- **File:** `src/routes/settings.php:8`
- Settings routes only required `auth`, while all other routes require `auth,verified,approved`. Unverified users could change passwords and delete accounts.
- **Fix:** Changed middleware from `'auth'` to `['auth', 'verified', 'approved']`.

### 4.5 [x] MEDIUM — Audio source URL uses raw file_path from backend
- **File:** `src/app/Http/Resources/MediaFileResource.php`, `src/resources/js/components/media-player.tsx`
- Removed `file_path` from `MediaFileResource` API response to prevent path traversal exposure. Media player now uses only `public_url`. Updated TypeScript `MediaFile` type to remove `file_path`.

### 4.6 [x] MEDIUM — Nginx upload limit misaligned with PHP
- **Files:** `docker/nginx/default.conf`, `src/php.ini`
- Nginx `client_max_body_size` was `100M` while PHP `post_max_size` was `513M`. Aligned nginx to `513M` to match.

### 4.7 [ ] MEDIUM — RSS feed tokens in query parameters
- **File:** `src/app/Http/Controllers/RssController.php:23`
- Tokens appear in server logs, proxy logs, and browser history.
- **Fix:** Consider HTTP Basic Auth or path-based tokens. Document the trade-off for users.

### 4.8 [x] MEDIUM — Feed token could be more cryptographically secure
- **File:** `src/app/Http/Controllers/FeedController.php:43`
- Changed `Str::random(32)` to `Str::random(64)` for stronger private feed access tokens.

### 4.9 [x] MEDIUM — Fragile CSRF token retrieval in manual fetch
- **File:** `src/resources/js/components/media-upload-button.tsx:87-113`
- Replaced raw `fetch()` with `axios.post()`. Axios is already configured by Inertia with CSRF tokens, so manual `document.querySelector` for the meta tag is no longer needed.

### 4.10 [x] MEDIUM — MediaController streams files instead of forcing download
- **File:** `src/app/Http/Controllers/MediaController.php:66`
- Changed from `Storage::download()` to `Storage::response()` with proper Content-Type header. Podcast clients can now stream audio.

### 4.11 [x] LOW — Private feed tokens now hidden behind Reveal button
- **File:** `src/resources/js/components/feed-list.tsx:134-137`
- Private feed tokens are now masked with `••••••••` by default. Added Eye/EyeOff toggle button per feed card. The actual URL (with real token) is only used for the link href and copy action; display shows the masked version until revealed.

---

## 5. Error Handling & Resilience

### 5.1 [x] HIGH — No React Error Boundary
- **File:** `src/resources/js/app.tsx`
- Any rendering error causes a white screen. Created `error-boundary.tsx` with class-based ErrorBoundary wrapping `<App>`. Shows friendly message with reload button, includes error details in dev mode.
- **Fix:** Created `components/error-boundary.tsx` and wrapped `<App>` in `<ErrorBoundary>` in `app.tsx`.

### 5.2 [x] MEDIUM — DuplicateDetectionService swallows exceptions
- **File:** `src/app/Services/DuplicateDetectionService.php:22`
- `calculateFileHash()` caught all exceptions silently. Added `Log::warning()` before falling through so misconfigured storage is visible in logs.

### 5.3 [x] MEDIUM — Form error handlers only log to console
- **Files:** `src/resources/js/components/create-feed-form.tsx`, `src/resources/js/components/feed-list.tsx`
- Server errors were silently ignored — users got no visual feedback. Added toast notifications via `useToast()` on `onError` callbacks.

### 5.4 [x] MEDIUM — RssController has no error handling for malformed XML
- **File:** `src/app/Http/Controllers/RssController.php:30-39`
- Malformed XML from the Blade template would crash in `DOMDocument::loadXML()` and be cached for 15 minutes. Added `libxml_use_internal_errors(true)`, error collection, logging on failure, and graceful fallback to raw XML output.

### 5.5 [x] MEDIUM — ProcessMediaFile uses proper DI injection
- **File:** `src/app/Jobs/ProcessMediaFile.php:30-35`
- Removed nullable `?MediaProcessingService $mediaProcessing = null` and fallback `app()` call. Now uses proper DI: `handle(MediaProcessingService $mediaProcessing)`.

### 5.6 [x] MEDIUM — Media player modal supports overlay click and Escape
- **File:** `src/resources/js/components/media-player.tsx`
- Added overlay click handler (`e.target === e.currentTarget`) and Escape key listener in the `useEffect` cleanup.

### 5.7 [x] LOW — RssController error handling for malformed XML
- **File:** `src/app/Http/Controllers/RssController.php`
- Same issue as 5.4. Resolved together with libxml error handling.

---

## 6. Type Safety & Language Idioms

### 6.1 [x] MEDIUM — Index signatures weaken type safety on User and SharedData
- **Files:** `src/resources/js/types/index.d.ts:33,52`
- Removed `[key: string]: unknown` from both `SharedData` and `User` interfaces. Added explicit `is_admin` and `approval_status` to `User`.

### 6.2 [x] MEDIUM — `is_admin` not in User type
- **File:** `src/resources/js/types/index.d.ts`
- Added `is_admin: boolean` and `approval_status` to `User` interface.

### 6.3 [x] MEDIUM — Inconsistent validation rule syntax across form requests
- **Files:** `src/app/Http/Requests/LibraryItemRequest.php`, `src/app/Http/Requests/UpdateLibraryItemRequest.php`
- Converted all pipe-delimited string rules to array format for consistency with `FeedRequest`.

### 6.4 [x] MEDIUM — `@ts-expect-error` and `as any` in SSR entry
- **File:** `src/resources/js/ssr.tsx`
- Removed 4 `@ts-expect-error` comments and `as any` cast. Used `Parameters<typeof route>[3]` for a single targeted type assertion on the ziggy config object. Removed unused `RouteName` import.

### 6.5 [x] MEDIUM — `usePage().props as PageProps` instead of generic parameter
- **File:** `src/resources/js/pages/admin/users/index.tsx:33`
- Changed from `usePage().props as PageProps` to `usePage<PageProps>().props` for proper compile-time type checking.

### 6.6 [x] LOW — `isDuplicate()` returns `?MediaFile`, not `bool`
- **File:** `src/app/Models/MediaFile.php`
- Added `findDuplicateByFile()` and `findDuplicateByFileForUser()` with descriptive names. Deprecated old `isDuplicate()` and `isDuplicateForUser()` as backward-compatible aliases. Updated callers in `LibraryUploadTest`.

### 6.7 [x] LOW — ProcessingStatusType enum at root namespace
- **File:** `src/app/ProcessingStatusType.php`
- Moved enum to `App\Enums\ProcessingStatusType`. Old location kept as `class_alias()` for backward compatibility. Updated all source files (non-test) to use the new namespace.

### 6.8 [x] LOW — CleanupDuplicateLibraryItem not using constructor promotion
- **File:** `src/app/Jobs/CleanupDuplicateLibraryItem.php:16`
- Replaced manual property declaration and assignment with PHP 8 constructor property promotion.

### 6.9 [x] LOW — Unsafe localStorage cast in useAppearance
- **File:** `src/resources/js/hooks/use-appearance.tsx:37,42,66`
- Added `isValidAppearance()` type guard and `getStoredAppearance()` helper. All three `as Appearance` casts replaced with validated reads. Invalid stored values default to `'system'`.

### 6.10 [x] LOW — `ProcessingStatusHelper.from()` accepts any string
- **File:** `src/resources/js/lib/processing-status.tsx:83-85`
- Added runtime validation: invalid status values now fall back to `PENDING` instead of being cast blindly.

### 6.11 [x] LOW — `'use client'` directives are Next.js conventions, not needed here
- **Files:** `src/resources/js/components/media-player.tsx:1`, `src/resources/js/hooks/use-toast.ts:1`
- Removed `'use client'` from both files.

### 6.12 [x] LOW — FeedController and LibraryController lack return type declarations
- Same as 2.14.

### 7.1 [x] HIGH — YouTube tests disable all middleware
- **File:** `src/tests/Feature/YouTubeTest.php:10,38,60`
- Removed `$this->withoutMiddleware()` from all 3 tests. They now properly exercise auth/verified/approved middleware. All pass with `actingAs()` + verified+approved factory users.

### 7.2 [ ] HIGH — Edge case tests are trivial non-tests
- **File:** `src/tests/Feature/UnifiedSourceProcessorEdgeCasesTest.php:59-74`
- Tests only assert that PHP arrays have keys and strings contain substrings — zero confidence in actual processor behavior.
- **Fix:** Pass data through the processor and verify database results.

### 7.3 [x] MEDIUM — FeedEditTest missing authorization test
- **File:** `src/tests/Feature/FeedEditTest.php:190-213`
- Already covered by existing test `it('prevents adding another users library item to own feed')` which uses a valid library item owned by a different user and asserts it's rejected with validation errors.

### 7.4 [ ] MEDIUM — Reflection-based tests are brittle
- **Files:** `src/tests/Feature/LibraryItemFactoryTest.php:25-78`, `src/tests/Feature/FileUploadProcessorTest.php`
- Tests use `ReflectionMethod` to verify private method signatures and parameter counts. These break on any refactoring regardless of correctness.
- **Fix:** Replace with behavioral tests using public APIs.

### 7.5 [x] MEDIUM — AddLibraryItemToFeedsJobTest only tests dispatch, not execution
- **File:** `src/tests/Feature/AddLibraryItemToFeedsJobTest.php`
- 3 of 4 tests already call `$job->handle()` directly and verify feed_items creation (correct sequences, ownership filtering, empty array handling). Only the first test uses `Queue::fake()` to verify dispatch parameters.

### 7.6 [x] MEDIUM — Missing test coverage for critical paths
- **Files:** `src/tests/Feature/RssFeedAccessTest.php`, `src/tests/Feature/AccessControlTest.php`
- Added 8 new tests covering: RSS 404 for non-existent feed, public feed access, private feed rejection without token, private feed access with token, empty RSS feed, unauthenticated URL duplicate check, pending user dashboard rejection, rejected user dashboard rejection.

### 7.7 [x] LOW — FeedManagementTest hardcodes feed ID as 1
- **File:** `src/tests/Feature/FeedManagementTest.php:141`
- Changed `delete('/feeds/1')` to `delete('/feeds/nonexistent')` to avoid relying on a specific database ID.

### 7.8 [ ] LOW — LibraryUrlTest references specific external website
- **File:** `src/tests/Feature/LibraryUrlTest.php:114-210`
- Three tests reference `file-examples.com` specifically, making them brittle and tied to a particular website.
- **Fix:** Use generic example URLs and describe the behavior pattern in test names.

### 7.9 [x] LOW — Redundant `uses(RefreshDatabase::class)` in multiple test files
- **Files:** `src/tests/Feature/ApiResourceTest.php:13`, `src/tests/Feature/UnifiedSourceProcessorEdgeCasesTest.php:9`, `src/tests/Feature/UnifiedSourceProcessorTest.php:15`
- Removed redundant `uses(RefreshDatabase::class)` from 3 Feature test files. Already applied by `Pest.php` for all Feature tests.

### 7.10 [x] LOW — Unused `something()` helper in Pest.php
- **File:** `src/tests/Pest.php:47-49`
- Removed the empty stub function.

---

## 8. Cross-Module Consistency

### 8.1 [x] HIGH — Inconsistent route usage — hardcoded paths vs named routes
- **Files:** `src/resources/js/pages/dashboard.tsx:55`, `src/resources/js/components/feed-list.tsx:31,154`, `src/resources/js/pages/admin/users/index.tsx:42,48,57`
- Replaced all hardcoded route strings with `route()` helper calls. Also replaced `<a>` with Inertia `<Link>` for the feed edit button in feed-list.tsx.

### 8.2 [x] MEDIUM — feed_items unique constraint + firstOrCreate in job
- **Files:** `src/database/migrations/2026_04_09_202800_add_unique_constraint_to_feed_items.php`, `src/app/Jobs/AddLibraryItemToFeedsJob.php:34`
- Added unique index on `(feed_id, library_item_id)`. Changed `FeedItem::create()` to `FeedItem::firstOrCreate()` to prevent duplicate feed items from duplicate job dispatches.

### 8.3 [x] MEDIUM — Inconsistent auth helper usage
- **Files:** `src/app/Http/Middleware/AdminMiddleware.php`, `src/app/Http/Middleware/ApprovedUserMiddleware.php`, `src/app/Http/Controllers/FeedController.php`, `src/app/Http/Controllers/LibraryController.php`
- Standardized all auth access to `$request->user()`. Removed `Auth` facade imports from both controllers and AdminMiddleware. Controllers now inject `Request` where needed.

### 8.4 [x] MEDIUM — Inconsistent delete confirmation UX
- **Files:** `src/resources/js/components/feed-list.tsx`
- Replaced browser `confirm()` with the existing `DeleteConfirmDialog` component for consistent destructive action confirmation across the app.

### 8.5 [x] MEDIUM — Inconsistent error styling across forms
- **Files:** `src/resources/js/components/media-upload-button.tsx`, `src/resources/js/pages/Library/Index.tsx`, `src/resources/js/pages/feeds/edit.tsx`, `src/resources/js/components/create-feed-form.tsx`
- Replaced 14 inline `<p className="text-sm text-red-600">` / `<p className="text-sm text-destructive">` error patterns with `<InputError>` component for consistency with auth forms.

### 8.6 [x] MEDIUM — Inconsistent flash message display
- **Files:** `src/resources/js/pages/admin/users/index.tsx`
- Replaced custom green/red `<div>` flash messages with the `<Alert>` + `<AlertDescription>` component, matching the pattern used in Library/Index and dashboard pages.

### 8.7 [x] MEDIUM — Inconsistent checkbox component usage
- **Files:** `src/resources/js/components/create-feed-form.tsx`, `src/resources/js/pages/feeds/edit.tsx`
- Replaced plain `<input type="checkbox">` with shadcn `<Checkbox>` component using `onCheckedChange`, matching the pattern used in login.tsx and admin/users.

### 8.8 [x] MEDIUM — `feed-list.tsx` uses `<a>` for internal navigation
- **File:** `src/resources/js/components/feed-list.tsx`
- Already fixed in 8.1 — edit button now uses Inertia `<Link>`. The remaining `<a>` tag is for RSS feed URLs with `target="_blank"` which is correct for cross-origin links.

### 8.9 [x] MEDIUM — UploadStrategy hardcodes cleanup delay
- **File:** `src/app/Services/SourceProcessors/UploadStrategy.php:23`
- Replaced hardcoded "5 minutes" with `config('constants.duplicate.cleanup_delay_minutes')` to stay consistent with other strategies.

### 8.10 [x] MEDIUM — Inconsistent success message patterns between strategies
- **File:** `src/app/Services/SourceProcessors/FileUploadProcessor.php`
- Removed duplicated private `getSuccessMessage()`/`getProcessingMessage()` from `FileUploadProcessor`. Now injects `UploadStrategy` and delegates message generation, ensuring a single source of truth per strategy.

### 8.11 [x] MEDIUM — MediaFileFactory missing user_id, LibraryItemFactory missing processing_status
- **Files:** `src/database/factories/MediaFileFactory.php`, `src/database/factories/LibraryItemFactory.php`
- Added `user_id => User::factory()` to MediaFileFactory. Added `processing_status => COMPLETED` and `is_duplicate => false` to LibraryItemFactory defaults.

### 8.12 [x] LOW — Inconsistent component export styles
- **Files:** Various
- All components already use `export default function ComponentName`. No anonymous arrow functions found. No changes needed.

### 8.13 [x] LOW — Login status message rendered in wrong position
- **File:** `src/resources/js/pages/auth/login.tsx:110-118`
- Moved status message from below the form (after "Sign up") to above the form so users see it immediately on page load.

### 8.14 [x] LOW — Magic number for TOAST_REMOVE_DELAY
- **File:** `src/resources/js/hooks/use-toast.ts:8`
- Changed `TOAST_REMOVE_DELAY` from `1000000` (~16.7 min) to `5000` (5 seconds).

### 8.15 [x] LOW — Login status message rendered in wrong position
- Same as 8.13. Fixed in commit 85e09ff.

### 9.1 [x] HIGH — Docker entrypoint runs as www-data — chown will fail
- **Files:** `Dockerfile`, `docker-entrypoint.sh`
- Added `gosu` to base image. Removed `USER www-data` from Dockerfile. Entrypoint now runs as root, performs `chown`/`chmod`, then drops privileges via `exec gosu www-data "$@"` before running the CMD.

### 9.2 [ ] HIGH — No health checks in any Docker service
- **Files:** `src/docker-compose.prod.yml`, `src/docker-compose.yml`
- No health checks defined for app, web, or worker services. Docker cannot determine if services are actually healthy.
- **Fix:** Add health checks: `php-fpm -t` for app, `wget --spider` for web, `php artisan queue:status` for worker.

### 9.3 [x] HIGH — Auto-migrate on every container start
- **File:** `docker-entrypoint.sh:11`
- Wrapped `php artisan migrate --force` behind `RUN_MIGRATIONS=true` env var check. Dev docker-compose sets `RUN_MIGRATIONS=true` for convenience. Production must opt in explicitly.

### 9.4 [x] HIGH — Permissive CSP in nginx
- **File:** `docker/nginx/default.conf`
- CSP was already tightened in earlier commit (removed wildcard sources, added specific bunny.net fonts, restricted script-src to 'self' 'unsafe-inline' 'unsafe-eval').
- **File:** `src/docker/nginx/default.conf:39`
- Content-Security-Policy allows `http: https: data: blob: 'unsafe-inline'`.
- **Fix:** Restrict to specific known domains.

### 9.5 [ ] MEDIUM — Hardcoded Google DNS in production compose
- **File:** `src/docker-compose.prod.yml:15-17`
- All services use `8.8.8.8`/`8.8.4.4`. Privacy concern and single point of failure.
- **Fix:** Remove explicit DNS or make configurable via env vars.

### 9.6 [ ] MEDIUM — Unpinned base images
- **Files:** `src/Dockerfile:40` (`composer:latest`), `src/Dockerfile:59` (`node:24-alpine`)
- Non-reproducible builds. Different build times can pull different versions.
- **Fix:** Pin to specific versions.

### 9.7 [ ] MEDIUM — Production nginx config bind-mounted from host
- **File:** `src/docker-compose.prod.yml:26`
- The nginx config is bind-mounted from the host, defeating the purpose of baking it into the image.
- **Fix:** Remove the bind mount and rely on the built-in config.

### 9.8 [ ] MEDIUM — PHP-FPM pool too conservative for production
- **File:** `src/custom-www.conf:3-7`
- `pm.max_children = 5` is insufficient for production media upload workloads.
- **Fix:** Increase to 20+ based on available RAM.

### 9.9 [x] MEDIUM — APP_DEBUG=true in example env
- **File:** `src/.env.example:4`
- Changed default to `false`. Production copies won't accidentally expose stack traces.

### 9.10 [ ] MEDIUM — Build packages remain in production image
- **File:** `src/Dockerfile:4`
- `autoconf`, `g++`, `make` are only needed for compiling PHP extensions but remain in the `app` stage.
- **Fix:** Use a separate build stage or `apk del` after compilation.

### 9.11 [ ] MEDIUM — No rate limiting on Traefik
- **File:** `src/docker-compose.prod.yml:35-45`
- Application exposed directly to the internet with TLS but no rate limiting middleware.
- **Fix:** Add rate-limiting middleware to Traefik configuration.

### 9.12 [x] MEDIUM — Worker service missing depends_on
- **File:** `docker-compose.yml:26`
- Added `depends_on: - app` to worker service so it starts after app (and migrations) complete.

### 9.13 [x] LOW — Storage permissions set as executable
- **File:** `Dockerfile:74`
- Replaced `chmod -R 755` with `find -type d -exec chmod 755` + `find -type f -exec chmod 644` for both storage and bootstrap/cache directories.

### 9.14 [ ] LOW — No backup strategy for podcast-storage volume
- **File:** `src/docker-compose.prod.yml:11-12`
- Named volume used for persistent storage with no backup configuration.
- **Fix:** Add volume labels or documentation about backup procedures.

### 9.15 [ ] LOW — Session secure cookie defaults to null
- **File:** `src/config/session.php:172`
- In production behind TLS termination, should default to `true`.
- **Fix:** Change default to `env('SESSION_SECURE_COOKIE', true)`.

### 9.16 [ ] LOW — Hardcoded server_name in nginx
- **File:** `src/docker/nginx/default.conf:3`
- `server_name podkeep.app` is hardcoded. Must be rebuilt if the domain changes.
- **Fix:** Use `envsubst` to inject the domain dynamically.

### 9.17 [x] LOW — Deprecated X-XSS-Protection header
- **File:** `docker/nginx/default.conf:36`
- Removed `X-XSS-Protection: 1; mode=block`. Modern browsers rely on CSP instead; this header is deprecated and can introduce vulnerabilities.

### 9.18 [ ] LOW — `latest` tag makes rollbacks difficult
- **File:** `src/docker-compose.prod.yml:6`
- Image tagged `podkeep-app:latest` is overwritten on every build.
- **Fix:** Use versioned tags or Git SHA-based tags.
