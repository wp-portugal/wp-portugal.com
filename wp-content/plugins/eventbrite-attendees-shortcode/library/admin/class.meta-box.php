<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Eventbrite_Attendees_Meta_Box {	

	private $domain,
			$version;
	
	/* Do it */
	public function __construct() {		
		add_action( 'admin_init', array( $this, 'init' ) );
	}
	
	/**
	 * Set parent domain
	 * 
	 * @param string $domain
	 * @return void
	 */
	public function set_domain( $domain ) {
		$this->domain = $domain;
	}
	
	/**
	 * Set parent version
	 * 
	 * @param string $version
	 * @return void
	 */
	public function set_version( $version ) {
		$this->version = $version;
	}
	
	/**
	 * Actions
	 * 
	 * @return void
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts',	array( $this, 'admin_scripts' ) );
		add_action( 'add_meta_boxes',			array( $this, 'add_meta_box' ) );
	}
		
	/**
	 * Register the plugin scripts.
	 *
	 * @since  0.4
	 * @access public
	 * @return void
	 */
	function admin_scripts() {
		global $pagenow;
		
		if ( ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) && !defined( 'IFRAME_REQUEST' ) )
			wp_enqueue_script( $this->domain . '-admin', plugins_url( 'library/js/admin.js', EVENTBRITE_ATTENDEES_FILE ), array( 'jquery' ), $this->version, false );
	}

	/**
	 * Creates a meta box on the post (page, other post types) editing screen for allowing the easy input of 
	 * commonly-used post metadata.  The function uses the get_post_types() function for grabbing a list of 
	 * available post types and adding a new meta box for each post type.
	 *
	 * @uses get_post_types() Gets an array of post type objects.
	 * @uses add_meta_box() Adds a meta box to the post editing screen.
	 */
	function add_meta_box() {		
		/* Gets available public post types. */
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		
		$post_types = apply_filters( 'eventbrite_attendees_shortcode_meta_box', $post_types );
		
		/* For each available post type, create a meta box on its edit page if it supports '$prefix-post-settings'. */
		foreach ( $post_types as $type ) {
			/* Add the meta box. */
			add_meta_box( $this->domain . "-{$type->name}-meta-box", __( 'Eventbrite Attendees Shortcode (does not save meta)', $this->domain ), array( $this, 'meta_box' ), $type->name, 'side', 'default' );
		}
	}
	
	/**
	 * Creates the settings for the post meta box.  
	 *
	 * @param string $type The post type of the current post in the post editor.
	 */
	function meta_box_args( $type = '' ) {
		$meta = array();
	
		/* If no post type is given, default to 'post'. */
		if ( empty( $type ) )
			$type = 'post';
		
		$true_false = array ( 'true' => 'true', 'false' => 'false' );
		
		/* Options */
		$meta['id'] = array( 'name' => '_Eventbrite_id', 'title' => __( 'Eventbrite ID:', $this->domain ), 'type' => 'text', 
			'description' => '<small>eventbrite.com/json/event_list_attendees?id=</small><strong>384870157</strong>' );
		
		$meta['sort'] = array( 'name' => '_Eventbrite_sort', 'title' => __( 'Sort:', $this->domain ), 'type' => 'select', 'options' => $true_false, 'use_key_and_value' => true, 
			'description' => '' );
		
		$meta['clickable'] = array( 'name' => '_Eventbrite_clickable', 'title' => __( 'Clickable:', $this->domain ), 'type' => 'select', 'options' => $true_false, 'use_key_and_value' => true, 
			'description' => '' );
		
		$meta['user_key'] = array( 'name' => '_Eventbrite_user_key', 'title' => __( 'User Key:', $this->domain ), 'type' => 'text',
			'description' => __( 'Optional.', $this->domain ) );
	
		return $meta;
	}
	
	/**
	 * Displays the post meta box on the edit post page. The function gets the various metadata elements
	 * from the meta_box_args() function. It then loops through each item in the array and
	 * displays a form element based on the type of setting it should be.
	 *
	 * @parameter object $object Post object that holds all the post information.
	 * @parameter array $box The particular meta box being shown and its information.
	 */
	function meta_box( $object, $box ) {
	
		$meta_box_options = self::meta_box_args( $object->post_type );
	
		foreach ( $meta_box_options as $option ) {
			if ( method_exists( $this, "meta_box_{$option['type']}" ) )
				call_user_func( array( $this, "meta_box_{$option['type']}" ), $option, get_post_meta( $object->ID, $option['name'], true ) );
		} ?>
		
		<p class="output">
			<label for="_Eventbrite_output"><?php _e( 'Output:', $this->domain ); ?></label>
			<br />
			<span id="_Eventbrite_output" class="postbox" style="-webkit-box-sizing: border-box; -moz-box-sizing: border-box; box-sizing: border-box; display:block; line-height: 18px; min-height: 50px; padding: 5px;"></span>
		</p>
		
		<?php printf( __( 'Like this plugin? <a href="%s" target="_blank">Buy me a beer</a>!', $this->domain ), 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7329157' ); ?></p><?php
	}
	
	/**
	 * Outputs a text input box with the given arguments for use with the post meta box.
	 *
	 * @param array $args 
	 * @param string|bool $value Custom field value.
	 */
	function meta_box_text( $args = array(), $value = false ) {
		$name = preg_replace( "/[^A-Za-z_-]/", '-', $args['name'] ); ?>
		<p>
			<label for="<?php echo $name; ?>"><?php echo $args['title']; ?></label>
			<br />
			<input type="text" name="<?php echo $name; ?>" id="<?php echo $name; ?>" value="<?php echo esc_attr( $value ); ?>" size="30" tabindex="30" style="width: <?php echo ( !empty( $args['width'] ) ? $args['width'] : '99%' ); ?>;" />
			<?php if ( !empty( $args['description'] ) ) echo '<br /><span class="howto">' . $args['description'] . '</span>'; ?>
		</p>
		<?php
	}
	
	/**
	 * Outputs a select box with the given arguments for use with the post meta box.
	 *
	 * @param array $args
	 * @param string|bool $value Custom field value.
	 */
	function meta_box_select( $args = array(), $value = false ) {
		$name = preg_replace( "/[^A-Za-z_-]/", '-', $args['name'] ); ?>
		<p>
			<label for="<?php echo $name; ?>"><?php echo $args['title']; ?></label>
			<?php if ( !empty( $args['sep'] ) ) echo '<br />'; ?>
			<select name="<?php echo $name; ?>" id="<?php echo $name; ?>" style="width:60px">
				<?php // echo '<option value=""></option>'; ?>
				<?php $i = 0; foreach ( $args['options'] as $option => $val ) { $i++; ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( esc_attr( $value ), esc_attr( $val ) ); //if ( $i == 1 ) echo 'selected="selected"'; ?>><?php echo ( !empty( $args['use_key_and_value'] ) ? $option : $val ); ?></option>
				<?php } ?>
			</select>
			<?php if ( !empty( $args['description'] ) ) echo '<br /><span class="howto">' . $args['description'] . '</span>'; ?>
		</p>
		<?php
	}
}