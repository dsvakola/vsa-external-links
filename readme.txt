=== WebOrbit Plugin ===
Contributors: dsvakola, weborbit-team
Donate link: https://vsa.edu.in/donate
Tags: security, whitelist, access-control, roles
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.11
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
WebOrbit provides powerful access-control features for WordPress sites with an emphasis on simplicity,
performance and per-role rules. Version 1.11 introduces whitelist wildcard support and per-role
control panels for more granular access rules.

Key features:
* Fast request filtering with minimal overhead.
* Whitelist support including wildcard patterns (e.g. 192.168.* or *.trusted.domain).
* Per-role access controls — define different rules for Administrators, Editors, Subscribers, etc.
* Compatibility with common caching & security plugins.
* Clear logging and safe-fail behavior to avoid blocking admin access.

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress plugin installer.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings → WebOrbit to configure whitelist and per-role controls.
4. If upgrading from an earlier version, follow the upgrade notice below.

== Frequently Asked Questions ==
= Will wildcard rules affect performance? =
Wildcard evaluation is optimized and cached; typical impact is negligible for normal rule-sets.

= I locked myself out after changing rules — what do I do? =
WebOrbit includes a safety bypass token for Administrators. If you cannot access admin, recover via the
database option `weborbit_bypass_token` or disable the plugin via WP-CLI or file rename.

== Screenshots ==
1. Settings → WebOrbit main screen (whitelist + role controls)
2. Rule creation modal
3. Logs panel

== Changelog ==
See the included `changelog.txt` for full history. Stable tag: 1.11.

== Upgrade Notice ==
= 1.11 =
Adds whitelist wildcard support and per-role controls. When upgrading, verify
role-based rules after activation. No DB schema changes required.

== Other Notes ==
For support, open an issue on GitHub or email support@vsa.edu.in.