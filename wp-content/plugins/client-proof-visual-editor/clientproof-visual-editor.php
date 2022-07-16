<?php
/**
 * Client-Proof Visual Editor
 *
 * @package   Clientproof_Visual_Editor
 * @author    Hugo Baeta <hugo@baeta.me>
 * @copyright 2019 Hugo Baeta
 * @license   GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Client-Proof Visual Editor
 * Plugin URI:        https://wordpress.org/plugins/client-proof-visual-editor/
 * Description:       Simple, option-less, plugin to make TinyMCE easier for clients and n00bs.
 * Version:           1.7.1
 * Requires at least: 3.0
 * Requires PHP:      5.6
 * Author:            Hugo Baeta
 * Author URI:        http://hugobaeta.com
 */

/**
 * Filter TinyMCE settings
 *
 * @param array $settings Array of TinyMCE settings.
 *
 * @return array           New settings array.
 */
function clientproof_visual_editor( $settings ) {
	// What goes into the 'formatselect' list.
	$settings['block_formats'] = 'Header 2=h2;Header 3=h3;Header 4=h4;Paragraph=p;Code=code';

	// What goes into the toolbars. Add 'wp_adv' to get the Toolbar toggle button back.
	$settings['toolbar1'] = 'bold,italic,strikethrough,formatselect,bullist,numlist,blockquote,link,unlink,hr,wp_more,fullscreen';
	$settings['toolbar2'] = '';
	$settings['toolbar3'] = '';
	$settings['toolbar4'] = '';

	// Clear most formatting when pasting text directly in the editor.
	$settings['paste_as_text'] = 'true';

	return $settings;
}

add_filter( 'tiny_mce_before_init', 'clientproof_visual_editor' );
