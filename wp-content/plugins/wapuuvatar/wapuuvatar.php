<?php
/*
Plugin Name: Wapuuvatar
Description: Use Wapuus for your user avatars.
Plugin URI: http://www.leewillis.co.uk
Author: Lee Willis
Author URI: http://www.leewillis.co.uk
Version: 2.7
License: GPL2
Text Domain: wapuuvatar
*/

/*
    Copyright (C) 2015-2021  Lee Willis  wordpress@leewillis.co.uk

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Init. Setup translation for the plugin.
 */
function wapuuvatar_init() {
	$locale = apply_filters( 'plugin_locale', get_locale(), 'wapuuvatar' );
	load_textdomain( 'wapuuvatar', WP_LANG_DIR . '/wapuuvatar/wapuuvatar-' . $locale . '.mo' );
	load_plugin_textdomain( 'wapuuvatar', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'wapuuvatar_init' );

/**
 * Register our avatar type so it can be chosen on the admin screens.
 *
 * @param  array $avatar_defaults  Array of avatar types.
 *
 * @return array                   Modified array of avatar types.
 */
function wapuuvatar_avatar_defaults( $avatar_defaults ) {
	$avatar_defaults['dwapuuvatar'] = __( 'Random wapuus', 'wapuuvatar' );
	$avatar_defaults['wapuuvatar'] = __( 'Random wapuus everywhere (No gravatars at all)', 'wapuuvatar' );
	return $avatar_defaults;
}
add_filter( 'avatar_defaults', 'wapuuvatar_avatar_defaults' );


/**
 * Implements get_avatar().
 *
 * Generate a Wapuuvatar if requested.
 */
function wapuuvatar_get_avatar( $avatar, $id_or_email, $size, $default, $alt, $args ) {
	if ( is_admin() ) {
		$screen = get_current_screen();
		if ( is_object($screen) && in_array( $screen->id, array( 'dashboard', 'edit-comments' ) ) && $default == 'mm') {
			$default = get_option( 'avatar_default', 'mystery' );
		}
	}
	if ( $default != 'wapuuvatar' && $default != 'dwapuuvatar' ) {
		return $avatar;
	}
	list ( $url, $url2x ) = wapuuvatar_generate_avatar_url( $id_or_email, $size );
	$class = array( 'avatar', 'avatar-' . (int) $args['size'], 'photo' );
	if ( $default == 'wapuuvatar' ) {
		return sprintf(
			"<img alt='%s' src='%s' srcset='%s' class='%s' height='%d' width='%d' %s/>",
			esc_attr( $args['alt'] ),
			esc_url( $url ),
			esc_attr( "$url2x 2x" ),
			esc_attr( join( ' ', $class ) ),
			(int) $args['height'],
			(int) $args['width'],
			$args['extra_attr']
		);
	}
	if ( 'dwapuuvatar' == $default ) {
		return str_replace( 'dwapuuvatar', urlencode( esc_url( $url ) ), $avatar );
	}
	return $avatar;
}
add_filter( 'get_avatar', 'wapuuvatar_get_avatar', 10, 6 );

/**
 * Generate the Wapuuvatar URL for a specific ID or email.
 *
 * @param  mixed  $id_or_email  The ID / email / hash of the requested avatar.
 * @param  int    $size         The requested size.
 * @return array                Array of standard and 2x URLs.
 */
function wapuuvatar_generate_avatar_url( $id_or_email, $requested_size ) {

	// Select a size.
	$sizes = array( 128, 64, 32 );
	$selected_size = max($sizes);
	foreach( $sizes as $choice ) {
		if ( $choice >= $requested_size ) {
			$selected_size = $choice;
		}
	}

	// Pick a wapuu.
	$hash        = wapuuvatar_id_or_email_to_hash( $id_or_email );
	$wapuus      = wapuuvatar_get_wapuus();
	$wapuu       = hexdec( substr( $hash, 0, 4) ) % count( $wapuus );
	$wapuu_base  = apply_filters( 'wapuuvatar_chosen_wapuu', $wapuus[ $wapuu ], $id_or_email, $hash );
	$wapuu_img   = $wapuu_base . '-' . $selected_size . '.png';
	$wapuu_img2x = $wapuu_base . '-' . ( $selected_size * 2 ) . '.png';

	// Common base URL.
    $base_url = plugins_url() . '/wapuuvatar/dist/';

	return array(
		$base_url . $wapuu_img,
		$base_url . $wapuu_img2x,
	);
}

/**
 * Deal with mapping an id_or_email to a hash.
 *
 * Borrows from get_avatar_data() in link-template.php.
 *
 * @param  mixed  $id_or_email  ID / email / hash of the requested avatar.
 *
 * @return string               Hash to use to map the wapuu.
 */
function wapuuvatar_id_or_email_to_hash( $id_or_email ) {

	$email_hash = '';
	$user = $email = false;

	// Process the user identifier.
	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', absint( $id_or_email ) );
	} elseif ( is_string( $id_or_email ) ) {
		if ( strpos( $id_or_email, '@md5.gravatar.com' ) ) {
			// md5 hash
			list( $email_hash ) = explode( '@', $id_or_email );
		} else {
			// email address
			$email = $id_or_email;
		}
	} elseif ( $id_or_email instanceof WP_User ) {
		// User Object
		$user = $id_or_email;
	} elseif ( $id_or_email instanceof WP_Post ) {
		// Post Object
		$user = get_user_by( 'id', (int) $id_or_email->post_author );
	} elseif ( is_object( $id_or_email ) && isset( $id_or_email->comment_ID ) ) {
		// Comment Object
		if ( ! empty( $id_or_email->user_id ) ) {
			$user = get_user_by( 'id', (int) $id_or_email->user_id );
		}
		if ( ( ! $user || is_wp_error( $user ) ) && ! empty( $id_or_email->comment_author_email ) ) {
			$email = $id_or_email->comment_author_email;
		}
	}
	if ( ! $email_hash ) {
		if ( $user ) {
			$email = $user->user_email;
		}
		if ( $email ) {
			$email_hash = md5( strtolower( trim( $email ) ) );
		}
	}
	return $email_hash;
}

