# Feature Specification: Podcast Subscribe Buttons

**Feature Branch**: `001-subscribe-buttons`  
**Created**: 2026-04-11  
**Status**: Draft  
**Input**: User description: "On the feeds list, instead of showing a url, I'd like buttons that automatically add the podcast to the iphone podcast app, whatever the default is for android podcasts, and a generic url copy button for people who still want to copy and paste the url somewhere manually."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Subscribe via Apple Podcasts (Priority: P1)

As an iPhone user, I want to tap a button on a feed card that opens Apple Podcasts (or the Podcasts app) and subscribes to my feed, so I don't have to manually copy/paste the RSS URL.

**Why this priority**: Largest single-platform podcast audience. The `podcast://` URL scheme is well-documented and reliable.

**Independent Test**: Can be fully tested by tapping the Apple Podcasts button and verifying it opens the correct `podcast://` URL with the feed's RSS URL.

**Acceptance Scenarios**:

1. **Given** a feed card is displayed, **When** user taps the Apple Podcasts button, **Then** the browser navigates to `podcast://` URL with the full RSS feed URL, triggering the Podcasts app
2. **Given** a private feed, **When** user taps the Apple Podcasts button, **Then** the URL includes the feed token so the subscription works

---

### User Story 2 - Subscribe via Google Podcasts / Android (Priority: P2)

As an Android user, I want to tap a button that subscribes to the podcast in my default podcast app (Google Podcasts or successor), so I can start listening immediately.

**Why this priority**: Second largest platform. Android uses a standard `https://` intent-based approach via Google Podcasts publish URL.

**Independent Test**: Can be fully tested by tapping the Android button and verifying it opens the correct Google Podcasts subscription URL.

**Acceptance Scenarios**:

1. **Given** a feed card is displayed, **When** user taps the Android/Google Podcasts button, **Then** the browser navigates to `https://podcasts.google.com/subscribe?url=<encoded_rss_url>`, triggering Google Podcasts
2. **Given** a private feed, **When** user taps the Google Podcasts button, **Then** the subscription URL includes the feed token

---

### User Story 3 - Copy RSS URL (Priority: P3)

As a user on any platform, I want a copy button that copies the full RSS feed URL to my clipboard, so I can manually paste it into any podcast app or share it.

**Why this priority**: This functionality already exists in the current feed-list component and serves as a fallback for any podcast app. It needs to be retained but repositioned as part of the new button group.

**Independent Test**: Can be fully tested by clicking the copy button and verifying the correct URL is in the clipboard.

**Acceptance Scenarios**:

1. **Given** a feed card is displayed, **When** user clicks the copy button, **Then** the full RSS feed URL is copied to the clipboard and a toast confirmation appears
2. **Given** a private feed with hidden token, **When** user clicks the copy button, **Then** the actual URL with the real token is copied (not the masked version)

---

### Edge Cases

- What happens when a user is on a device that doesn't support `podcast://` URL scheme? The link will fail gracefully; the copy button serves as fallback.
- What happens with private feeds? The RSS URL must include the token for subscription to work.
- What happens on desktop browsers where podcast apps aren't installed? Links will fail to open; copy button is the primary desktop action.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Feed cards MUST replace the plain URL display with a set of subscribe action buttons
- **FR-002**: An Apple Podcasts subscribe button MUST open the `podcast://` URL scheme with the feed's RSS URL
- **FR-003**: An Android/Google Podcasts subscribe button MUST open the Google Podcasts subscribe URL
- **FR-004**: A copy URL button MUST copy the full RSS feed URL to clipboard with toast confirmation
- **FR-005**: Private feeds MUST include the authentication token in the subscribe URLs
- **FR-006**: The existing URL display (plain text link) MUST be removed from feed cards
- **FR-007**: Buttons MUST be visually grouped and clearly labeled with icons and/or text

### Key Entities

- **Feed**: Existing entity - no schema changes needed. The `user_guid`, `slug`, `is_public`, and `token` fields are used to construct subscribe URLs.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can subscribe to a feed in Apple Podcasts with a single tap
- **SC-002**: Users can subscribe to a feed in Google Podcasts with a single tap
- **SC-003**: Users can copy the RSS URL with a single tap
- **SC-004**: All existing feed list tests continue to pass
