=== My Link Order ===
Contributors: froman118
Donate link: http://geekyweekly.com/gifts-and-donations
Tags: link, category, categories, order, sidebar, widget
Requires at least: 2.8
Tested up to: 3.5
Stable tag: 3.5

My Link Order allows you to set the order in which links and link categories will appear in the sidebar.

== Description ==

[My Link Order](http://geekyweekly.com/mylinkorder) allows you to set the order in which links and link categories will appear in the sidebar. Uses a drag 
and drop interface for ordering. Adds a widget with additional options for easy installation on widgetized themes.

= Breaking Change Made in 3.1.4 =

If you do not use widgets to display links you must do the following: replace your call to wp_list_bookmarks() with mylinkorder_list_bookmarks().

= Big Update! =

My Link Order has been out since WP 1.5 or 2.0 (2006) and it's been a struggle to keep it working smoothly. The taxonomy.php hack was hideous and a filter finally got added that let me avoid this. No more visiting the My Link Order page after every single Wordpress update!

As of version 2.8.6 of the plugin I'm breaking backwards compatibility to add new features like a multiple widget instances. Keep using version [2.8.4](http://downloads.wordpress.org/plugin/my-link-order.2.8.4.zip) if you are not on WP 2.8 yet.

== Changelog ==
= 3.3.1 =
* Added Danish translation
* Fixed bug with widget and ordering by ID (parameter name changed from id to link_id in 3.2)
= 3.1.4 =
* The order arguments coming into get_bookmarks() started being matched against a static list of values. To get around this I made copies of wp_list_bookmarks and get_bookmarks and modified them.
* Widget will work as is after upgrading.
* If you weren't using widgets then replace your call to wp_list_bookmarks() with mylinkorder_list_bookmarks(). It is exactly the same except it calls my version of get_bookmarks(), mylinkorder_get_bookmarks().
* The link categories are also matched against a list now, but there is a filter available after that modifies the orderby value.
= 3.0 =
* Update for compatibility with 3.0
* Switched way menu item was being added, any permission issues should be fixed
* Updated drag and drop to include a placeholder, makes it much easier to see where items will move
* Updated styles to fit in with Wordpress better
* Updated page code to use regular submit buttons, less reliance on Javascript and query strings
* Links and categories now wrapped in localization code, thanks for the tip Florian
= 2.9.1 =
* Fixed widget checkboxes not holding their value.
* Fixed several missing localization strings.
= 2.8.7 =
* Small bug fix on widget's Category Order option
= 2.8.6 =
* Significant backend changes, only compatible with 2.8 and above
* Transitioned to new Widget API, breaking backwards compatibility in the process
* Multiple widgets are now supported
* Widget options will have to repopulated
* Added more complete widget options, should be able to do just about everything you can in code
* Removed taxonomy.php hack, hooking into "get_terms_orderby" filter for category ordering now, no more visiting page after each Wordpress update
* The PO file has changed and translations will need to be updated
= 2.8.4 =
* Added "Show Names" option to widget to allow link name to be displayed with "Show Images" is selected.
= 2.8.3 =
* Trying to fix Javascript onload issues. Settled on using the addLoadEvent function built into Wordpress. If the sorting does not initialize then you have a plugin that is incorrectly overriding the window.onload event. There is nothing I can do to help. 
= 2.8.1 =
* Added Czech translation (Jan)
= 2.8 =
* Updated for 2.8 compatibility
= 2.7.1 =
* If your link categories don't show up for ordering your DB user account must have ALTER permissions, the plugin adds columns to store the order
* Added a call to $wpdb->show_errors(); to help debug any issues
* Translations added and thanks: Spanish (Karin), French (Regis), Italian (Stefano)
= 2.7 =
* Updated for 2.7, now under the the new Links menu
* Moved to jQuery for drag and drop
* Removed finicky AJAX submission
* Translations added and thanks: Russian (Flector), Dutch (Anja)
* Keep those translations coming
= 2.6.1a =
* The plugin has been modified to be fully translated
* The widget now has a description
= 2.6.1 =
* Finally no more taxonomy.php overwriting, well kind of. After you upgrade Wordpress visit the My Link Order page and it will perform the edit automatically.
* Thanks to Submarine at http://www.category-icons.com for the code.
* Also added string localization, email me if you are interested in translating.

== Installation ==

1. Install and activate plugin
2. Go to "My Link Order" under the Links menu and specify your desired order for your link categories and the links in each category
3. If you are using widgets then replace the standard "Links" widget with the "My Link Order" widget. That's it.
4. If you aren't using widgets, modify your template to use the correct filter (additional parameter seperated by ampersands):
	`mylinkorder_list_bookmarks('orderby=order&category_orderby=order');`

== Frequently Asked Questions ==

= Why isn't the order changing on my site? =

The change isn't automatic. You need to modify your theme or widgets.

= Like the plugin? =

If you like the plugin, consider showing your appreciation by saying thank you or making a [small donation](http://geekyweekly.com/gifts-and-donations).