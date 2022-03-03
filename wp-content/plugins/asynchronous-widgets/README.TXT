=== Asynchronous Widgets ===
Contributors: da.futt
Tags: widgets, ajax, asynchronous
Requires at least: 2.8.6
Tested up to: 3.3.1
Stable tag: 1.1.1

Asynchronous Widgets allows you to have any registered widget on your WordPress.org-powered
site be loaded asynchronously via an AJAX call.

== Description ==

Asynchronous Widgets allows you to have any registered widget on your WordPress.org-powered
site be loaded asynchronously via an AJAX call. It is configured via the admin panel. No further integration is required.

== Installation ==

1. Download the plugin;
2. Extract the plugin archive to `/wp-content/plugins`;
3. Activate the plugin through the 'Plugins' menu in the administration section;
4. Configure the plugin via its settings page;
5. That's all.

== Frequently Asked Questions ==

= How does it work? =

The plugin replaces the callback a widget has registered with the WordPress.org engine
with one of its own. When that callback is invoked, instead of outputting the widget content,
it outputs a customized chunk of HTML. That chunk contains information that will be parsed
on the client side, after the page is loaded, by a short bit of JavaScript this plugin also
injects on the page. This will result in an AJAX call to the server delivering the actual
widget content (one call for each widget).
The HTML also contains a `<noscript>` block with some information, in case JavaScript
shouldn't be enabled.

This mechanism is set to run on the `wp_head` action hook, at priority 2^17.

= What's it good for? =

Good question. I don't know. It seemed like a froody idea.

More seriously though, the main motivation for writing this was an instance where I wanted
the contents of a specific widget NOT appear to search engine crawlers. Since with this
plugin, the widget content won't be visible unless JavaScript is enabled, this seemed
the perfect way to achieve that goal.

I'm anxious to see whether it can have any other uses, though.
I am also aware that the purpose stated above isn't entirely uncontroversial.

In terms of performance, this plugin will most definitely increase your pages' 
delivery speed (in proportion to how many and how "heavy" widgets you have), and 
*probably not* increase your server's load average significantly.

= Is it compatible with custom widgets and/or other plugins? =

Yet another good question. I'm not sure, but I'm trusting you to help me find out.

I've tested it successfully with a very few custom widgets. But there might be problems,
due to one significant change this plugin introduces. Namely, the fact that it detaches
the widget display callback from the rest of the WordPress loop (the widget callback is
invoked asynchronously, thus outside of the loop). **If a custom widget makes some
non-persistent changes somewhere in the loop AND relies on those changes in its
display callback, there MAY be compatibility problems**.

One specific instance I've encountered was with the WP Widget Cache plugin
(http://wordpress.org/extend/plugins/wp-widget-cache/) (which doesn't work with WordPress
2.9, anyway). I had to integrate a little hack (essentially, making the changes WidgetCache
performs persistent) to ensure compatibility with it.

Other such conficts might arise. If you encounter one, please provide some [feedback](http://wordpress.org/tags/asynchronous-widgets?forum_id=10). 

= Which PHP version does it use? =

This plugin has been developped with PHP version 5.3.1.

= Can I get hooked? =

* You can hook on the action that outputs the proxy HMTL code: `async_widgets_output_proxy_html`.
* You can filter on the action that outputs the proxy HTML code: `async_widgets_output_proxy_html_filter`.

Each of these take one argument: the "widget settings" array as would normally be passed
to the widget display callback (they'll be invoked once for each widget).

If using the filter, you MUST return that value, lest you want to supress the widget's output.

= Can I donate? =

No. Go buy yourself something nice.

== Screenshots ==

1. The plugin settings page in the admin section. This is pretty much the only visible
effect of this plugin.

== Changelog ==

= 1.0.0 =

* Initial Release

= 1.1.0 =

* Moved widget callback parameter persistence (between page call and AJAX call)
from database to PHP $_SESSION, because it doesn't belong in the former.

= 1.1.1 =

* Picked up this old project and made it work with the lastest WordPress version. 

== Upgrade Notice ==

= 1.1.0 =
Storing the widget callback parameters in the database was most probably
a bad idea, since they may be different from user to user, at least in theory.

= 1.1.1 =

Asynchorous Widgets has been updated to be compatible with WordPress 3.3.1

== Arbitrary section ==

= TODO =

 * I18N