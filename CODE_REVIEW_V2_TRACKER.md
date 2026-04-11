# CODE_REVIEW_V2_TRACKER.md

**Created:** 2026-04-11  
**Source:** CODE_REVIEW_V2.md  
**Total findings:** 82 (7 Critical, 14 High, 38 Medium, 23 Low)  
**Status legend:** `[ ]` pending | `[x]` completed | `[-]` skipped

---

## Phase 1 — Critical Data Integrity

### C1. [x] CRITICAL — `media_file_id` not persisted (FALSE POSITIVE)
- **Files:** `MediaProcessingService.php:90-94`, `UnifiedDuplicateProcessor.php:126/149/174/197/215`, `YouTubeProcessingService.php:104-108`
- **FALSE POSITIVE:** Laravel's `update()` calls `save()` which persists ALL dirty attributes, including ones set via `$model->attr = value` before the `update()` call. Verified via SQL query log: `update "library_items" set "processing_status" = completed, "media_file_id" = ..., "updated_at" = ...`. Test suite confirms all 4 persistence scenarios pass.

### C2. [x] CRITICAL — `onDelete('cascade')` on `media_file_id` with global dedup
- **File:** `database/migrations/2026_04_11_151839_change_media_file_id_fk_to_set_null.php`
- Changed FK constraint from `cascadeOnDelete()` to `nullOnDelete()`. Now when a MediaFile is deleted, linked LibraryItems have `media_file_id` set to NULL instead of being cascade-deleted. This prevents one user's file deletion from removing another user's library items in the dedup scenario.

### C3. [x] CRITICAL — SSRF vulnerability in MediaDownloader
- **File:** `MediaDownloader.php:63-72`
- User-supplied URLs passed to `Http::get()` without private IP validation. Add blocklist for `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `127.0.0.0/8`, `169.254.0.0/16`, `0.0.0.0/8`, `::1`, and `file://` scheme.

### C4. [x] CRITICAL — Exception handler `renderable` intercepts all exceptions
- **File:** `Exceptions/Handler.php:49-51`
- The `renderable` callback fires for every exception, not just API routes. Scope it to `$request->is('api/*')` by checking inside the callback or moving it to an API-specific middleware.

### C5. [x] CRITICAL — FeedController cannot clear feed items with empty array
- **File:** `FeedController.php:120-128`
- `whereNotIn('library_item_id', [])` generates `WHERE 0 = 1`, deleting nothing. Handle empty array case: `if (empty($newItemIds)) { $feed->items()->delete(); }`.

### C6. [x] CRITICAL — Malformed RSS XML served and cached
- **File:** `RssController.php:44-50`
- When `DOMDocument::loadXML()` fails, raw malformed XML is cached. Return a 500 error or empty feed instead of caching bad XML.

### C7. [x] CRITICAL — YouTube duplicate check bypasses non-duplicate-but-linked cases
- **File:** `YouTubeProcessingService.php:62`
- `if ($duplicateResult['is_duplicate'])` only handles true duplicates, but `processUrlDuplicate` can return `is_duplicate: false` with a valid `media_file` (e.g., `handleUserMediaFileOnly`, `handleGlobalUrlDuplicate`). These fall through to re-download.

---

## Phase 2 — Security Hardening

### H1. [ ] HIGH — No index on `media_files.file_path`
- **File:** `database/migrations/2025_07_14_011002_create_media_files_table.php`
- Every `MediaController::show()` request does a full table scan. Add `$table->index('file_path')` via a new migration.

### H2. [ ] HIGH — No rate limiting on media file endpoint
- **File:** `routes/web.php:20`
- The `files.show` route serves files (DB + file I/O) with zero throttling. Add `->middleware('throttle:60,1')`.

### H3. [ ] HIGH — No rate limiting on RSS endpoint
- **File:** `routes/web.php:18`
- `rss.show` has no middleware at all. Add `->middleware('throttle:120,1')` (podcast clients refresh frequently).

