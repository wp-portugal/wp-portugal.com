=== Client-proof Visual Editor ===
Contributors: hugobaeta, vanillalounge
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=hugo%40baeta%2eme&lc=US&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted
Tags: visual editor, TinyMCE
Requires at least: 3.0
Tested up to: 5.4
Stable tag: 1.7.1

Simple, option-less, plugin to make TinyMCE - the WordPress Visual Editor - easier for clients and n00bs.

== Description ==

Simple, option-less (yeah, that's a feature), plugin to make TinyMCE - the WordPress Visual Editor - easier for clients and n00bs. It removes a bunch of TinyMCE features that could potentially be used by inexperienced clients to screw-up the theme developers hard work! It also makes TinyMCE remove the nasty formatting when you paste content directly in it - so, there is no need to use the "paste from Word" or "paste from text" buttons!

Thanks to [Z&eacute; Fontainhas](https://profiles.wordpress.org/vanillalounge) for testing and SVN help and [Tiago Rodrigues](http://http://trodrigues.net/) for help with TinyMCE "paste" issues.

== Frequently Asked Questions ==

= What buttons are kept in the Editor? =
* Strong
* Emphasis
* Strike Through
* Format (h2, h3, h4, Paragraph, Code)
* Unordered Lists
* Ordered Lists
* Blockquote
* Link Buttons
* Horizontal Line
* More Tag
* Full Screen mode

= Where are the plugin options? =

This plugin has no options. That is a feature! Just activate it and enjoy the simplicity.

= Does it do anything else? =

When you paste text directly into the visual editor, all formatting gets removed automagically! There's no need to teach clients to use the "Paste as Text" or "Paste from Word" buttons (which I always found hard to get people to remember using them).

== Installation ==

1. Upload `clientproof-visual-editor.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Enjoy the simplified Visual Editor

== Screenshots ==

1. screenshot-1.png

== Changelog ==

= 1.7.1 =
* Less stringent PHP/WP requirements

= 1.7 =
* Updated WordPress version tested up to 5.4.
* Implement WordPress Coding Standards
* Fix plugin header comments

= 1.6 =
* Updated WordPress version tested up to 4.9.
* Implement WordPress Coding Standards
* Update contributors list

= 1.5 =
* Updated WordPress version tested up to 4.2.

= 1.4 =
* Updated WordPress version tested up to 4.1
* Cleaned up code and added some comments
* Fixed the fullscreen button (using TinyMCE's fullscreen mode, not the WordPress Distraction-Free mode.)

= 1.3 =
* Updated WordPress version tested up to.

= 1.2 =
* Updated code to fix bug with second row of buttons showing up sometimes.

= 1.1 =
* Updated code to add full-screen mode button and update version compatibility to WordPress 3.8

= 1.0 =
* Plugin creation
