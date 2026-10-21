=== PressFleet Connector ===
Contributors: nvekay
Tags: rest-api, publishing, bulk, wp-cli, lead form
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lets the PressFleet hub publish to this site over a signed REST API.

== Description ==

PressFleet publishes the same content to a network of WordPress sites from one hub. This plugin is the part that runs on each site.

* `POST /wp-json/pressfleet/v1/posts` creates or updates a post by the hub's external ID, so a retried request never creates a duplicate. Categories and tags are created when missing, the featured image is downloaded once (matched by URL and by file hash), and SEO title and description go to Yoast SEO, Rank Math or the plugin's own output.
* Every request needs an Application Password of a user who can publish and an HMAC signature made with a shared secret.
* Settings → PressFleet shows the endpoint, the secret and an activity log.
* WP-CLI: `wp pressfleet status`, `wp pressfleet log`, `wp pressfleet secret`.
* A "Case study" post type for the demo theme's portfolio sections.

== Changelog ==

= 0.1.0 =
* First version: signed REST publishing, media de-duplication, SEO fields, activity log, WP-CLI commands.
