<?php

class WCB_Header extends WCB_Element {
	function get_id() {
		return 'header';
	}

	function content() { ?>
		<div id="<?php echo $this->get_id(); ?>" class="grid_12">
			<div id="return-to-central">
				<a href="http://central.wordcamp.org/" title="<?php esc_attr_e( 'Return to WordCamp Central', 'wcb' ); ?>"><?php _e('&larr; WordCamp Central', 'wcb'); ?></a>
			</div>
			<div id="masthead">
				<div id="branding" role="banner">
					<div id="branding-overlay"></div>
					<div id="branding-logo"></div>
					<?php wcb_site_title(); ?>
					<div id="site-description"><?php bloginfo( 'description' ); ?></div>
					<?php wcb_header_image(); ?>
				</div><!-- #branding -->
			</div><!-- #masthead -->
		</div><!-- #header -->
	<?php
	}
}

?>