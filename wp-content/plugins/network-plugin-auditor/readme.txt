=== Network Plugin Auditor ===
Contributors: ksemel
Tags: network, multisite, plugin management, theme management, admin
Donate Link: http://bonsaibudget.com/donate/
Requires at least: 3.2.1
Tested up to: 4.6.1
Stable tag: 1.10.1

For multisite/network installations only.  Adds columns to your network admin to show which sites are using each plugin and theme.

== Description ==

As my WordPress network grew I found it challenging to track which plugins and themes were used on each site, and the only way to check was to visit each dashboard one at a time.  What a hassle!

This plugin adds columns to your Network Admin on the Sites, Themes and Plugins pages to show which of your sites have each plugin and theme activated.  Now you can easily determine which plugins and themes are used on your network sites and which can be safely removed.

== Installation ==

1. Upload the files to the /wp-content/plugins/ directory.
2. Network Activate the plugin through the 'Network Plugins' menu in WordPress

== Changelog ==

= 1.10.1 =

- Add filter for the args sent to wp_get_sites(), thanks to @jazbek
- Send correct args to get_sites to pull data from additional blogs beyond 100, thanks to @wlpdrpat

= 1.10 =

WordPress 4.6 compatibility fixes

= 1.9.1 =

WordPress 4.3 compatibility fixes

= 1.9 =

Added blog status to plugin listing
Adjusted code for better i18n
- Brazilian Portuguese translation now available, courtesy of Gabriel Reguly (gabriel-reguly)

= 1.8.1 =

Check out the mirror on github! https://github.com/ksemel/network-plugin-auditor
Some cleanup when running PHP Strict

= 1.8 =

Fixed limit on wp_get_sites() to support up to 10000 sites ( the default wp_is_large_network() limit ) ( Props to iclysdale )

= 1.7 =

- Spanish translation now available, courtesy of Maria Ramos

= 1.6 =

- Displays parent-child theme relationships
- Added translation hooks

= 1.5.2 =

- Fixed a logic error in cleanup

= 1.5.1 =

- Fixed error where themes page does not show the active blogs
- Fixed a database error when there is no option table with the base prefix

= 1.5 =

- Fixed the transient cache not clearing on plugin activation and deactivations

= 1.4 =

- Added a theme column to the Sites page (User request from marikamitsos)
- Wordpress 3.6 compatibility updates

= 1.3.2 =

- Reduced transient name length to under 45 characters

= 1.3.1 =

- Fixed a bug where the primary blog would show all available themes as active even if they were not.
- Fix over-long transient names in db fields

= 1.3 =

- Fixed Wordpress 3.5 compatibility issues

= 1.2 =

- Fixed an issue where the database prefixes were not determined correctly (Thank you montykaplan for your debugging log info!)
- Added messaging for the case where the database prefix is blank (which isn't supported in multisite as of 3.3)

= 1.1 =

- Added support for Themes.  Now shows which themes are actually used and by which blog in your themes list
- Stored some of the more intensive queries in the transient cache to improve performance
- Improved error handling

= 1.0.1 =

Bug fix: Check column_name before adding the output (Thanks to gabriel-reguly for the catch!)

= 1.0 =

Initial release

== Frequently Asked Questions ==

= Will this plugin work with my single-site wordpress installation? =

No, the columns are only added in the network admin dashboard.  There is no change on the normal site dashboards so there is nothing to see on a single-site installation.

= All my blogs are showing blank columns except for the first! =

Please update to version 1.2 for improved support for custom database prefixes.

= Can I use this plugin as an Must-Use plugin? =

Yes!  Just copy the network-plugin-auditor.php file to your mu-plugins folder.  Be aware that you will not receive automatic notices of updates if you choose to install the plugin this way.

= I want to help! =

Sure, head over to https://github.com/ksemel/network-plugin-auditor, fork the repo and send in a pull request!

= I found a bug =

And I want to know about it!  You can visit the Support Forum (http://wordpress.org/support/plugin/network-plugin-auditor) or open an issue in github (https://github.com/ksemel/network-plugin-auditor/issues)

== Screenshots ==

1. Plugin Active on the Network Plugins page
2. Plugin Active on the Network Sites page
3. Plugin Active on the Network Themes page