### H4. [ ] HIGH — No rate limiting on YouTube video info endpoint
- **File:** `routes/web.php:24`
- Cache misses trigger external YouTube API calls. Add `->middleware('throttle:30,1')`.

### H5. [x] HIGH — `feed_ids` ownership not validated
- **File:** `LibraryItemRequest.php:38-39`
- Validation checks `exists:feeds,id` but not that feeds belong to the user. Add ownership closure like `FeedRequest.php:30-35`.

### H6. [ ] HIGH — Files < 100 bytes pass media validation silently
- **File:** `MediaValidator.php:46`
- Short files that don't match any signature pass instead of failing. Change condition to throw on any unrecognized file regardless of size.

### H7. [ ] HIGH — No timeout on yt-dlp metadata extraction
- **File:** `YouTubeMetadataExtractor.php:28`
- Unlike `YouTubeDownloader` (300s timeout), this process hangs indefinitely. Add `$process->setTimeout(120)`.

### H8. [ ] HIGH — `Carbon::createFromFormat` with no error handling
- **File:** `YouTubeFileProcessor.php:96`
- Unexpected `upload_date` format returns `false`, causing fatal error on `->startOfDay()`. Wrap in try/catch or validate format first.

### H9. [ ] HIGH — Race condition on sequence number in AddLibraryItemToFeedsJob
- **File:** `AddLibraryItemToFeedsJob.php:31-42`
- `max('sequence')` is not atomic. Use `DB::transaction()` with `lockForUpdate()` or atomic increment.

### H10. [ ] HIGH — Race condition on orphaned media file cleanup
- **Files:** `CleanupOrphanedMediaFiles.php:24-34`, `LibraryController.php:73-76`
- Re-check orphan status inside a transaction with lock before deleting. For `LibraryController`, use `DB::transaction()`.

### H11. [ ] HIGH — Middleware applied twice in UserManagementController
- **File:** `UserManagementController.php:14-18`
- Constructor registers `auth` + `admin` middleware, but routes already apply them. Remove constructor middleware.

### H12. [ ] HIGH — Session not invalidated on forced logout
- **File:** `ApprovedUserMiddleware.php:25,33`
- Add `$request->session()->invalidate()` and `$request->session()->regenerateToken()` after `auth()->logout()`.

### H13. [ ] HIGH — RSS descriptions not wrapped in CDATA
- **File:** `resources/views/rss.blade.php:7,22`
- HTML in descriptions breaks XML parsing. Use `<description><![CDATA[{{ $description }}]]></description>`.

### H14. [ ] HIGH — YouTube video ID not validated
- **File:** `YouTubeController.php:19`
- Add `regex:/^[a-zA-Z0-9_-]{11}$/` validation on `$videoId` before API calls.

---

## Phase 3 — Reliability

### M1. [ ] MEDIUM — MediaController no HTTP caching headers
- **File:** `MediaController.php:65`
- Add `Cache-Control: public, max-age=3600`, `ETag`, and `Last-Modified` headers to media responses.

### M2. [ ] MEDIUM — MediaController no caching of access control decisions
- **File:** `MediaController.php:22-48`
- Cache the access check result for 5-60 minutes using `Cache::remember()` keyed by `media_file_id`.

### M3. [ ] MEDIUM — Client `sequence` ignored in FeedController::syncFeedItems
- **File:** `FeedController.php:130-131`
- Use `$item['sequence']` from validated input instead of `$index` from array position.

### M4. [ ] MEDIUM — Null cache duration caches forever
- **File:** `RssController.php:29`
- Add fallback: `Cache::remember(..., config('constants.cache.rss_feed_duration_seconds') ?? 3600, ...)`.

### M5. [ ] MEDIUM — `file_path` not unique in media_files
- **File:** `database/migrations/2025_07_14_011002_create_media_files_table.php`
- Add `$table->unique('file_path')` via new migration, or handle in `MediaStorageManager::moveTempFile()`.

### M6. [ ] MEDIUM — Logout before delete — no transaction
- **File:** `ProfileController.php:52-56`
- Delete user first, then logout. Wrap in `DB::transaction()`.

