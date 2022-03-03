<?php
/**
 * Plugin Name: WordPress Portugal Admin Widget
 * Plugin URI: http://vanilla-lounge.pt
 * Description: Adds a widget to your dashboard screen, with the latest news from http://wp-portugal.com/
 * Contributors: vanillalounge
 * Author: vanillalounge
 * Author URI: http://wordpress.org/support/profile/vanillalounge
 * Tags: dashboard, widget, rss, Portugal
 * Requires at least: 3.0
 * Tested up to: 3.5.1
 * Stable tag: 1.0.1
 * Version: 1.0.1
 * License: GPL2
 * Copyright 2013 vanillalounge (email : info AT vanilla-lounge DOT pt)
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * The plugin base class - the root of it all
 *
 * @author vanillalounge
 *
 */
class VL_WPPTAW_Plugin_Base {
	/**
	 * Assign everything as a call from within the constructor
	 */
	function __construct() {
		$this->add_rss_panel();
	}

	/**
	 * Hook into wp_dashboard_setup to add the widget
	 *
	 * @since 1.0
	 * 
	 */
	function add_rss_panel() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_rss_panel_callback' ) );
	}

	/**
	 * Add the actual widget
	 *
	 * @since 1.0
	 * 
	 */
	function add_rss_panel_callback() {
		wp_add_dashboard_widget( 'wpptrss_news_list', 'Notícias WordPress Portugal', array( $this, 'add_rss_widget' ) );

	}

	/**
	 * Widget display code
	 *
	 * @since 1.0
	 * 
	 */
	function add_rss_widget() {

		$rss = fetch_feed( 'http://wp-portugal.com/feed/' );
		if ( is_wp_error( $rss ) ) : // Checks that the object is created correctly
			echo '<div class="rss-widget"><p>';
		printf( '<strong>Erro RSS</strong>: %s', $rss->get_error_message() );
		echo '</p></div>';
		return;
		endif;

		echo '<div class="rss-widget">';
		echo '<ul>';

		$maxitems = $rss->get_item_quantity( 5 );
		$rss_items = $rss->get_items( 0, $maxitems );

		if ( $maxitems == 0 )
			echo '<li>Nenhuma entrada.</li>';
		else
			foreach ( $rss_items as $item ) :

				$desc = str_replace( array( "\n", "\r" ), ' ', esc_attr( strip_tags( @html_entity_decode( $item->get_description(), ENT_QUOTES, get_option( 'blog_charset' ) ) ) ) );
			$desc = wp_html_excerpt( $desc, 360 );

		// Append ellipsis. Change existing [...] to [&hellip;].
		if ( '[...]' == substr( $desc, -5 ) )
			$desc = substr( $desc, 0, -5 ) . '[&hellip;]';
		elseif ( '[&hellip;]' != substr( $desc, -10 ) )
			$desc .= ' [&hellip;]';

		$desc = esc_html( $desc );

		echo '<li>';
		// feed title
		echo '<a class="rsswidget" target="_blank" href="'.esc_url ( $item->get_permalink() ).'" title="Posted '.$item->get_date( 'j F Y | g:i a' ).'">'.esc_html( $item->get_title() ).'</a>';
		echo '<span class="rss-date">'.$item->get_date( 'F j, Y' ).'</span>';
		// feed summary
		echo '<div class="rssSummary">';
		echo $desc;
		echo '</div>';
		echo '</li>';
		endforeach;

		echo '</ul>';
		echo '</div>';

	}


}

// Initialize everything
$wpptaw_plugin_base = new VL_WPPTAW_Plugin_Base();
