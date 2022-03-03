=== Quick Flickr Widget ===
Contributors: kovshenin
Donate link: http://kovshenin.com/beer/
Tags: flickr, photos, photo, gallery, widget, widgets, sidebar
Requires at least: 3.3
Tested up to: 3.4.1
Stable tag: 1.3

Display your Flickr photos in your sidebar.

== Description ==

Use this widget to display your Flickr photos in your sidebar, via a Flickr username or a Flickr RSS feed URL.

== Installation ==

1. Upload archive contents to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Check out your sidebar widgets configuration

== Screenshots ==

1. Widget configuration
2. In action

== Change log ==

= 1.3 =
Just rewrote the whole sh**, now uses the WP_Widget class, uses the WordPress HTTP functions, transient caching and more. The upgrade script will (hopefully) update your old widget to the new widget.

= 1.2.10 =
Minor js bugfix.

= 1.2.9 =
Compatible with WordPress 2.8 (Thickbox, jQuery) and 1.5. Fixed some Javascript bugs. Photos titles in divs instead of spans.

= 1.2.8 =
Whoops! Fixed the javascript problem ;)

= 1.2.7 =
Increased maximum number of photos from 10 to 20. You can now use javascript instead of php! Under beta though ;)

= 1.2.6 =
Added the "Random pick" ability using shuffle.

= 1.2.5 =
Minor bugfixes (including the Thickbox siteurl issue). Added the ability to filter images by tags. Licensed under GPL v3.

= 1.2.4 =
Now supports Thickbox effects! +minor bugfixes. Does not require JSON functions anymore, therefore works on PHP 4.

= 1.2.3 =
Minor bug fixes. Using php format and eval() instaed of json and json_decode().

= 1.2.2 =
Now supporthing both Flickr screen name and RSS feed in the widget configuration. Please note, that if you are using a Flickr RSS feed, then it SHOULD start with `http://api.flickr.com/services/feeds`.

= 1.2.1 =
Took me a while to figure out the difference between username and screenname in the Flickr API. There was a bug in 1.2 when using screen names with spaces. Here's a fixed version. Special thanks to Tung Nguyen Thanh ;)

= 1.2 =
Considered some feature requests. Okay, so I don't use RSS anymore, cause that sucks. Flickr has got an open API, so I use the REST interface to send requests and retrieve data in JSON format. It's much easier this way - no more useless regular expressions. In this version I don't even require you to go get your RSS feed link, all you need is a Flickr username and you're done. I make a Flickr API call to convert your username into an ID during widget configuration, then the requests from the widget are made by another API call using that ID. Does not require a Flickr API key.

= 1.1 =
Yeah I know _blank targets totally suck and that it's very unkind of opening a new browser window (or tab) without users' permission, but anyways, somebody requested this so here you go.

= 1.0 =
We got hosted at WordPress.org! Wohooo! Minor changes to the php file and restructured the readme.txt (and I actually renamed it to readme.txt, duh!)

= 1.0b =
This is the start, so good luck to me and you all too. You may browse the source by the way, it's not too complicated yet.

== Upgrade Notice ==

= 1.3 =
Now compatible with 3.4! Multi-widget enabled, caching enabled, for faster and better and more secure user experience. Some of the options have been removed too, for easier configuration and less compatibility problems.