function wapuuvatar_get_wapuus() {
	return array(
		'WapuuPepa',
		'WapuuPepe',
		'basile-wapuu',
		'canvas-wapuu',
		'cheesesteak-wapuu',
		'dokuganryu-wapuu',
		'edinburgh-wapuu',
		'fes-wapuu',
		'kani-wapuu',
		'krimpet-wapuu',
		'maikochan-and-wapuu',
		'manchester-wapuu',
		'masuzushi-wapuu',
		'matsuri-wapuu',
		'mineiro-wapuu',
		'onsen-wapuu',
		'original-wapuu',
		'pretzel-wapuu',
		'rocky-wapuu',
		'scott-wapuu',
		'shachihoko-wapuu',
		'shikasenbei-wapuu',
		'sydney-wapuu',
		'takoyaki-wapuu',
		'tampa-gasparilla-wapuu',
		'tonkotsu-wapuu',
		'wapuda-shingen',
		'wapuu-brainhurts',
		'wapuu-cheesehead',
		'wapuu-cosplay',
		'wapuu-der-ber',
		'wapuu-dev',
		'wapuu-france-hd',
		'wapuu-france',
		'wapuu-guitar',
		'wapuu-hampton-roads',
		'wapuu-heropress',
		'wapuu-hipster',
		'wapuu-magic',
		'wapuu-minion',
		'wapuu-moto',
		'wapuu-nyc',
		'wapuu-orange',
		'wapuu-pixar',
		'wapuu-pizza',
		'wapuu-poststatus',
		'wapuu-sleepy-wordcamp',
		'wapuu-snitch',
		'wapuu-spy',
		'wapuu-struggle',
		'wapuu-torque',
		'wapuu-travel',
		'wapuu-tron',
		'wapuu-unipiper',
		'wapuu-wptavern',
		'wapuujlo',
		'wapuunder',
		'wapuushkin-wapuu',
		'wapuutah-wapuu',
		'wck-wapuu',
		'wct2012',
		'wct2013',
		'wctokyo_wapuu',
		'wapevil-wapuu',
		'sweden-wapuu',
		'eduwapuu',
		'wapmas-wapuu',
		'swiss-wapuu',
		'bapuu-wapuu',
		'benpuu',
		'heian-wapuu',
		'london2016-wapuu',
		'mercenary-wapuu',
		'okita-wapuu',
		'r2-wapuu',
		'r2wapuu',
		'shikari-wapuu',
		'sunshinecoast-wapuu',
		'taekwon-blue-wapuu',
		'taekwon-red-wapuu',
		'wapumura-kenshin',
		'wapuu-alaaf',
		'wapuu-ji-chaudhary',
		'wapuu-tiger',
	);
}