### M7. [ ] MEDIUM — No pagination on library/feed/user index
- **Files:** `LibraryController.php:22-25`, `FeedController.php:24`, `UserManagementController.php:25`
- Replace `->get()` with `->paginate(50)` or `->simplePaginate(50)`.

### M8. [ ] MEDIUM — `formatDuration(0)` returns `'Unknown'`
- **File:** `resources/js/lib/format.ts:10`
- Change `if (!seconds)` to `if (seconds == null)` to handle `0` correctly.

### M9. [ ] MEDIUM — `formatFileSize` crashes on negative/oversize input
- **File:** `resources/js/lib/format.ts:1-7`
- Add guard: `if (bytes < 0) return '0 Bytes'`. Add `'TB'` to sizes array.

### M10. [ ] MEDIUM — Duplicate Feed interface in dashboard.tsx
- **File:** `resources/js/pages/dashboard.tsx:9-20`
- Import `Feed` from `@/types` instead of defining locally.

### M11. [ ] MEDIUM — Dashboard over-fetches unused data
- **File:** `routes/web.php:33-37`
- Remove `libraryItems` query from dashboard route since the frontend doesn't use it.

### M12. [ ] MEDIUM — Hardcoded route URL in feeds/edit.tsx
- **File:** `resources/js/pages/feeds/edit.tsx:47`
- Replace `` put(`/feeds/${feed.id}`) `` with `put(route('feeds.update', feed.id))`.

### M13. [ ] MEDIUM — `onClick` on Radix Checkbox in login.tsx
- **File:** `resources/js/pages/auth/login.tsx:100`
- Replace `onClick` with `onCheckedChange={(checked) => setData('remember', checked === true)}`.

### M14. [ ] MEDIUM — Variable shadowing in media-upload-button.tsx
- **File:** `resources/js/components/media-upload-button.tsx:63`
- Rename `const data = await response.json()` to `const responseData` or `const videoData`.

### M15. [ ] MEDIUM — No cleanup of abort controller/timeout on unmount
- **File:** `resources/js/components/media-upload-button.tsx:30-31`
- Add `useEffect` cleanup to abort `youTubeAbortController` and clear `urlCheckTimeout`.

### M16. [ ] MEDIUM — Audio doesn't reload when libraryItem changes
- **File:** `resources/js/components/media-player.tsx:40`
- Add `libraryItem.id` to dependency array and call `audioRef.current?.load()` in the effect.

### M17. [ ] MEDIUM — No focus trap/ARIA in media player modal
- **File:** `resources/js/components/media-player.tsx:51`
- Add `role="dialog"`, `aria-modal="true"`, focus trap, and scroll lock. Or refactor to use shadcn `Dialog`.

### M18. [ ] MEDIUM — Shared processing state in admin users page
- **File:** `resources/js/pages/admin/users/index.tsx:38,136,149`
- Use per-row forms or disable only the clicked row's button using a `processingUserId` state.

### M19. [ ] MEDIUM — No error handling callbacks in Library Index
- **File:** `resources/js/pages/Library/Index.tsx:72-82,84-90,100-111`
- Add `onError` callbacks to `router.delete()`, `router.post()`, and `editForm.put()` with toast notifications.

### M20. [ ] MEDIUM — No loading state on delete confirmation
- **File:** `resources/js/components/delete-confirm-dialog.tsx:29-31`
- Add `loading` and `disabled` props to the confirm button.

### M21. [ ] MEDIUM — No client-side file size validation
- **File:** `resources/js/components/media-upload-button.tsx:79-87`
- Check `file.size > 513 * 1024 * 1024` in `handleFileSelect` and show error toast.

### M22. [ ] MEDIUM — `processing_status` typed as `string` instead of union
- **File:** `resources/js/types/index.d.ts:47`
- Change to `processing_status: 'pending' | 'processing' | 'completed' | 'failed'`.

### M23. [ ] MEDIUM — Duplicated file signature tables
- **Files:** `MediaDownloader.php`, `MediaValidator.php`
- Extract to a shared constant class or trait. Both should reference the same source of truth.

