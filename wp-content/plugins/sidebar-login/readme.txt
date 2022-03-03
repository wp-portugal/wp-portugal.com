=== Sidebar Login ===
Contributors: mikejolley
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=mike.jolley@me.com&currency_code=&amount=&return=&item_name=Donation+for+Sidebar+Login
Tags: login, sidebar, widget, sidebar login, login widget, login form
Requires at least: 5.0
Tested up to: 5.5
Requires PHP: 5.6
Stable tag: 3.0.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Easily add an ajax-enhanced login widget to your WordPress site sidebar.

== Description ==

_Sidebar Login_ adds a useful login widget which you can use to login from in the sidebar of your WordPress powered blog.

Once a user logs in they are redirected back to the page they logged in from, rather than the admin panel (this is configurable).

If you'd like to contribute code to the plugin you can do so via [GitHub](https://github.com/mikejolley/sidebar-login).

If you'd like to contribute a translation, [the project is hosted on WordPress.org](https://translate.wordpress.org/projects/wp-plugins/sidebar-login).

= Usage =

Simply go to `Appearance > Widgets` and drag "sidebar login" to the sidebar of your choice. Within the widget are several options you can use for changing the titles or the links displayed to the user.

To use this widget in a template, use [the `the_widget()` function](http://codex.wordpress.org/Function_Reference/the_widget) which WordPress provides.

= Tags for titles + links =

These tags can be used in the widget settings for titles + links and will be replaced at runtime.

* `%username%` - logged in users display name
* `%userid%` - logged in users ID
* `%firstname%` - logged in users firstname
* `%lastname%` - logged in users lastname
* `%name%` - logged in users firstname + lastname
* `%nicename%` - user nice name
* `%admin_url%` - url to WP admin
* `%logout_url%` - logout url
* `%buddypress_profile_url%` - Buddypress Profile URL
* `%bbpress_profile_url%` - Buddypress Profile URL
* `%avatar%` - User Avatar

= Filter Reference =

* `sidebar_login_include_css` - return false to not include the CSS stylesheet
* `sidebar_login_widget_logged_in_links` - An array of links shown when logged in.
* `sidebar_login_widget_logged_out_links` - An array of links shown when logged out.
* `sidebar_login_widget_display` - Return false to hide the widget.
* `sidebar_login_widget_logged_in_title` - The widget title shown when logged in.
* `sidebar_login_widget_avatar_size` - The avatar size - defaulted to 38 (thats in px)
* `sidebar_login_widget_logged_out_title` - The widget title shown when logged out.
* `sidebar_login_widget_form_args` - Arguments for the wp_login_form function.
* `sidebar_login_widget_login_redirect` - Redirect URL after login.
* `sidebar_login_widget_logout_redirect` - the redirect after logging out.
* `sidebar_login_widget_register_url` - The URL for registration links.
* `sidebar_login_widget_lost_password_url` - The URL for lost password links.
* `sidebar_login_widget_wp_login_form` - The HTML of the Login form.

= Action Reference =

* `sidebar_login_widget_start` - Fired before the widget.
* `sidebar_login_widget_{logged_in || logged_out}_content_start` - Fired before the widget content.
* `sidebar_login_widget_before_{logged_in || logged_out}_links` - Fired before the links.
* `sidebar_login_widget_after_{logged_in || logged_out}_links` - Fire after the links.
* `sidebar_login_widget_{logged_in || logged_out}_content_end` - Fired after the widget content.
* `sidebar_login_widget_end` - Fired after the widget.

= Notes =

* Due to AJAX not working across different domains (see [same_origin_policy](http://en.wikipedia.org/wiki/Same_origin_policy)), AJAX logins will be disabled if your site it non-SSL, but the FORCE_SSL_ADMIN constant is set to true. Instead it will fallback to a traditional POST.

== Screenshots ==

1. The widget when logged in
2. The widget when logged out
3. Widget settings

== Changelog ==

= 3.0.1 =
* Fix - Fix rendering of additional links when using non-latin characters in the link text.

= 3.0.0 =
* Refactor - Rewritten majority of plugin using more up to date standards and namespaces.
* Refactor - Cleaned up the widget settings form to make functionality clearer.
* Refactor - Removed email->username conversion since this is supported natively.
* Tweak - Added %nicename% placeholder.
* Tweak - Improved default styling and included extra wrapping divs (.sidebar-login-form, .sidebar-login-account, .sidebar-login-links).
* Performance - Removed jQuery dependency and BlockUI script.
* Performance - Removed `sidebar_login_js_in_footer` filter - this is now true by default.
* Fix - Prevent notices due to incorrect sizeof() usage.

= 2.7.3 =
* Tweak - %avatar% placeholder.
* Tweak - Removed translations due to WordPress.org language packs.
* Tweak - sidebar_login_widget_wp_login_form filter.
* Fix - Removed force_ssl_login which is deprecated in 4.4 in favour of force_ssl_admin.

= 2.7.2 =
* Feature - Added firstname, lastname, name tags.
* Fix - PHP4 widget contructors (https://gist.github.com/chriscct7/d7d077afb01011b1839d).
* Fix - Missing strings in localisation.
* Fix - Change email to username during wp_authenticate hook.

= 2.7.1 =
* Make rememberme show by default, without a widget save.

= 2.7.0 =
* Renamed textdomain from sidebar_login to sidebar-login.
* Moved translations to Transifex.
* Added Grunt.
* Removed deprecated sidebarlogin function.
* Option to show/hide the rememberme checkbox.
* Allow login using an email address - username will be queried based on address given.

= 2.6.0 =
* Support for Buddypress Profile URL via %buddypress_profile_url%. Thanks Tim Reeves.
* Support for BBPress Profile URL via %bbpress_profile_url%.
* Slovakian translation by Alexander Kriss
* Swedish translation by Tommy Wahlund
* Dutch translation by Bert Smulders
* Russian and Ukranian translation by Yarri
* Polish translation by Czeslaw Liebert

= 2.5.3 =
* Removed nonce check on frontend due to conflict with caching scripts
* Arabic translation by Mamoun Elkheir
* Brazillian translation by Marco André Argenta
* Italian translation by Adriano Calvitto

= 2.5.2 =
* Latvian translation by Lana Mangusa
* German translation by Klaus-Peter
* Wrapped response to prevent crap plugins breaking the response

= 2.5.1 =
* Filterable error classes
* Tweak to how SSL logins are handled.
* FR translation
* Fix $link_cap

= 2.5.0 =
* Rewrite and code cleanup - class based.
* Optionless - Moved all settings to the widgets themselves rather than having a settings page.
* Removed all localisations (these are out of date, and need re-doing)
* Removed JSONP/GET request - although this allowed logins between urls of different origin, it poses a security risk as GET requests are logged.

= 2.4.0 =
* XSS Fix
* Added classes to tags in widget
* Improved/filtered register and lost password links
* Removed the outdated openid/fb code. Hook it in if you want it.
* Removed markup in favour of wp_login_form()

= 2.3.6 =
* Sanitize REQUEST_URI/$pageURL

= 2.3.5 =
* 	Use jsonp to enable login from http to https
* 	Fixed remember me logic (in js)
* Sanitize redirect url
* Removed esc_attr from username and password to prevent breaking login
* Updated french and italian langs
* 	Added Swedish lang by Ove Kaufeldt

= 2.3.4 =
* SSL URL tweak
* Better handling for force_ssl_login and force_ssl_admin

= 2.3.3 =
* Removed a link after request from WordPress.org staff
* wp_lostpassword_url() for lost password link
* sanitized user_login
* Uses wp_ajax for ajax login instead of init functions
* 	Secure cookie logic change

= 2.3.2 =
* Login redirect fix

= 2.3.1 =
* Error loop fix
* Added filter for errors - sidebar_login_error

= 2.3 =
* Put the project on GitHub
* Added new localisations
* New options panel
* 	AJAX Login

= 2.2.15 =
* FORCE_SSL_LOGIN/ADMIN double login issue fix (Thanks to bmaupin)
* Only added openid styling if other plugin is installed
* Added more languages

= 2.2.14 =
* Further revised the |true / |user_capability code - only need to use one or the other now.

= 2.2.13 =
* Updated translations
* Support for https and style.css
* is_date fix
* Added option for headings
* Removed attribute_escape for esc_attr - therefore this version needs wp 2.8 and above
* USER LEVEL option gone - replaced with USER CAPABILITY instead - use a capability like 'manage_options'

= 2.2.12 =
* Headers sent bugs fixed
* Avatar display option

= 2.2.11 =
* More/Updated langs

= 2.2.10 =
* Moved settings to appearance menu
* Changed min user level to capilbilty 'manage_options'
* Fixed menu showing in wordpress 3.0
* Added %USERID% for links
* Fixed white space bug for link options

= 2.2.8 =
* Min level setting for links. Add user level after |true when defining the logged in links.
* Moved 'settings' from tools to settings.
* Encoded ampersand for valid markup
* Moved Labels about
* Fixed SSL url
* Reusable widget

= 2.2.6 =
* Added changelog to readme.
* OpenID Plugin (http://wordpress.org/extend/plugins/openid/) Integration.
* %username% can be used in your custom links shown when logged in (gets replaced with username)
* WP-FacebookConnect (http://wordpress.org/extend/plugins/wp-facebookconnect/) integration (untested!)
* Minor fixes (worked through a big list of em!)
