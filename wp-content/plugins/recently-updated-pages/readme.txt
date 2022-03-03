=== Recently Updated Pages ===
Contributors: Ehsanul Haque
Author URI: http://ehsanIs.me/
Tags: updated, recent, page, post
Requires at least: 2.8
Tested up to: 3.4.2
Stable Tag: 1.0.4

Purpose of this plugin is to display the list of pages (and optionally posts) on Wordpress blog those have been recently updated. It also lets you use WP's shortcodes to display last update date of the page or blog posts. 

== Description ==

Sometimes when you update one of your pages on the Wordpress blog you would want visitors to know about those. This widget will create a sidebar 
box with a list of pages you've recently updated. It also shows the date of the update beside the page title. You can choose whether to display the update date and in what format. 

You've an option to display the Posts in the list as well. If checked (through admin panel) the list will include the Posts along with the list of Pages.

You can use WP's shortcode to display the last update date of the page or blog posts. Date/time format for the shortcode can be controlled through the widget settings. 

V 1.0.4 only fixes the bug related to the widget area where any widget (in admin panel) below RUP's widget will lockup and cannot be moved. 

== Installation ==

This plugin can be installed in the standard plugin installation process.

i.e. 

1. Upload `recently_updated_pages.php` to the `/wp-content/plugins/` directory (may also create a directory under `/wp-content/plugins/` to keep files organized)
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to `Widgets` section under `Appearance` menu
4. Drag the `Recently Updated Pages` widget into the `Available Widgets` section
5. Position the widget in the location of choice in the sidebar you want it to be on

== Frequently Asked Questions ==

= What if I keep the title blank? =

By default the widget will show "Recently Updated Pages" as the title. If you want to display a different title, type it in and save.

= How many pages will it show in the list? =

You can define it through the widget editor in the Wordpress admin panel. Specify the number and save.

= Will I be able to show my blog POST titles in the list? =

Yes, now you can. Check the option to include the blog Posts in the list and it will show them along with the Page list.

= Is there a way to hide the last update date? =

Yes, you can do that. Toggle the checkbox to hide or display the date. 

= Can I change the date format? = 

There is now a text box to specify the date format. There's also a small help below the admin form in the Widget section.

= How do I use the shortcode? =

There are two ways you can use it. Either by modifying the template files (e.g. single.php or page.php or footer.php) of the currently running theme or you can add the shortcode within the blog post or page through the WP admin post/page editors. 

To use the shortcode within the template file add the following PHP code:

&lt;&#63;php echo do_shortcode('[rup_display_update_date]'); &#63;&gt;

To use the shortcode through the post/page editor:

[rup_display_update_date]

= Can I control the date/time format for the shortcode?

Yes, you can. Through the widget settings (WP Admin > Appearance > Widgets > Recently Updated Pages) you can now control the date/time format for shortcodes.

= I'm trying to display the update date/time like 20th Aug 2010 at 5:30pm, but the word "at" shows up like "pm31". Why?

Because PHP's date function will recognize the characters "a" and "t" as format parameters and parse them. In order to show the word "at" you will need to escape both characters like "\a\t". 

== Screenshots ==

1. Widget on the blog, only showing the Pages
2. Widget as shown in Admin panel
3. Widget on the blog showing both Pages and Posts but not the date

== Changelog ==

= 1.0.0 =
* First release

= 1.0.1 = 
* Added feature to display list of recently updated blog Posts along with the pages

= 1.0.2 = 
* Added the feature to hide/display the update date
* Added the feature to specify the date format 

= 1.0.3 = 
* Added the feature to use shortcodes and control its date/time format

= 1.0.4 = 
* Bug fix: Fixed the admin panel widget area issue where any widget below RUP widget will lock up. 