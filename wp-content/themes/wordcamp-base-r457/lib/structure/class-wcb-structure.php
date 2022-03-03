<?php

class WCB_Structure extends WCB_Loader {
	var $body;
	var $sidebars;
	var $excerpting = false;

	function includes() {
		// Require all elements
		$elements = array('element', 'elements', 'container', 'sidebar', 'sidebar-row', 'content', 'header', 'footer', 'menu', 'body');
		foreach ( $elements as $element ) {
			require_once "class-wcb-$element.php";
		}
	}

	function hooks() {
		add_action( 'after_setup_theme',    array( &$this, 'setup' ) );
		add_action( 'wp_head',              array( &$this, 'structure' ) );

		// add_filter( 'the_content',          array( &$this, 'home_excerpts' ), 5 );
	}

	function setup() {
		// Clear twenty ten's default widgets.
		remove_action( 'widgets_init', 'twentyten_widgets_init' );

		$this->register_sidebars();
		$this->enqueue_styles();
	}

	function enqueue_styles() {
		if ( is_admin() )
			return;

		// The user chooses a grid.
		$versions = array(
			'grid960' => '20110221',
			'grid720' => '20110221',
		);

		$grid  = wcb_get_option('grid');

		// If SafeCSS plugin has "start from scratch" selected, then bail.
		if ( get_option( 'safecss_add') == 'no' )
			return;

		wp_enqueue_style( 'wcb-foundation', WCB_URL . '/style.css', array(), '20110212' );
		$version = isset( $versions[ $grid ] ) ? $versions[ $grid ] : false;
		wp_enqueue_style( "wcb-$grid", wcb_dev_url( WCB_URL . "/css/$grid.css" ), array('wcb-foundation'), $version );
		wp_enqueue_style( "wcb-style", wcb_dev_url( WCB_URL . '/css/default.css' ), array('wcb-foundation', "wcb-$grid"), '20110421' );

		if ( is_child_theme() )
			wp_enqueue_style( "wcb-child", get_stylesheet_uri(), array('wcb-foundation', "wcb-$grid", 'wcb-style'), false );
	}

	function register_sidebars() {
		$this->sidebars = array();
		$sidebar_args = array(
			'after_header'      => array(
				'id'   => 'after-header',
				'name' => __('After Header', 'wcb'),
			),
			'before_content'    => array(
				'id'   => 'before-content',
				'name' => __('Before Content', 'wcb'),
			),
			'content'           => array(
				'id'   => 'content-row',
				'name' => __('Content', 'wcb'),
			),
			'after_content'     => array(
				'id'   => 'after-content',
				'name' => __('After Content', 'wcb'),
			),
			'before_footer'     => array(
				'id'   => 'before-footer',
				'name' => __('Before Footer', 'wcb'),
			),
		);

		foreach ( $sidebar_args as $id => $args ) {
			$option = wcb_get_option( $id );

			if ( ! $option['visible'] )
				continue;

			$args['grid'] = $option['layout'];

			if ( $option['front_only'] )
				$args['name'] = sprintf( __('Front Page: %s'), $args['name'] );

			$this->sidebars[ $id ] = new WCB_Sidebar_Row( $args );
		}
	}

	function full_width_content() {
		$this->sidebars['content'] = new WCB_Sidebar_Row( array(
			'id'   => 'content-row',
			'name' => __('Content', 'wcb'),
			'grid' => array( array( 12, 'content' ) ),
		) );
	}

	function structure() {
		$rows = array( new WCB_Header(), new WCB_Menu() );
		$keys = array( 'after_header', 'before_content', 'content', 'after_content', 'before_footer' );

		foreach ( $keys as $id ) {
			if ( ! isset( $this->sidebars[ $id ] ) )
				continue;

			$option = wcb_get_option( $id );

			if ( ! $option['front_only'] || is_front_page() )
				$rows[] = $this->sidebars[ $id ];
		}

		$rows[] = new WCB_Footer();

		$this->row_structure( $rows );
	}

	// @TODO: Potentially remove this function and use only row_structure?
	function column_structure( $rows ) {
		$this->body = new WCB_Body( new WCB_Container( array(
			'id'    => 'wrapper',
			'class' => 'container_12 hfeed',
		), $rows ) );
	}

	function row_structure( $rows ) {
		$this->body = new WCB_Body();

		foreach ( $rows as $row ) {
			$id = $row->get_id();
			$container = new WCB_Container( array(
				'id'    => "$id-container",
				'class' => 'container_12 hfeed clearfix',
			), array( $row ) );

			$wrapper = new WCB_Container( array(
				'id'    => "$id-wrapper",
				'class' => 'row-wrapper',
			), array( $container ) );

			$this->body->add( $wrapper );
		}
	}

	/**
	 * Replace the content with an excerpt on the home page.
	 */
	function home_excerpts( $content ) {
		if ( ! is_front_page() || $this->excerpting )
			return $content;

		$this->excerpting = true;
		$content = get_the_excerpt();
		$this->excerpting = false;
		return $content;
	}
}

function wcb_start_rendering() {
	$structure = wcb_get('structure');
	$structure->body->render();
}

function wcb_finish_rendering() {
	$structure = wcb_get('structure');
	$structure->body->resume();
}

?>