# VCAC Landing Page 0.4.0

One plugin provides the landing-page template, community directory, and ministry publishing controls. The active theme remains The7. Activation does not create or remove pages, change the homepage, import content, or alter Polylang.

## Upgrade for a staging preview

1. Keep the previous ZIP and a normal WordPress backup. In Network Admin → Plugins, upload vcac-landing-page-0.4.0.zip and replace the existing VCAC Landing Page plugin.
2. **Network Activate VCAC Landing Page.** Version 0.4.0 needs its publishing controls on the three ministry sites. The landing template remains restricted to the network's main site; ministry pages retain their normal theme.
3. On the main site, open **Settings → VCAC Content Feed**. Confirm English → English site, Traditional Chinese → Cantonese, Simplified Chinese → Mandarin. Defaults detect site paths, not assumed numeric IDs. Save only if the mapping needs changing.
4. Preview the existing separate page assigned the **VCAC Landing Page** template. Its selected Media Library video remains selected; the church-building image remains the default fallback. No MP4 is in the ZIP.
5. Publish a test listing on a ministry staging site as below, then refresh the landing preview. Test each language and the directory. A homepage rollout needs a separate team decision.

The existing Community Services & Events page is not replaced. “View all opportunities” opens this landing template in directory mode (?vcac_view=community), using the same feed. Changing existing community-page navigation remains a separate step.

## Staff: publish once in your ministry

Use the usual ministry editor. A **VCAC Landing Page Listing** box appears on Posts and Modern Events Calendar events.

| Content | Where to create it | Show on landing page |
|---|---|---|
| Announcement or news | Ordinary Post | Ministry update |
| Ongoing programme | Ordinary Post | Community programme or event |
| Dated or recurring event | Existing MEC event editor | Community programme or event |

- Write the title and content as usual. Existing excerpt and featured image are reused. No second poster or special graphic is required; cards also work without an image.
- An optional listing summary overrides the excerpt; otherwise a plain-text excerpt from the content is used. An optional action URL links directly to registration; otherwise the card links to the original post/event.
- For an ongoing programme, enter its Schedule and a future **Review by** date. It disappears after that date until reviewed and extended. Incomplete ongoing listings remain hidden. Review by is optional for updates and calendar events.
- Choose **This language ministry** by default. For a community activity open across the church, choose **All language ministries**. Updates always stay in their ministry's own feed.
- Give translated versions the same stable **Programme ID**, such as baby-circle. The selected language's version wins without duplication. Otherwise a shared original can appear with its source language identified. The plugin does not translate post content.
- Publish. The landing page reads the source on its next request; staff do not re-enter anything there. Edit or unpublish the source to change or remove it.

Only published, unprotected, explicitly opted-in items from active public source sites are eligible. Drafts, private/password-protected posts, cancelled/expired events and expired programmes are excluded. Existing posts do not suddenly appear on activation.

## Languages and navigation

First visit: browser language chooses English, Traditional Chinese or Simplified Chinese. A manual selection is remembered. An explicit vcac_lang=en, zh-Hant or zh-Hans URL takes priority. Changing language updates the community feed, latest updates and ministry destination together. Permanent ministry shortcuts stay Cantonese → English → Mandarin.

Display language supplies a default ministry choice; it is not an automatic translation service. Shared records preserve their actual language. No-JavaScript visitors retain ministry links; dynamic cards and language switching require JavaScript.

## Calendar integration and limits

The adapter reads Modern Events Calendar's generated occurrence data instead of guessing recurrence dates from the original post date. It uses current/next occurrences, cancellation and sold-out status when available. Times display in Vancouver time. Missing/unsupported calendar APIs fail closed: calendar items are omitted; ordinary Post listings continue to work.

The staging MEC Lite 7.35.1 API was inspected read-only. See release test notes for executed tests and remaining environment checks. The ZIP does not include MEC, The7, their licensed files, a video, or example content.

This version reads up to the 100 most recently published opted-in community items and 100 updates per ministry, then filters and orders them. Retire old listings if approaching that limit. It does not backfill announcements, generate translations, create duplicate posts or scrape pages.

Feeds are assembled server-side per request with no plugin feed cache. The selected landing page sends no-cache headers and sets DONOTCACHEPAGE. Hosting/CDN caches that ignore these must explicitly exclude the landing page and directory, or source changes can remain stale. Already-open browser pages update when refreshed.

## Local development and packaging

Edit the repository HTML/CSS/JS and PHP adapter files. http://127.0.0.1:4178/?demo=1 shows clearly labelled example content for design review; its example JSON is not shipped. This demo is not connected to staging data.

Run node wordpress/build.mjs <output-directory> and zip the generated vcac-landing-page folder itself. A hash manifest versions assets. Uploads, Git commits and deployments are manual.

The reproducible blueprint in wordpress/tests uses disposable local WordPress Multisite sites and fixture posts. Never run the fixture test file inside a real VCAC site.

## Isolation and rollout checks

Only pages explicitly assigned this template use the layout. It retains WordPress metadata hooks while suppressing theme/enqueued styles and scripts for its own request. Plugins printing inline code can still affect it. Analytics/consent integrations relying on suppressed scripts need separate integration.

Before rollout, preview on the real The7/plugin combination, verify a real recurring MEC event and source edit, check cache behavior, and test physical iPhone Safari video fallback. Responsive browser checks cannot establish physical-iPhone autoplay behavior. This release retains the existing video-controls policy and does not claim full motion-accessibility compliance.

No homepage, old community page, ministry layout, translation relationship, page content or media is deleted. Restore the previous ZIP to roll back. Selecting Default Template returns the landing page to ordinary WordPress content; deactivation removes the template choice without deleting content or media.
