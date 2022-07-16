<?php
/*
Plugin Name: Sapo Videos oEmbed
Description: Adds oEmbed support for Sapo Videos
Version: 1.2.1
Author: Zé Fontainhas
Author URI: http://zedejose.com
Requires at least: 3.0
Tested up to: 5.9.3
Stable tag: 1.2.1
License: GPL2
Copyright 2013 Zé Fontainhas
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
 * Add Sapo Videos as an oEmbed provider
 *
 * @see https://sapovideos.blogs.sapo.pt/65383.html
 */
function sapo_oembed() {

	wp_oembed_add_provider( 'http://videos.sapo.pt/*', 'http://videos.sapo.pt/oembed' );
}

add_action( 'init', 'sapo_oembed' );
