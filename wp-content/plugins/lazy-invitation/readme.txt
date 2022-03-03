Je=== Slack Lazy Invitation ===
Contributors: juliobox
Tags: slack
Requires at least: 4.0
Tested up to: 4.4
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Slack Lazy Invitation lets you auto invite anyone to your Slack Group.

== Description ==

Slack Lazy Invitation lets you auto invite anyone to your Slack Group without adding each member each time they want to be invited. Boring.

Invitation page be like example.com/wp-login.php?action=slack-invitation

Auto support for sf-move-login, wp-reCaptcha and google-captcha, read the FAQ

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the zip content into the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==
1. Why do i have to use the bookmarklet to find this api key?

Be cause there is no other way to get it, sorry, this is the trick ;)

1. What about SF Move Login?

If you're running the plugin SF Move Login, the invitation page won't be /wp-login.php?action=slack-invitation but /slack-invitation instead. You can now, of course, change this slug via the sf move login settings

1. What about captcha plugins?

If you're running wp-reCaptcha or google-captcha, you have nothing to do, just activate it
If you're running another captcha plugin, tell me, i'll add it.

== Screenshots ==

1. Invitation Screen (=1 team)
1. Invitation Screen (>1 team)
1. Invitation Sent (default)
1. Invitation Sent (and customized)
1. Settings (these are fake tokens ;p)

== Changelog ==

= 1.4 =
* 26 jan 2016
* Use only get_option() and not get_site_option()

= 1.3 =
* 29 sept 2015
* Support multi slacks teams
* New hook 'slack-invitation-default-botname' to filter the slackbot name
* Both 'slack-invitation-default-botname' and 'slack-invitation-default-avatar' got a new param "group" to filter it depending on the selected group, see screenshots

= 1.2 =
* 01 sept 2015
* Support reCaptcha v1 and v2
* New hook 'slack-invitation-default-avatar' to filter the slackbot avatar
* Fix bug using _() instead of __()

= 1.1 =
* 28 july 2015
* Add 2 links to find the invitation page, props @pollyplummer
* Add auto support for 2 Captcha plugins : wp-reCaptcha and google-captcha, read the FAQ

= 1.0 =
* 25 july 2015
* First Release