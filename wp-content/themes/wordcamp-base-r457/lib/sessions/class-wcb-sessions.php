<?php

class WCB_Sessions extends WCB_Loader {
	var $meta_manager;

	function constants() {
		wcb_maybe_define( 'WCB_SESSION_POST_TYPE', 'wcb_session', 'wcb_session_post_type' );
		wcb_maybe_define( 'WCB_SESSION_SLUG',      'session',     'wcb_session_slug'      );
		wcb_maybe_define( 'WCB_TRACK_TAXONOMY',    'wcb_track',   'wcb_track_taxonomy'    );
	}

	function includes() {
		require_once "class-wcb-session-template.php";
	}

	function loaded() {
		$this->meta_manager = new WCB_Post_Meta_Manager( array(
			'prefix'    => 'wcb_session',
			'keys'      => array('speakers'),
		) );

		if ( is_admin() ) {
			$meta_fields = array(
				'speakers'  => array(
					'type'      => 'text',
					'label'     => __('Speakers', 'wcb'),
				)
			);

			$box = wcb_get_metabox( 'WCB_Post_Metabox' );
			$box->add_instance( WCB_SESSION_POST_TYPE, array(
				'title'          => __('Speakers', 'wcb'),
				'meta_manager'   => $this->meta_manager,
				'meta_fields'    => $meta_fields,
				'context'        => 'normal',
				'priority'       => 'high',
			) );
		}
	}

	function register_post_types() {
		// Session post type labels
		$labels = array (
			'name'                  => __( 'Sessions', 'wcb' ),
			'singular_name'         => __( 'Session', 'wcb' ),
			'add_new'               => __( 'Add New', 'wcb' ),
			'add_new_item'          => __( 'Create New Session', 'wcb' ),
			'edit'                  => __( 'Edit', 'wcb' ),
			'edit_item'             => __( 'Edit Session', 'wcb' ),
			'new_item'              => __( 'New Session', 'wcb' ),
			'view'                  => __( 'View Session', 'wcb' ),
			'view_item'             => __( 'View Session', 'wcb' ),
			'search_items'          => __( 'Search Sessions', 'wcb' ),
			'not_found'             => __( 'No sessions found', 'wcb' ),
			'not_found_in_trash'    => __( 'No sessions found in Trash', 'wcb' ),
			'parent_item_colon'     => __( 'Parent Session:', 'wcb' )
		);

		// Session post type rewrite
		$rewrite = array (
			'slug'        => WCB_SESSION_SLUG,
			'with_front'  => false,
		);

		// Session post type supports
		$supports = array (
			'title',
			'editor',
			'revisions',
			'thumbnail',
		);

		$menu_icon = wcb_menu_icon( WCB_SESSION_POST_TYPE, WCB_URL . '/images/sessions.png' );

		// Register session post type
		register_post_type (
			WCB_SESSION_POST_TYPE,
			apply_filters( 'wcb_session_register_post_type',
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
			'name'              => __( 'Tracks', 'wcpt' ),
			'singular_name'     => __( 'Track', 'wcpt' ),
			'search_items'      => __( 'Search Tracks', 'wcpt' ),
			'popular_items'     => __( 'Popular Tracks', 'wcpt' ),
			'all_items'         => __( 'All Tracks', 'wcpt' ),
			'edit_item'         => __( 'Edit Track', 'wcpt' ),
			'update_item'       => __( 'Update Track', 'wcpt' ),
			'add_new_item'      => __( 'Add Track', 'wcpt' ),
			'new_item_name'     => __( 'New Track', 'wcpt' ),
		);

		// Rewrite
		$rewrite = array (
			'slug' => 'track'
		);

		// Register the taxonomy
		register_taxonomy (
			WCB_TRACK_TAXONOMY,             // The tax ID
			WCB_SESSION_POST_TYPE,          // The post type ID
			apply_filters( 'wcb_track_taxonomy_register',
				array (
					'labels'                => $labels,
					'rewrite'               => $rewrite,
					'query_var'             => 'track',
					'hierarchical'          => true,
					'public'                => true,
					'show_ui'               => true,
				)
			)
		);
	}

}

?>