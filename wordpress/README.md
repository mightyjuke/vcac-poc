# VCAC local-files → WordPress workflow

This package adds **VCAC Landing Page** to the page-template selector. It keeps the active The7 theme and all existing pages. It does not create pages, change the homepage, replace a theme, alter Polylang settings, or run migrations on activation. In Multisite it offers the template only on the network main site.

The landing page's HTML, CSS, JavaScript and translation buttons remain the design source. The video comes from WordPress's Media Library and is not included in the plugin ZIP. WordPress page content is not rendered when this template is selected. This version deliberately preserves the current design and does not convert it to WPBakery/Polylang.

## First installation (network administrator)

1. Install the generated `vcac-landing-page.zip` through Network Admin → Plugins → Add Plugin → Upload Plugin, or copy its `vcac-landing-page` folder into `wp-content/plugins` using an authorized hosting/SFTP account. Do not replace the The7 theme.
2. Activate **VCAC Landing Page** only for the root staging site. Do not network-activate it.
3. Open the separate landing-page draft. Under Page Attributes → Template select **VCAC Landing Page**. In **Landing Page Video**, choose any MP4/video already in the Media Library, save the draft, and preview it while signed in. If no video is selected or autoplay cannot start, the supplied VCAC Knight Street Church exterior photo remains visible. Do not change Settings → Reading or select this page as the homepage.
4. Check desktop/mobile appearance, all three translation buttons, video/poster behavior and the links. Only publish this separate preview page when requested.

The current WordPress account does not have Network Admin installation access. A draft can be created before installation, but the landing page cannot render until the package is installed and the template is selected.

## Subsequent local updates

1. Edit the normal repository files (`index.html`, the CSS files, `app.js`, `languages.js`, `assets/`). Run the normal local preview and review the result.
2. Run `node wordpress/build.mjs <output-directory>`. Zip the generated **vcac-landing-page folder itself**, retaining that top-level directory. The runtime resolves CSS/JS/image URLs to the plugin, the selected video to the Media Library, and ministry links to the current network. It leaves the source landing-page files unchanged.
3. Keep the previous ZIP, then have the administrator replace only this plugin package. Do not paste PHP/JavaScript into the page editor. The selected page uses the updated files automatically; check the page and refresh relevant caches.
4. No source change is uploaded or committed automatically. Keep WordPress backups, credentials and licensed The7 files out of the public repository.

## Isolation and limits

Only a page explicitly assigned this template uses it; other pages retain their normal theme rendering and asset queues. Password-protected pages use the normal WordPress template until access is granted. Draft access uses WordPress's normal permissions. The package does not bypass Password Protected or other access-control plugins.

For fidelity the template omits the The7 header/footer and suppresses the enqueued WordPress/theme/plugin CSS and JavaScript **for that page request only**. It retains `wp_head`, `wp_body_open`, and `wp_footer` for metadata and integrations. Plugins that print inline CSS or JavaScript directly can still affect the page; verify the real staging combination. Analytics/consent widgets that depend on enqueued scripts require explicit integration before any public rollout. No claim of tested The7 compatibility is made until it has been previewed there.

The original client-side translation behavior and title remain in place. This new standalone draft is not linked as a translation of the existing root homepages. A later homepage rollout needs a separate decision about Polylang/SEO, events navigation and the motion-control accessibility issue. Existing homepage translations and Settings → Reading remain untouched.

The plugin does not contain an MP4. WordPress must serve the selected Media Library video correctly; test the reported iPhone iOS 26.2.1 freeze/fullscreen scenario on the physical phone. Current muted/playsinline attributes are not a guarantee of autoplay.

## Rollback

For the new draft, select Default Template to return to the ordinary WordPress content. Restore the previous plugin ZIP to undo a local design update. Deactivating the plugin removes its template choice without deleting pages or media; a selected page then falls back to the theme. Keep the standalone page a draft until installation and review are complete.

## WordPress extension points

- https://developer.wordpress.org/reference/hooks/theme_page_templates/
- https://developer.wordpress.org/reference/hooks/template_include/
