<?php
/* 
Plugin Name: Markdown for P2
Plugin URI: http://wordpress.org/extend/markdown-for-p2
Description: Markdown for P2 will enable Markdown formatting within your P2 theme status updates and comments.
Version: 0.1.1
Author: Ryan Imel
Author URI: http://wpcandy.com
License: GPL 

PHP Markdown & Extra  
Copyright (c) 2004-2009 Michel Fortin  
<http://michelf.com/>  
All rights reserved.

Based on Markdown  
Copyright (c) 2003-2006 John Gruber   
<http://daringfireball.net/>   
All rights reserved.

*/


// Kudos to abackstrom on Github https://gist.github.com/1561020
// http://michelf.com/projects/php-markdown/extra/
require_once( dirname(__FILE__) . '/markdown-extra.php' );


/**
 * Format posts/comments with Markdown at display time. Only process
 * blocks starting with \^md\s+.
 **/
function markdown_for_p2_content_comment_format( $text ) {
	if( ! function_exists('Markdown') ) {
		return $text;
	}

	if( preg_match( '/^\^md\s+/i', $text ) ) {
		$text = preg_replace( '/^\^md\s+/i', '', $text );
		$text = Markdown($text);
	}

	return $text;
}
add_filter( 'comment_text', 'markdown_for_p2_content_comment_format', 1 );
add_filter( 'the_content', 'markdown_for_p2_content_comment_format', 1 );

// Remove the P2 list processing, which converts lists to HTML on save
add_filter( 'p2_add_component_post-list-creator', '__return_false' );
add_filter( 'p2_add_component_comment-list-creator', '__return_false' );


