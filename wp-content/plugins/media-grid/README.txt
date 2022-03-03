=== Media Grid ===
Contributors: shaunandrews, ericlewis
Tags: media, features-as-plugins
Requires at least: 3.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Stable tag: trunk
Version: 0.7

A grid view for the WordPress Media Library.

== Description ==

When browsing your media, why limit yourself to a list with tiny thumbnails? The Media Grid plugin gives you a nice grid view of your media items.

Please be aware that this is (and has always been) a work in progress. Activating this plugin means you’ll lose a lot of functionality, like easily editing and managing your media library. We hope to improve things rapidly.

This is a WordPress Core “features as plugins” project: http://make.wordpress.org/core/features-as-plugins/

Active development happens over at GitHub: http://github.com/helenhousandi/wp-media-grid-view/

== Installation ==

Install this plugin just like any normal WordPress plugin, by dropping the folder into your wp-content/plugins/ folder. Once you activate the plugin, head to your site's Media Library and click on the "grid" link.

== Changelog ==
= 0.6 =
Move over to extending the WordPress media Backbone Javascript.

= 0.4 =
Removing the selected sidebar (for now) in favor of simplicity. Getting rid of the new school flexbox layout in favor of the old school (and more reliable) inline-block layout. Bigger images and more room for details in the detail modal.

= 0.3 =
Adds some basic item information for the single item modal.

= 0.2 =
Fixed the missing grid button. Removed the bg blur for the modal, it was too resource intensive. Better scrolling and flexbox in the comparison modal. Icon added for the edit link.

= 0.1 =
Starting development with a concept UI. Some things don't actually work. Experimental tagging of media items is commented out. Delete doesnt actually delete. Search is limited to the items viewable on the page.