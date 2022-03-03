<?php

class WCB_Speakers extends WCB_Loader {
	var $meta_manager;

	function constants() {
		wcb_maybe_define( 'WCB_SPEAKER_POST_TYPE', 'wcb_speaker', 'wcb_speaker_post_type' );
		wcb_maybe_define( 'WCB_SPEAKER_SLUG',      'speaker',     'wcb_speaker_slug'      );
	}

	function includes() {
		require_once "class-wcb-speaker-template.php";
	}

	function loaded() {
		$this->meta_manager = new WCB_Post_Meta_Manager( array(
			'prefix'    => 'wcb_speaker',
			'keys'      => array('email'),
		) );

		if ( is_admin() ) {
			$meta_fields = array(
				'email'     => array(
					'type'      => 'text',
					'label'     => __('Gravatar Email', 'wcb'),
				)
			);

			$box = wcb_get_metabox( 'WCB_Post_Metabox' );
			$box->add_instance( WCB_SPEAKER_POST_TYPE, array(
				'title'          => __('Gravatar Email', 'wcb'),
				'meta_manager'   => $this->meta_manager,
				'meta_fields'    => $meta_fields,
			) );
		}
	}

	function register_post_types() {
		// Speaker post type labels
		$labels = array (
			'name'                  => __( 'Speakers', 'wcb' ),
			'singular_name'         => __( 'Speaker', 'wcb' ),
			'add_new'               => __( 'Add New', 'wcb' ),
			'add_new_item'          => __( 'Create New Speaker', 'wcb' ),
			'edit'                  => __( 'Edit', 'wcb' ),
			'edit_item'             => __( 'Edit Speaker', 'wcb' ),
			'new_item'              => __( 'New Speaker', 'wcb' ),
			'view'                  => __( 'View Speaker', 'wcb' ),
			'view_item'             => __( 'View Speaker', 'wcb' ),
			'search_items'          => __( 'Search Speakers', 'wcb' ),
			'not_found'             => __( 'No speakers found', 'wcb' ),
			'not_found_in_trash'    => __( 'No speakers found in Trash', 'wcb' ),
			'parent_item_colon'     => __( 'Parent Speaker:', 'wcb' )
		);

		// Speaker post type rewrite
		$rewrite = array (
			'slug'        => WCB_SPEAKER_SLUG,
			'with_front'  => false,
		);

		// Speaker post type supports
		$supports = array (
			'title',
			'editor',
			'revisions',
		);

		$menu_icon = wcb_menu_icon( WCB_SPEAKER_POST_TYPE, WCB_URL . '/images/speakers.png' );

		// Register speaker post type
		register_post_type (
			WCB_SPEAKER_POST_TYPE,
			apply_filters( 'wcb_speaker_register_post_type',
				array (
					'labels'            => $labels,
					'rewrite'           => $rewrite,
					'supports'          => $supports,
					'menu_position'     => 20,
					'public'            => false,
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

}

?>