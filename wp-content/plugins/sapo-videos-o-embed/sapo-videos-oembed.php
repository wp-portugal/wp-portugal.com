<?php
/*
Plugin Name: Sapo Videos oEmbed
Description: Adds oEmbed support for Sapo Videos
Version: 1.2
Author: Zé Fontainhas
Author URI: http://zedejose.com
*/

/**
 * Add Sapo Videos as an oEmbed provider
 *
 * @see https://sapovideos.blogs.sapo.pt/65383.html
 */
function sapo_oembed() {

	wp_oembed_add_provider( 'http://videos.sapo.pt/*', 'http://videos.sapo.pt/oembed' );
}

add_action( 'init', 'sapo_oembed' );