### M24. [ ] MEDIUM — SSRF via URL regex in LibraryItemRequest
- **File:** `LibraryItemRequest.php:35`
- Add `'active_url'` rule or explicit scheme check to block `file://`, `ftp://`, etc.

### M25. [ ] MEDIUM — N+1 in FeedRequest validation closure
- **File:** `FeedRequest.php:30-35`
- Eager-load all items in one query, then filter in-memory. Add `'max:100'` to items array.

### M26. [ ] MEDIUM — Hardcoded `'pending'` string in RegisteredUserController
- **File:** `RegisteredUserController.php:43`
- Use `ApprovalStatusType::PENDING->value`.

### M27. [ ] MEDIUM — `!null` evaluates to `true` in toggleAdmin
- **File:** `UserManagementController.php:79`
- Use `$user->is_admin ? false : true` instead of `! $user->is_admin`.

### M28. [ ] MEDIUM — Cross-user media file linking without ownership transfer
- **File:** `UnifiedDuplicateProcessor.php:174`
- When linking a global duplicate, either create a new `MediaFile` for the user or implement reference counting.

### M29. [ ] MEDIUM — No composite index on `(user_guid, slug)`
- **File:** `database/migrations/` — new migration needed
- Add `$table->index(['user_guid', 'slug'])` for the RSS feed lookup query.

### M30. [ ] MEDIUM — `feed_ids` ownership not validated in LibraryController
- **File:** `LibraryController.php:50`
- Filter `$validated['feed_ids']` to only include feeds owned by the authenticated user.

### M31. [ ] MEDIUM — Full user model serialized to frontend
- **File:** `HandleInertiaRequests.php:48`
- Use `$request->user()?->only(['id', 'name', 'email', 'is_admin', 'approval_status'])`.

### M32. [ ] MEDIUM — `processing_error` exposed to frontend
- **File:** `LibraryItemResource.php:29`
- Sanitize or truncate error messages. Strip file paths and internal details.

### M33. [ ] MEDIUM — `file_hash` exposed in API response
- **File:** `MediaFileResource.php:20`
- Remove `file_hash` from the resource unless needed by the frontend.

### M34. [ ] MEDIUM — Feed token mass-assignable
- **File:** `Feed.php:20`
- Remove `token` from `$fillable`. Set it explicitly in `Feed::create()`.

### M35. [ ] MEDIUM — No DB unique constraint on `(feed_id, library_item_id)`
- **File:** `database/migrations/` — new migration needed
- Add `$table->unique(['feed_id', 'library_item_id'])` on `feed_items` table.

### M36. [ ] MEDIUM — Hash computed twice in YouTubeFileProcessor
- **File:** `YouTubeFileProcessor.php:24,37`
- Pass the precomputed hash to `processFileDuplicate` instead of recomputing.

### M37. [ ] MEDIUM — `exists()` used on directory in YouTubeDownloader
- **File:** `YouTubeDownloader.php:127`
- Use `Storage::disk('public')->directoryExists()` instead.

### M38. [ ] MEDIUM — Hardcoded `'approved'` string in MakeUserAdmin
- **File:** `MakeUserAdmin.php:47`
- Use `ApprovalStatusType::APPROVED->value`.

---

## Phase 4 — Low Priority Polish

### L1. [ ] LOW — No route model binding on library routes
- **File:** `LibraryController.php:59,85,114`
- Use `LibraryItem $libraryItem` type hints instead of untyped `$id`.

### L2. [ ] LOW — Slug generation: one query per collision
- **File:** `FeedController.php:141-161`
- Fetch all similar slugs in one query: `Feed::where('slug', 'LIKE', "{$baseSlug}%")->pluck('slug')`.

### L3. [ ] LOW — `request()->user()` global helper in generateUniqueSlug
- **File:** `FeedController.php:147`
- Inject `Request $request` parameter instead.

### L4. [ ] LOW — `index()` dual behavior (JSON + redirect) — SRP violation
- **File:** `FeedController.php:22`
- Split into separate `index()` (web) and `apiIndex()` (JSON) methods.

