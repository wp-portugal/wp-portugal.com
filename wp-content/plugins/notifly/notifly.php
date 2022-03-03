<?php
/*
Plugin Name: Notifly
Plugin URI: http://wordpress.org/extend/plugins/notifly/
Description: Sends an email to the addresses of your choice when a new post or comment is made. Add email addresses in your Discussion Settings area.
Author: Otto42, Matt, John James Jacoby
Version: 1.4
Author URI: http://ottodestruct.com
*/

if ( !class_exists( 'Notifly' ) ) :
/**
 * Notifly
 *
 * @package Notifly
 *
 * The most fly way to send blog updates
 */
class Notifly {

	// Current blog name
	var $blogname;

	// Notifly recipients
	var $recipients;

	/**
	 * Notifly Initializer
	 */
	function notifly() {
		$this->blogname   = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$this->recipients = $this->get_recipients();

		// Let's get this party started quickly...
		add_action( 'init',                       array( $this, 'load_textdomain' )            );

		// Admin area
		add_action( 'admin_init',                 array( $this, 'discussion_settings_loader' ) );
		add_action( 'admin_notices',              array( $this, 'activation_notice' )          );
		add_filter( 'plugin_action_links',        array( $this, 'add_settings_link' ),   10, 2 );

		// Attaches email functions to WordPress actions
		add_action( 'comment_post',               array( $this, 'comment_email' ),       10, 2 );
		//add_action( 'wp_set_comment_status',      array( $this, 'comment_email' ),       10, 2 );
		add_action( 'transition_comment_status',  array( $this, 'transition_comment' ),  10, 3 );
		add_action( 'transition_post_status',     array( $this, 'post_email' ),          10, 3 );

		// Notifly text blocks
		add_filter( 'notifly_text', array( $this, 'filter_html' ), 8 );
		add_filter( 'notifly_text', 'make_clickable', 9 );
		add_filter( 'notifly_text', 'wptexturize' );
		add_filter( 'notifly_text', 'convert_chars' );
		add_filter( 'notifly_text', 'force_balance_tags', 25 );
		add_filter( 'notifly_text', 'convert_smilies', 20 );
		add_filter( 'notifly_text', 'wpautop', 30 );

		// Register activation sequence
		register_activation_hook( __FILE__, array( $this, 'activation' ) );
	}

	/**
	 * activation()
	 *
	 * Block duplicate emails by default when activated if no previous settings exist
	 */
	function activation() {
		if ( !get_option( 'pce_email_moderator' ) )
			update_option( 'pce_email_moderator', '1' );

		if ( !get_option( 'pce_email_post_author' ) )
			update_option( 'pce_email_post_author', '1' );
	}

	/**
	 * activation_notice()
	 *
	 * Admin area ctivation notice. Only appears when there are no addresses.
	 */
	function activation_notice() {
		$email_addresses = get_option( 'pce_email_addresses' );

		if ( empty( $email_addresses ) ) { ?>

			<div id="message" class="updated">
				<p><?php printf( __( '<strong>Notifly is almost ready.</strong> Go <a href="%s">add some email addresses</a> to keep people in the loop.', 'notifly' ), admin_url( 'options-discussion.php' ) . '#pce_email_addresses' ) ?></p>
			</div>

		<?php }
	}

