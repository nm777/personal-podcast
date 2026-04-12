# Research: Podcast Subscribe Buttons

## Decision 1: Apple Podcasts Subscribe URL Format

**Decision**: Use `podcast://` URL scheme with the full RSS feed URL as a parameter.

**Format**: `podcast://podcast.example.com/rss/feed-url-here` where the path is the full HTTPS RSS URL.

**Rationale**: The `podcast://` URL scheme is the documented way to open the Apple Podcasts app and subscribe to a feed. The format is `podcast://` followed by the full RSS URL (dropping the `https://` prefix). On iOS, this opens the Podcasts app directly and prompts the user to subscribe.

**Alternatives considered**:
- `itms-podcast://` — older deprecated scheme, still works but `podcast://` is preferred
- `itpc://` — even older, redirects through iTunes, not recommended
- Direct link to Apple Podcasts web (`https://podcasts.apple.com/podcast/...`) — requires the podcast to be listed in Apple's directory, which self-hosted feeds are not

## Decision 2: Android/Google Podcasts Subscribe URL Format

**Decision**: Use `https://podcasts.google.com/subscribe?url=<encoded_rss_url>`.

**Rationale**: Google Podcasts was sunset in 2024 and replaced by YouTube Podcasts. However, the Google Podcasts subscribe URL still works as an intent on Android devices — it opens the user's default podcast app. For newer Android devices, YouTube Music handles podcasts. The subscribe URL pattern is the most universal Android approach since there's no single dominant podcast app on Android.

**Alternatives considered**:
- `intent://` scheme — requires knowing the target app's package name, not universal
- `podcast://` on Android — not universally supported on Android
- YouTube Music link — YouTube Music podcasts must be in their directory; self-hosted RSS feeds aren't listed
- Note: The Android ecosystem is fragmented. The Google Podcasts subscribe URL is the closest thing to a universal "subscribe" mechanism. Users whose default app doesn't handle it can fall back to the copy button.

## Decision 3: Button Layout and UX

**Decision**: Replace the current URL text display and individual action buttons with a labeled button group showing: Apple Podcasts icon, Android icon, and Copy icon.

**Rationale**:
- The current design shows the URL as text, a reveal-token toggle, and a copy button
- Users need platform-specific subscribe buttons that are immediately recognizable
- Icons with tooltips provide a clean, compact UI
- The URL text display is removed to reduce clutter since the subscribe buttons handle the common use cases

**Alternatives considered**:
- Dropdown menu — adds an extra click, less discoverable
- Full URL text + buttons — too cluttered, doesn't solve the core UX problem
- Separate section below the card — takes too much vertical space

## Decision 4: No Backend Changes Needed

**Decision**: All subscribe URLs are constructed on the frontend from existing `Feed` data (`user_guid`, `slug`, `is_public`, `token`).

**Rationale**: The RSS URL is already constructed client-side in `getFeedUrl()`. Subscribe URLs are just different URL schemes wrapping the same RSS URL. No new API endpoints, no migrations, no model changes.

**Alternatives considered**:
- Backend route to generate subscribe URLs — unnecessary complexity
- Store subscribe URLs in the database — redundant, URLs are deterministic
