===Eventbrite Attendees Shortcode ===
Contributors: austyfrosty
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7329157
Tags: eventbrite, shortcode-only, event, attendee, shortcode, json 
Requires at least: 3.7
Tested up to: 4.1
Stable tag: trunk

A shortcode to output your Eventbrite attendee list.

== Description ==

A shortcode to pull in your [Eventbrite](http://www.eventbrite.com/r/thefrosty) attendees list.

Example shortcode useage:

`[eventbrite-attendees id="384870157"]`

More options: `[eventbrite-attendees id="YOUR_EVENT_ID" sort="true|false" clickable="true|false" user_key="USER_KEY(IF_NOT_SET_IN_SETTINGS)"]`

Shortcode args:

1. *id*: with your Eventbrite event id.
2. *sort*: Should the attendee list be sorted by puchase date?
3. *clickable*: Should links be clickable?
4. *user_key*: Your API user key if not saved in the settings.

Leave any comments about the [Eventbrite Attendees Shortcode](http://austin.passy.co/wordpress-plugins/eventbrite-attendees-shortcode/) [here](http://austin.passy.co/wordpress-plugins/eventbrite-attendees-shortcode/).

Attendee output control. Eventbrite returns a lot of information in regards to each attendee. You can filter out what you do not want by creating a filter. Use the example code bellow in your theme or a functionallity plugin:

`
// See: http://developer.eventbrite.com/doc/events/event_list_attendees/ 'only_display' for allowed keys.
function frosty_eventbrite_attendee_data_to_remove( $data ) {
	// If you want to start fresh use the next line
	//$data = array( 'ticket_id', 'tax' ); // etc..
	
	// If you want to remove additional info from the default
	//$newdata = array( 'eventbrite_fee', 'created' ); // etc..
	//$data = array_unique( array_merge( $newdata, $data ) );
	return $data;
}
add_filter( 'eventbrite_attendees_only_display', 'frosty_eventbrite_attendee_only_display );
`

Removed is the old app_key and replaced with the user_key. If you want to use your own app key filter it like so:

`
function frosty_eventbrite_attendees_app_key( $key ) {
	return 'MY_NEW_KEY';
}
add_filter( 'eventbrite_attendees_app_key', 'frosty_eventbrite_attendees_app_key' );
`

Template $make_clickable example; here are two examples:
`
#1
$name = 'display_name'
function frosty_eventbrite_attendees_make_display_name_clickable() {
	return true; //default is false
}
add_filter( "eventbrite_attendees_{$name}_make_clickable", 'frosty_eventbrite_attendees_make_display_name_clickable' );

#2
$names = array( 'display_name', 'company' );
foreach ( $names as $name ) :
	add_filter( "eventbrite_attendees_{$name}_make_clickable", "frosty_eventbrite_attendees_make_clickable" );
endforeach;
 
function frosty_eventbrite_attendees_make_clickable() {
	return true; //default is false
}
`

== Installation ==

Follow the steps below to install the plugin.

1. Upload the `eventbrite-attendees-shortcode` directory to the /wp-content/plugins/ directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Settings/eventbrite-attendees to enter your App Key.
4. Visit any post page and enter the shorcode or use the shortcode generator.

== Frequently Asked Questions ==

= Donations? =

Please! Or support my by visting [Extendd.com](http://extendd.com); A premium WordPress plugin marketplace.

= Why create this plugin? =

I created this plugin to easily show your attendees from any event you've created on [Eventbrite](http://www.eventbrite.com/r/thefrosty).

== Screenshots ==

1. Eventbrite Attendees Shortcode Settings page.
2. Shortcode generator on post page.

== Changelog ==

= Version 1.1.3 (10/29/14) =

* Added array_unique to `eventbrite_attendees_keys_to_unset` filter.
* Remove 'eeeee' typo in email class. 
* Added global `$attendee_website` variable. Add a website link to any template with the global var.
* Added: `eventbrite_attendees_{$name}_make_clickable` filter where $name is the template name ex: email, first_name, display_name or etc.
	* Default is false, if true will add the website URL to the template value.
* Updated default and display_name template.

= Version 1.1.2 (10/24/14) =

* Added more keys to `eventbrite_attendees_keys_to_unset` filter.

= Version 1.1.1 (10/24/14) =

* Added `eventbrite_attendees_folder_template` filter for template name in your current theme.
* Added `eventbrite_attendees_only_display` filter for the display only Eventbrite fields.
* Removed 'event_id' and 'id' from the attendee output.

= Version 1.1 (10/20/14) =

* Update: Change `app_key` to `user_key`
* Fix: Make user_key required. Find it here: https://www.eventbrite.com/userkeyapi
* Removed: app_key, as it's no longer needed.

= Version 1.0 (2/20/14) =

* Well hello there! Everything is new. 
* Be sure to get your developer API Key and enter it in the settings.

= Version 0.3.3 (11/8/11) =

* Feeds updated.
* WordPress 3.3 check.

= Version 0.3.2 (9/8/11) =

* Dashboard fix.

= Version 0.3.1 (6/23/11) =

* [BUG FIX] An error in the dashboard widget is casuing some large images. Sorry. Always escape.

= Version 0.3 = 

* Complete rewrite and overhaul.

= Version 0.2.1&alpha; = 

* Removed javscript link causing hang-ups.

= Version 0.2&alpha; =

* `array_slice` fix.
* Spelling fixes

= Version 0.1 =

* Admin upgrade.
* RSS feed changed to *list* items in **one** listed element.

= Version 0.1&alpha; =

* Initial release.

== Upgrade Notice ==

= Version 1.0 =

* Complete code rewrite. Everything is new! Now using the Eventbrite Developer API.