	/**
	 * add_settings_link( $links, $file )
	 *
	 * Add Settings link to plugins area
	 *
	 * @return string Links
	 */
	function add_settings_link( $links, $file ) {
		if ( plugin_basename( __FILE__ ) == $file ) {
			$settings_link = '<a href="' . admin_url( 'options-discussion.php' ) . '#pce_email_addresses">' . __( 'Settings', 'notifly' ) . '</a>';
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	/**
	 * load_textdomain()
	 *
	 * Load the translation file for current language
	 */
	function load_textdomain() {
		$locale = apply_filters( 'notifly_locale', get_locale() );
		$mofile = WP_PLUGIN_DIR . '/notifly/languages/notifly-' . $locale . '.mo';

		if ( file_exists( $mofile ) )
			load_textdomain( 'notifly', $mofile );
	}

	/**
	 * discussion_settings_loader()
	 *
	 * Sets up the settings section in the Discussion admin screen
	 *
	 * @uses add_settings_section
	 */
	function discussion_settings_loader() {
		// Add the section to Discussion options
		add_settings_section( 'pce_options', __( 'Notifly', 'notifly' ), array( $this, 'section_heading' ), 'discussion' );

		// Add the field
		add_settings_field( 'pce_email_addresses', __( 'Email Addresses', 'notifly' ), array( $this, 'email_addresses_textarea' ), 'discussion', 'pce_options' );

		// Add the field
		add_settings_field( 'pce_email_moderator', __( 'Site Administrator', 'notifly' ), array( $this, 'email_moderator' ), 'discussion', 'pce_options' );

		// Add the field
		add_settings_field( 'pce_email_post_author', __( 'Post Author', 'notifly' ), array( $this, 'email_post_author' ), 'discussion', 'pce_options' );

		// Register our settings with the discussions page
		register_setting( 'discussion', 'pce_email_addresses', array( $this, 'validate_email_addresses' ) );
		register_setting( 'discussion', 'pce_email_post_author', array( $this, 'validate_author' ) );
		register_setting( 'discussion', 'pce_email_moderator', array( $this, 'validate_author' ) );
	}

	/**
	 * section_heading()
	 *
	 * Output the email address notification section in the Discussion admin screen
	 *
	 */
	function section_heading() {
		_e( 'Email addresses of people to notifly when new posts and comments are published. One per line.', 'notifly' );
	}

	/**
	 * email_addresses_textarea()
	 *
	 * Output the textarea of email addresses
	 */
	function email_addresses_textarea() {
		$pce_email_addresses = get_option( 'pce_email_addresses' );
		$pce_email_addresses = str_replace( ' ', "\n", $pce_email_addresses );

		echo '<textarea class="large-text" id="pce_email_addresses" cols="50" rows="10" name="pce_email_addresses">' . $pce_email_addresses . '</textarea>';
	}

	/**
	 * email_moderator()
	 *
	 * Output the checkbox of email addresses
	 */
	function email_moderator() {
		$pce_email_moderator = get_option( 'pce_email_moderator' ); ?>

		<label for="pce_email_moderator">
			<input name="pce_email_moderator" type="checkbox" id="pce_email_moderator" <?php checked( $pce_email_moderator ); ?>>
			<?php _e( 'Block WordPress Emails when the site administrator is included above and approves a comment.', 'notifly' ); ?>
		</label>

		<?php
	}

	/**
	 * email_post_author()
	 *
	 * Output the checkbox of email addresses
	 */
	function email_post_author() {
		$pce_post_author = get_option( 'pce_email_post_author' ); ?>

		<label for="pce_email_post_author">
			<input name="pce_email_post_author" type="checkbox" id="pce_email_post_author" <?php checked( $pce_post_author ); ?>>
			<?php _e( 'Block WordPress Emails if the post author is included above.', 'notifly' ); ?>
		</label>

		<?php
	}

	/**
	 * validate_email_addresses( $email_addresses )
	 *
	 * Returns validated results
	 *
	 * @param string $email_addresses
	 * @return string
	 *
	 */
	function validate_email_addresses( $email_addresses ) {

		// Make array out of textarea lines
		$valid_addresses = '';
		$recipients      = str_replace( ' ', "\n", $email_addresses );
		$recipients      = explode( "\n", $recipients );

		// Check validity of each address
		foreach ( $recipients as $recipient ) {
			if ( is_email( trim( $recipient ) ) )
				$valid_addresses .= $recipient . "\n";
		}

		// Trim off extra whitespace
		$valid_addresses = trim( $valid_addresses );

		// Return valid addresses
		return apply_filters( 'notifly_validate_email_addresses', $valid_addresses );
	}

	/**
	 * validate_author( $author )
	 *
	 * Returns true/false if author is checked
	 *
	 * @param string $author
	 * @return boolean
	 */
	function validate_author( $author ) {
		if ( 'on' == $author )
			$retval = true;
		else
			$retval = false;

		return $retval;
	}

	/**
	 * transition_comment( $new, $old, $comment )
	 *
	 * Send an email to all users when a new post is created
	 *
	 * @param string $new
	 * @param string $old
	 * @param object $post
	 * @return Return empty if post is being updated or not published
	 */
	function transition_comment( $new, $old, $comment ) {
		global $pce_comment_transition;

		if ( 'approved' != $new || 'approved' == $old )
			return false;

		$pce_comment_transition = true;

		$this->comment_email( isset( $comment->ID ) ? $comment->ID : $comment->comment_ID, 'approve' );
	}

	/**
	 * comment_email( $comment_id, $comment_status )
	 *
	 * Send an email to all users when a comment is made
	 *
	 * @param int $comment_id
	 * @param string $comment_status
	 * @return Return empty if comment is not published
	 */
	function comment_email( $comment_id, $comment_status ) {
		global $pce_comment_transition;

		// Only send emails for actual published comments
		if ( empty( $comment_status ) || !in_array( $comment_status, array( '1', 'approve' ) ) )
			return false;

		// Comment data
		$comment                   = get_comment( $comment_id );
		$post                      = get_post( $comment->comment_post_ID );
		$post_type_obj             = get_post_type_object( $post->post_type );
		$post_author               = get_userdata( $post->post_author );
		$comment_author_domain     = gethostbyaddr( $comment->comment_author_IP );

		$trigger = true;
		if ( ! $post_type_obj->public )
			$trigger = false;
		if ( ! apply_filters( 'trigger_notifly_comment', $trigger, $comment, $post ) )
			return;

		// Prevent Notifly email to site admins and/or comment author
		if ( true === $pce_comment_transition ) {
			if ( get_option( 'pce_email_moderator' ) && ( get_option( 'comments_notify' ) || get_option( 'comments_moderation' ) ) )
				$blocked_recipients = array( get_option( 'admin_email' ), $comment->comment_author_email );
			else
				$blocked_recipients = $comment->comment_author_email;
		} else {
			$blocked_recipients = $comment->comment_author_email;
		}

		// Content details
		$message['permalink']      = get_permalink( $comment->comment_post_ID ) . '#comment-' . $comment_id;
		$message['shortlink']      = wp_get_shortlink( $post->ID, 'post' ) . '#comment-' . $comment_id;
		$message['timestamp']      = sprintf( __( '%1$s at %2$s', 'notifly' ), get_post_time( 'F j, Y', false, $post ), get_post_time( 'g:i a', false, $post ) );
		$message['title']          = $post->post_title;
		$message['author_name']    = $comment->comment_author;
		$message['author_link']    = $comment->comment_author_url;
		$message['author_avatar']  = $this->get_avatar( $comment->comment_author_email );
		$message['content']        = $comment->comment_content;

		// Comment Extras
		$message['comment_author'] = sprintf( __( 'Author : %1$s (IP: %2$s , %3$s)', 'notifly' ), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain );
		$message['comment_whois']  = sprintf( __( 'Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s', 'notifly' ), $comment->comment_author_IP );

		// Create the email
		$email['subject']          = sprintf( __( '[%1$s] Comment: "%2$s"', 'notifly' ), $this->blogname, $post->post_title );
		$email['recipients']       = $this->get_recipients( $blocked_recipients );
		$email['body']             = $this->get_html_email_template( 'post', $message );
		$email['headers']          = $this->get_email_headers( sprintf( 'Reply-To: %1$s <%2$s>', __( 'No Reply', 'notifly' ), $this->no_reply_to() ) );

		// Send email to each user
		foreach ( (array)$email['recipients'] as $recipient )
			@wp_mail( $recipient, $email['subject'], $email['body'], $email['headers'] );

	}

	/**
	 * post_email( $new, $old, $post )
	 *
	 * Send an email to all users when a new post is created
	 *
	 * @param string $new
	 * @param string $old
	 * @param object $post
	 * @return Return empty if post is being updated or not published
	 */
	function post_email( $new, $old, $post ) {
		// Don't send emails on updates
		if ( 'publish' != $new || 'publish' == $old )
			return;

		$author = get_userdata( $post->post_author );
		$post_type_obj = get_post_type_object( $post->post_type );
		$post_type_name = $post_type_obj->labels->singular_name;

		$trigger = true;
		if ( ! $post_type_obj->public )
			$trigger = false;
		if ( ! apply_filters( 'trigger_notifly_post', $trigger, $post ) )
			return;

		// Content details
		$message['permalink']     = get_permalink( $post->ID );
		$message['shortlink']     = wp_get_shortlink( $post->ID, 'post' );
		$message['timestamp']     = sprintf( __( '%1$s at %2$s', 'notifly' ), get_post_time( 'F j, Y', false, $post ), get_post_time( 'g:i a', false, $post ) );
		$message['title']         = $post->post_title;
		$message['content']       = $post->post_content;
		$message['tags']          = wp_get_post_tags( $post->ID );
		$message['categories']    = wp_get_post_categories( $post->ID );

		// Post author
		$message['author_name']   = $author->user_nicename;
		$message['author_link']   = get_author_posts_url( $author->ID );
		$message['author_avatar'] = $this->get_avatar( $author->user_email );

		// Create the email
		$email['subject']         = sprintf( __( '[%1$s] %2$s: "%3$s" by %4$s' ), $this->blogname, $post_type_name, $post->post_title, $author->user_nicename );
		$email['recipients']      = $this->get_recipients( $author->user_email );
		$email['body']            = $this->get_html_email_template( 'post', $message );
		$email['headers']         = $this->get_email_headers( sprintf( 'Reply-To: %1$s <%2$s>', __( 'No Reply', 'notifly' ), $this->no_reply_to() ) );

		// Send email to each user
		foreach ( $email['recipients'] as $recipient )
			@wp_mail( $recipient, $email['subject'], $email['body'], $email['headers'] );
	}

	/**
	 * no_reply_to()
	 *
	 * Hide comment author email address from going out to everyone
	 *
	 * @return string
	 */
	function no_reply_to() {
		return apply_filters( 'notifly_no_reply_to', 'noreply@' . preg_replace( '#^www\.#', '', strtolower( $_SERVER['SERVER_NAME'] ) ) );
	}

	/**
	 * email_from()
	 *
	 * Returns the email address shown as the sender
	 *
	 * @return string
	 */
	function email_from() {
		return apply_filters( 'notifly_email_from', 'wordpress@' . preg_replace( '#^www\.#', '', strtolower( $_SERVER['SERVER_NAME'] ) ) );
	}

	/**
	 * email_headers
	 *
	 * Returns formatted HTML email headers
	 *
	 * @param string $blogname
	 * @param string $reply_to
	 * @return string
	 */
	function get_email_headers( $reply_to ) {
		$headers['from']     = sprintf( 'From: %1$s <%2$s>', $this->blogname, $this->email_from() );
		$headers['mime']     = 'MIME-Version: 1.0';
		$headers['type']     = 'Content-Type: text/html; charset="' . get_option( 'blog_charset' ) . '"';
		$headers['reply_to'] = $reply_to;

		return implode( "\n", $headers );
	}

	/**
	 * get_recipients( $skip_addresses = '' )
	 *
	 * Gets the recipients
	 *
	 * @param array $skip_addresses optional Users to remove from notifications
	 * @return array
	 */
	function get_recipients( $skip_addresses = '' ) {
		// Get recipients and turn into an array
		$recipients = get_option( 'pce_email_addresses' );
		$recipients = str_replace( ' ', "\n", $recipients );
		$recipients = explode( "\n", $recipients );

		// Loop through recipients and remove duplicates if any were passed
		if ( !empty( $skip_addresses ) ) {
			foreach ( (array)$skip_addresses as $address ) {
				foreach ( $recipients as $key => $recipient ) {
					if ( trim ( $address ) === trim( $recipient ) ) {
						unset( $recipients[$key] );
					}
				}
			}
		}

		$recipients = array_values( $recipients );

		// Return result
		return apply_filters( 'notifly_get_recipients', $recipients );
	}

	/**
	 * Runs HTML filtering over content to allow some HTML into emails.
	 *
	 * Uses the $allowedtags global, used for comments, and appends the
	 * ul, li, and ol tags.
	 *
	 * @since 1.4
	 *
	 * @param string $content
	 * @return string
	 */
	function filter_html( $content ) {
		global $allowedtags;
		$tags = array_merge( $allowedtags, array( 'ul' => array(), 'li' => array(), 'ol' => array() ) );
		return wp_kses( $content, $tags );
	}

	/**
	 * get_html_email_template()
	 *
	 * Template used for Notifly emails.
	 * Hijacked from WordPress.com blog subscriptions.
	 *
	 * @param string $type post|comment
	 * @param array $args Arguments to fill HTML email with
	 * @return string
	 */
	function get_html_email_template( $type, $args ) {
		// Build the post meta
		$meta = '| ' . $args['timestamp'];
		if ( isset( $args['tags'] ) && !empty( $args['tags'] ) ) {
			//$meta .= ' | ' . __( 'Tags:', 'notifly' );
			//<a href="" style="text-decoration: none; color: #0088cc;"></a>
		}

		if ( isset( $args['categories'] ) && !empty( $args['categories'] ) ) {
			//$meta .= ' | ' . __( 'Categories:', 'notifly' );
			//<a href="" style="text-decoration: none; color: #0088cc;"></a>
		}

		$meta .= ' | ' . __( 'Short Link:', 'notifly' ) . ' <a href="' . $args['shortlink'] . '" style="text-decoration: none; color: #0088cc;">' . $args['shortlink'] . '</a>';

		$args['content'] = apply_filters( 'notifly_text', $args['content'] );

		// Build the email pieces
		$email['doctype'] = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
		$email['html']    = '<html xmlns="http://www.w3.org/1999/xhtml">';
		$email['head']    = '<head>
			<style type="text/css" media="all">
			a:hover { color: red; }
			a {
				text-decoration: none;
				color: #0088cc;
			}
			/*
			@media only screen and (max-device-width: 480px) {
				 .post { min-width: 700px !important; }
			}
			*/
			</style>
			<title><?php // blog title ?></title>
			<!--[if gte mso 12]>
			<style type="text/css" media="all">
			body {
			font-family: arial;
			font-size: 0.8em;
			-webkit-text-size-adjust: none;
			}
			.post, .comment {
			background-color: white !important;
			line-height: 1.4em !important;
			}
			</style>
			<![endif]-->
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		</head>';

		// Type of notification determines the body
		if ( 'post' == $type ) {

			// Email Body
			$email['body'] = '<body>
			<div style="max-width: 1024px;" class="content">
				<div style="padding: 1em; margin: 1.5em 1em 0.5em 1em; background-color: #f5f5f5; border: 1px solid #ccc; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; line-height: 1.6em;" class="post">
					<table style="width: 100%;" class="post-details">
						<tr>
							<!-- Author Avatar -->
							<td valign="top"  style="width: 48px; margin-right: 7px;" class="table-avatar">
								<a href="' . $args['author_link'] . '" style="text-decoration: none; color: #0088cc;">
									' . $args['author_avatar'] . '
								</a>
							</td>

							<td valign="top">
								<!-- Post Title -->
								<h2 style="margin: 0; font-size: 1.6em; color: #555;" class="post-title">
									<a href="' . $args['permalink'] . '" style="text-decoration: none; color: #0088cc;">' . $args['title'] . '</a>
								</h2>

								<!-- Author Info -->
								<div style="color: #999; font-size: 0.9em; margin-top: 4px;" class="meta">
									<strong>
										<a href="' . $args['author_link'] . '" style="text-decoration: none; color: #0088cc;">' . $args['author_name'] . '</a>
									</strong>
									<!-- Post Meta -->
									' . $meta . '
								</div>
							</td>
						</tr>
					</table>

					<!-- Post -->
					' . $args['content'] . '
					<div style="clear: both"></div>

					<!-- Reply -->
					<p style="margin-bottom: 0.4em">
						<a href="' . $args['permalink'] . '#respond" style="text-decoration: none; color: #0088cc;">' . __( 'Add a comment to this post', 'notifly' ) . '</a>
					</p>
				</div>
			</div>

			<!-- WordPress Logo -->
			<table style="max-width: 1024px; line-height: 1.6em;" class="footer">
				<tr>
					<td width="80">
						<img border="0" src="http://s.wordpress.org/about/images/logo-grey/grey-m.png" alt="WordPress" width="64" height="64" />
					</td>
					<td>
						' . sprintf( __( 'Don\'t just fly... <a href="%s">Notifly</a>', 'notifly' ), 'http://wordpress.org/extend/plugins/notifly/' ) . '
					</td>
				</tr>
			</table>
		</body>';

		// @todo Comment Notification
		} elseif ( 'comment' == $type ) {

		}

		$email['bottom'] = '</html>';

		return implode( "\n", $email );
	}

