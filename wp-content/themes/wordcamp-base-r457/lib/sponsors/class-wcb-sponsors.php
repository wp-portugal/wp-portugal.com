<?php

class WCB_Sponsors extends WCB_Loader {
	var $meta_manager;

	function constants() {
		wcb_maybe_define( 'WCB_SPONSOR_POST_TYPE',      'wcb_sponsor',       'wcb_sponsor_post_type'      );
		wcb_maybe_define( 'WCB_SPONSOR_SLUG',           'sponsor',           'wcb_sponsor_slug'           );
		wcb_maybe_define( 'WCB_SPONSOR_LEVEL_TAXONOMY', 'wcb_sponsor_level', 'wcb_sponsor_level_taxonomy' );
		wcb_maybe_define( 'WCB_SPONSOR_LEVEL_SLUG',     'sponsor_level',     'wcb_sponsor_level_slug'     );
	}

	function includes() {
		require_once "class-wcb-sponsor-template.php";
		require_once "class-wcb-sponsor-order.php";
		require_once "class-wcb-widget-sponsors.php";
	}

	function loaded() {
		new WCB_Sponsor_Order;
		register_widget('WCB_Widget_Sponsors');
	}

	function register_post_types() {
		// Sponsor post type labels
		$labels = array (
			'name'                  => __( 'Sponsors', 'wcb' ),
			'singular_name'         => __( 'Sponsor', 'wcb' ),
			'add_new'               => __( 'Add New', 'wcb' ),
			'add_new_item'          => __( 'Create New Sponsor', 'wcb' ),
			'edit'                  => __( 'Edit', 'wcb' ),
			'edit_item'             => __( 'Edit Sponsor', 'wcb' ),
			'new_item'              => __( 'New Sponsor', 'wcb' ),
			'view'                  => __( 'View Sponsor', 'wcb' ),
			'view_item'             => __( 'View Sponsor', 'wcb' ),
			'search_items'          => __( 'Search Sponsors', 'wcb' ),
			'not_found'             => __( 'No sponsors found', 'wcb' ),
			'not_found_in_trash'    => __( 'No sponsors found in Trash', 'wcb' ),
			'parent_item_colon'     => __( 'Parent Sponsor:', 'wcb' )
		);

		// Sponsor post type rewrite
		$rewrite = array (
			'slug'        => WCB_SPONSOR_SLUG,
			'with_front'  => false,
		);

		// Sponsor post type supports
		$supports = array (
			'title',
			'editor',
			'revisions',
			'thumbnail',
		);

		$menu_icon = wcb_menu_icon( WCB_SPONSOR_POST_TYPE, WCB_URL . '/images/sponsors.png' );

		// Register sponsor post type
		register_post_type (
			WCB_SPONSOR_POST_TYPE,
			apply_filters( 'wcb_sponsor_register_post_type',
				array (
					'labels'            => $labels,
					'rewrite'           => $rewrite,
					'supports'          => $supports,
					'menu_position'     => 21,
					'public'            => true,
					'show_ui'           => true,
					'can_export'        => true,
					'capability_type'   => 'post',
					'hierarchical'      => false,
					'query_var'         => true,
					'menu_icon'         => $menu_icon,
				)
			)
		);
	}

	function register_taxonomies() {

		// Labels
		$labels = array (
			'name'              => __( 'Sponsor Levels', 'wcpt' ),
			'singular_name'     => __( 'Sponsor Level', 'wcpt' ),
			'search_items'      => __( 'Search Sponsor Levels', 'wcpt' ),
			'popular_items'     => __( 'Popular Sponsor Levels', 'wcpt' ),
			'all_items'         => __( 'All Sponsor Levels', 'wcpt' ),
			'edit_item'         => __( 'Edit Sponsor Level', 'wcpt' ),
			'update_item'       => __( 'Update Sponsor Level', 'wcpt' ),
			'add_new_item'      => __( 'Add Sponsor Level', 'wcpt' ),
			'new_item_name'     => __( 'New Sponsor Level', 'wcpt' ),
		);

		// Rewrite
		$rewrite = array (
			'slug' => WCB_SPONSOR_LEVEL_SLUG
		);

		// Register the taxonomy
		register_taxonomy (
			WCB_SPONSOR_LEVEL_TAXONOMY,     // The tax ID
			WCB_SPONSOR_POST_TYPE,          // The post type ID
			apply_filters( 'wcb_sponsor_level_tax_register',
				array (
					'labels'                => $labels,
					'rewrite'               => $rewrite,
					'query_var'             => 'sponsor_level',
					'hierarchical'          => true,
					'public'                => true,
					'show_ui'               => true,
				)
			)
		);
	}
}

?>