### L5. [ ] LOW — No success message after password change
- **File:** `PasswordController.php:37`
- Add `return back()->with('status', 'Password updated.')`.

### L6. [ ] LOW — No success message after profile update
- **File:** `ProfileController.php:40`
- Add `->with('status', 'Profile updated.')`.

### L7. [ ] LOW — Missing return type on UrlDuplicateCheckController
- **File:** `UrlDuplicateCheckController.php:14`
- Add `: JsonResponse` return type.

### L8. [ ] LOW — `Auth::user()->id` instead of `Auth::id()`
- **File:** `MediaController.php:52`
- Use `Auth::id()` for clarity.

### L9. [ ] LOW — Unused `useCallback` import
- **File:** `Library/Index.tsx:18`
- Remove unused import.

### L10. [ ] LOW — `adminNavItems` not memoized
- **File:** `app-sidebar.tsx:39-47`
- Wrap in `useMemo(() => [...], [user?.is_admin])`.

### L11. [ ] LOW — Deprecated `document.execCommand('copy')` fallback
- **File:** `feed-list.tsx:50-76`
- Remove fallback; use `navigator.clipboard.writeText()` only (well-supported in modern browsers).

### L12. [ ] LOW — `formContent` recreated every render
- **File:** `create-feed-form.tsx:70-115`
- Move inside `if (isExpanded)` block.

### L13. [ ] LOW — Module-level mutable state in use-toast.ts survives HMR
- **File:** `use-toast.ts:57,128,130`
- Accept as known limitation of the shadcn toast implementation.

### L14. [ ] LOW — SSR/hydration mismatch in use-appearance.tsx
- **File:** `use-appearance.tsx:57`
- Accept as known limitation; theme is set via cookie on server and updated client-side.

### L15. [ ] LOW — `new URL()` crash in ssr.tsx
- **File:** `ssr.tsx:19`
- Add try/catch around `new URL(page.props.ziggy.location)`.

### L16. [ ] LOW — Inspiring quotes `explode('-')` edge case
- **File:** `HandleInertiaRequests.php:41`
- Use `str()->beforeLast('-')` and `str()->afterLast('-')` with null safety.

### L17. [ ] LOW — Validation message key mismatch
- **File:** `LibraryItemRequest.php:49-51`
- Change `file.required_without` to `file.required_without_all` (and same for url/source_url).

### L18. [ ] LOW — No max URL length in UrlDuplicateCheckRequest
- **File:** `UrlDuplicateCheckRequest.php:26`
- Add `'max:2048'` to URL validation rule.

### L19. [ ] LOW — No confirmation prompt in MakeUserAdmin command
- **File:** `MakeUserAdmin.php:45-48`
- Add `$this->confirm("Grant admin to {$user->email}?")`.

### L20. [ ] LOW — Inline validation instead of Form Request (4 files)
- **Files:** `PasswordController.php`, `ProfileController.php:48-49`, `RegisteredUserController.php:33-37`
- Extract to Form Request classes for consistency.

### L21. [ ] LOW — Checkbox doesn't handle `'indeterminate'` in create-feed-form
- **File:** `create-feed-form.tsx:101`
- Type `onCheckedChange` parameter as `boolean | 'indeterminate'`.

### L22. [ ] LOW — Redundant `isOpen={true}` in media player
- **File:** `Library/Index.tsx:283`
- Remove `isOpen={true}` (component is only rendered when `playingItem` is truthy).

### L23. [ ] LOW — `global.route` untyped in ssr.tsx
- **File:** `ssr.tsx:16-20`
- Add proper type declaration for the global route function.

---

## Progress

| Phase | Total | Completed | Remaining |
|-------|-------|-----------|-----------|
| Phase 1 — Critical | 7 | 7 | 0 |
| Phase 2 — High | 14 | 0 | 14 |
| Phase 3 — Medium | 38 | 0 | 38 |
| Phase 4 — Low | 23 | 0 | 23 |
| **Total** | **82** | **7** | **75** |