	/**
	 * get_avatar( $email = '' )
	 *
	 * Custom get_avatar function with inline styling
	 *
	 * @param string $email optional
	 * @return string <img>
	 */
	function get_avatar( $email = '' ) {

		// Set avatar size
		$size = '48';

		// Safe alternate text
		$safe_alt = '';

		// Get site default avatar
		$avatar_default = get_option( 'avatar_default' );
		if ( empty( $avatar_default ) )
			$default = 'mystery';
		else
			$default = $avatar_default;

		$email_hash = md5( strtolower( $email ) );

		// SSL?
		if ( is_ssl() ) {
			$host = 'https://secure.gravatar.com';
		} else {
			if ( !empty( $email ) ) {
				$host = sprintf( "http://%d.gravatar.com", ( hexdec( $email_hash{0} ) % 2 ) );
			} else {
				$host = 'http://0.gravatar.com';
			}
		}

		// Get avatar/gravatar
		if ( 'mystery' == $default )
			$default = "$host/avatar/ad516503a11cd5ca435acc9bb6523536?s={$size}"; // ad516503a11cd5ca435acc9bb6523536 == md5('unknown@gravatar.com')
		elseif ( 'blank' == $default )
			$default = includes_url( 'images/blank.gif' );
		elseif ( !empty( $email ) && 'gravatar_default' == $default )
			$default = '';
		elseif ( 'gravatar_default' == $default )
			$default = "$host/avatar/s={$size}";
		elseif ( empty( $email ) )
			$default = "$host/avatar/?d=$default&amp;s={$size}";
		elseif ( strpos( $default, 'http://' ) === 0 )
			$default = add_query_arg( 's', $size, $default );

		if ( !empty( $email ) ) {
			$out = "$host/avatar/";
			$out .= $email_hash;
			$out .= '?s='.$size;
			$out .= '&amp;d=' . urlencode( $default );

			$rating = get_option('avatar_rating');
			if ( !empty( $rating ) )
				$out .= "&amp;r={$rating}";

			$avatar = '<img alt="' . $safe_alt . '" src="' . $out . '" style="border: 1px solid #ddd; padding: 2px; background-color: white; width: 48px; margin-right: 7px;" class="avatar avatar-' . $size . ' photo" height="' . $size . '" width="' . $size . '" />';
		} else {
			$avatar = '<img alt="' . $safe_alt . '" src="' . $default . '" style="border: 1px solid #ddd; padding: 2px; background-color: white; width: 48px; margin-right: 7px;" class="avatar avatar-default avatar-' . $size . ' photo" height="' . $size . '" width="' . $size . '" />';
		}

		return apply_filters( 'notifly_get_avatar', $avatar, $email, $size, $default );
	}
}
endif;

