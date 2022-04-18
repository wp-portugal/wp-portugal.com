<?php
/*
 * This is the page users will see logged out.
 * You can edit this, but for upgrade safety you should copy and modify this file into a folder.
 * See https://docs.loginwithajax.com/advanced/templates/ for more information.
*/
/* @var array $lwa  Array of data supplied to widget */
?>
<div class="lwa-wrapper lwa-bones">
	<div class="lwa lwa-<?php echo esc_attr($lwa['template']); ?> pixelbones lwa-logged-in <?php if( !empty($lwa['loggedin_vertical']) ) echo 'vertical'; ?>" <?php if( !empty($lwa['css_style']) ) echo "style='{$lwa['css_style']}'" ?>>
		<?php
			$user = wp_get_current_user();
		?>
		<?php if( !empty($lwa['title_loggedin']) ): ?>
			<p class="lwa-title"><?php echo esc_html($lwa['title_loggedin']); ?></p>
		<?php endif; ?>
		<div class="grid-container">
			<div class="avatar lwa-avatar <?php if( !empty($lwa['avatar_rounded']) ) echo 'rounded'; ?>">
				<?php echo get_avatar( $user->ID, $size = $lwa['avatar_size'] );  ?>
			</div>
			<div class="lwa-info">
				<?php
				//Admin URL
				if ( !empty($lwa['profile_link']) ) {
					if( function_exists('bp_loggedin_user_link') ){
						?>
						<p><a href="<?php bp_loggedin_user_link(); ?>"><?php esc_html_e('Profile','login-with-ajax') ?></a></p>
						<?php
					}else{
						?>
						<p><a href="<?php echo trailingslashit(get_admin_url()); ?>profile.php"><?php esc_html_e('Profile','login-with-ajax') ?></a></p>
						<?php
					}
				}
				//Logout URL
				$logout_url = wp_logout_url();
				if( !empty($lwa['redirect_logout']) ){
					$logout_url = add_query_arg('redirect', $lwa['redirect_logout'], $logout_url);
				}
				?>
				<p><a id="wp-logout" href="<?php echo esc_url($logout_url)  ?>"><?php esc_html_e( 'Log Out' ,'login-with-ajax') ?></a></p>
				<?php
				//Blog Admin
				if( current_user_can('list_users') ) {
					?>
					<p><a href="<?php echo get_admin_url(); ?>"><?php esc_html_e("Site Admin", 'login-with-ajax'); ?></a></p>
					<?php
				}
				?>
			</div>
		</div>
	</div>
</div>