// Set it off
$notifly = new Notifly();

if ( !function_exists( 'wp_notify_postauthor' ) ) :
/**
 * Notify an author of a comment/trackback/pingback to one of their posts.
 * Normally found in WordPress core, this pluggable function is slightly customized for Notifly.
 *
 * @since 1.0.0
 *
 * @param int $comment_id Comment ID
 * @param string $comment_type Optional. The comment type either 'comment' (default), 'trackback', or 'pingback'
 * @return bool False if user email does not exist. True on completion.
 */
function wp_notify_postauthor( $comment_id, $comment_type = '' ) {
	global $notifly;

	$comment = get_comment( $comment_id );
	$post    = get_post( $comment->comment_post_ID );
	$user    = get_userdata( $post->post_author );

	// The author moderated a comment on his own post
	if ( $comment->user_id == $post->post_author ) return false;

	// If there's no email to send the comment to
	if ( empty( $user->user_email ) ) return false;

	// Check if post author is alraedy on the Notifly list
	if ( get_option( 'pce_email_post_author' ) ) {
		foreach ( $notifly->recipients as $recipient ) {
			if ( trim( $user->user_email ) == trim( $recipient ) ) return false;
		}
	}

	$comment_author_domain = @gethostbyaddr( $comment->comment_author_IP );

	// The blogname option is escaped with esc_html on the way into the database in sanitize_option
	// we want to reverse this for the plain text area of emails.
	$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

	if ( empty( $comment_type ) ) $comment_type = 'comment';

	if ( 'comment' == $comment_type ) {
		$notify_message  = sprintf( __( 'New comment on your post "%s"' ), $post->post_title ) . "\r\n";
		/* translators: 1: comment author, 2: author IP, 3: author domain */
		$notify_message .= sprintf( __( 'Author : %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
		$notify_message .= sprintf( __( 'E-mail : %s'), $comment->comment_author_email ) . "\r\n";
		$notify_message .= sprintf( __( 'URL    : %s'), $comment->comment_author_url ) . "\r\n";
		$notify_message .= sprintf( __( 'Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s'), $comment->comment_author_IP ) . "\r\n";
		$notify_message .= __( 'Comment: ') . "\r\n" . $comment->comment_content . "\r\n\r\n";
		$notify_message .= __( 'You can see all comments on this post here: ') . "\r\n";
		/* translators: 1: blog name, 2: post title */
		$subject = sprintf( __( '[%1$s] Comment: "%2$s"'), $blogname, $post->post_title );
	} elseif ( 'trackback' == $comment_type ) {
		$notify_message  = sprintf( __( 'New trackback on your post "%s"' ), $post->post_title ) . "\r\n";
		/* translators: 1: website name, 2: author IP, 3: author domain */
		$notify_message .= sprintf( __( 'Website: %1$s (IP: %2$s , %3$s)' ), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
		$notify_message .= sprintf( __( 'URL    : %s'), $comment->comment_author_url ) . "\r\n";
		$notify_message .= __( 'Excerpt: ') . "\r\n" . $comment->comment_content . "\r\n\r\n";
		$notify_message .= __( 'You can see all trackbacks on this post here: ' ) . "\r\n";
		/* translators: 1: blog name, 2: post title */
		$subject = sprintf( __( '[%1$s] Trackback: "%2$s"' ), $blogname, $post->post_title );
	} elseif ( 'pingback' == $comment_type ) {
		$notify_message  = sprintf( __( 'New pingback on your post "%s"' ), $post->post_title ) . "\r\n";
		/* translators: 1: comment author, 2: author IP, 3: author domain */
		$notify_message .= sprintf( __( 'Website: %1$s (IP: %2$s , %3$s)' ), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
		$notify_message .= sprintf( __( 'URL    : %s' ), $comment->comment_author_url ) . "\r\n";
		$notify_message .= __( 'Excerpt: ' ) . "\r\n" . sprintf( '[...] %s [...]', $comment->comment_content ) . "\r\n\r\n";
		$notify_message .= __( 'You can see all pingbacks on this post here: ' ) . "\r\n";
		/* translators: 1: blog name, 2: post title */
		$subject = sprintf( __( '[%1$s] Pingback: "%2$s"' ), $blogname, $post->post_title );
	}

	$notify_message .= get_permalink($comment->comment_post_ID) . "#comments\r\n\r\n";

	if ( EMPTY_TRASH_DAYS )
		$notify_message .= sprintf( __( 'Trash it: %s' ), admin_url( "comment.php?action=trash&c=$comment_id" ) ) . "\r\n";
	else
		$notify_message .= sprintf( __( 'Delete it: %s'), admin_url("comment.php?action=delete&c=$comment_id" ) ) . "\r\n";

	$notify_message .= sprintf( __( 'Spam it: %s' ), admin_url( "comment.php?action=spam&c=$comment_id" ) ) . "\r\n";

	$wp_email = 'wordpress@' . preg_replace( '#^www\.#', '', strtolower( $_SERVER['SERVER_NAME'] ) );

	if ( empty( $comment->comment_author ) ) {
		$from = "From: \"$blogname\" <$wp_email>";
		if ( !empty( $comment->comment_author_email ) )
			$reply_to = "Reply-To: $comment->comment_author_email";
	} else {
		$from = "From: \"$comment->comment_author\" <$wp_email>";
		if ( !empty( $comment->comment_author_email ) )
			$reply_to = "Reply-To: \"$comment->comment_author_email\" <$comment->comment_author_email>";
	}

	$message_headers = "$from\n"
		. "Content-Type: text/plain; charset=\"" . get_option( 'blog_charset' ) . "\"\n";

	if ( isset( $reply_to ) )
		$message_headers .= $reply_to . "\n";

	$notify_message  = apply_filters( 'comment_notification_text', $notify_message, $comment_id );
	$subject         = apply_filters( 'comment_notification_subject', $subject, $comment_id );
	$message_headers = apply_filters( 'comment_notification_headers', $message_headers, $comment_id );

	@wp_mail( $user->user_email, $subject, $notify_message, $message_headers );

	return true;
}
endif;

if ( !function_exists( 'wp_notify_moderator' ) ) :
/**
 * Notifies the moderator of the blog about a new comment that is awaiting approval.
 * Normally found in WordPress core, this pluggable function is slightly customized for Notifly.
 *
 * @since 1.1.7
 * @uses $wpdb
 *
 * @param int $comment_id Comment ID
 * @return bool Always returns true
 */
function wp_notify_moderator( $comment_id ) {
	global $wpdb, $notifly;

	if ( 0 == get_option( 'moderation_notify' ) )
		return true;

	$admin_email = get_option( 'admin_email' );

	$comment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->comments WHERE comment_ID=%d LIMIT 1", $comment_id ) );
	$post    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID=%d LIMIT 1", $comment->comment_post_ID ) );

	$comment_author_domain = @gethostbyaddr( $comment->comment_author_IP);
	$comments_waiting      = $wpdb->get_var( "SELECT count(comment_ID) FROM $wpdb->comments WHERE comment_approved = '0'" );

	// The blogname option is escaped with esc_html on the way into the database in sanitize_option
	// we want to reverse this for the plain text area of emails.
	$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

	switch ( $comment->comment_type ) {
		case 'trackback':
			$notify_message  = sprintf( __( 'A new trackback on the post "%s" is waiting for your approval' ), $post->post_title ) . "\r\n";
			$notify_message .= get_permalink($comment->comment_post_ID) . "\r\n\r\n";
			$notify_message .= sprintf( __( 'Website : %1$s (IP: %2$s , %3$s)' ), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
			$notify_message .= sprintf( __( 'URL    : %s' ), $comment->comment_author_url ) . "\r\n";
			$notify_message .= __( 'Trackback excerpt: ' ) . "\r\n" . $comment->comment_content . "\r\n\r\n";
			break;
		case 'pingback':
			$notify_message  = sprintf( __( 'A new pingback on the post "%s" is waiting for your approval' ), $post->post_title ) . "\r\n";
			$notify_message .= get_permalink($comment->comment_post_ID) . "\r\n\r\n";
			$notify_message .= sprintf( __( 'Website : %1$s (IP: %2$s , %3$s)' ), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
			$notify_message .= sprintf( __( 'URL    : %s' ), $comment->comment_author_url ) . "\r\n";
			$notify_message .= __( 'Pingback excerpt: ' ) . "\r\n" . $comment->comment_content . "\r\n\r\n";
			break;
		default: //Comments
			$notify_message  = sprintf( __( 'A new comment on the post "%s" is waiting for your approval' ), $post->post_title ) . "\r\n";
			$notify_message .= get_permalink($comment->comment_post_ID) . "\r\n\r\n";
			$notify_message .= sprintf( __( 'Author : %1$s (IP: %2$s , %3$s)' ), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
			$notify_message .= sprintf( __( 'E-mail : %s' ), $comment->comment_author_email ) . "\r\n";
			$notify_message .= sprintf( __( 'URL    : %s' ), $comment->comment_author_url ) . "\r\n";
			$notify_message .= sprintf( __( 'Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s' ), $comment->comment_author_IP ) . "\r\n";
			$notify_message .= __( 'Comment: ' ) . "\r\n" . $comment->comment_content . "\r\n\r\n";
			break;
	}

	$notify_message .= sprintf( __('Approve it: %s'),  admin_url("comment.php?action=approve&c=$comment_id") ) . "\r\n";

	if ( EMPTY_TRASH_DAYS )
		$notify_message .= sprintf( __('Trash it: %s'), admin_url("comment.php?action=trash&c=$comment_id") ) . "\r\n";
	else
		$notify_message .= sprintf( __('Delete it: %s'), admin_url("comment.php?action=delete&c=$comment_id") ) . "\r\n";

	$notify_message .= sprintf( __( 'Spam it: %s' ), admin_url( "comment.php?action=spam&c=$comment_id" ) ) . "\r\n";
	$notify_message .= sprintf( _n( 'Currently %s comment is waiting for approval. Please visit the moderation panel:',
		'Currently %s comments are waiting for approval. Please visit the moderation panel:', $comments_waiting ), number_format_i18n( $comments_waiting ) ) . "\r\n";
	$notify_message .= admin_url( "edit-comments.php?comment_status=moderated" ) . "\r\n";

	$subject = sprintf( __( '[%1$s] Please moderate: "%2$s"' ), $blogname, $post->post_title );

	$message_headers = '';

	$notify_message  = apply_filters( 'comment_moderation_text', $notify_message, $comment_id );
	$subject         = apply_filters( 'comment_moderation_subject', $subject, $comment_id );
	$message_headers = apply_filters( 'comment_moderation_headers', $message_headers );

	@wp_mail( $admin_email, $subject, $notify_message, $message_headers );

	return true;
}
endif;

?>
