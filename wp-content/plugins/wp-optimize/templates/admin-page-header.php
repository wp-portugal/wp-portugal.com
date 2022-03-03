<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>

<header class="wpo-main-header">
	<p class="wpo-header-links">
		<span class="wpo-header-links__label"><?php _e('Useful links', 'wp-optimize'); ?></span>
		<?php $wp_optimize->wp_optimize_url('https://getwpo.com/', __('Home', 'wp-optimize')); ?> |

		<?php $wp_optimize->wp_optimize_url('https://updraftplus.com/', 'UpdraftPlus'); ?> |
		
		<?php $wp_optimize->wp_optimize_url('https://updraftplus.com/news/', __('News', 'wp-optimize')); ?> |

		<?php $wp_optimize->wp_optimize_url('https://twitter.com/updraftplus', __('Twitter', 'wp-optimize')); ?> |

		<?php $wp_optimize->wp_optimize_url('https://wordpress.org/support/plugin/wp-optimize/', __('Support', 'wp-optimize')); ?> |

		<?php $wp_optimize->wp_optimize_url('https://updraftplus.com/newsletter-signup', __('Newsletter', 'wp-optimize')); ?> |

		<?php $wp_optimize->wp_optimize_url('https://david.dw-perspective.org.uk', __("Team lead", 'wp-optimize')); ?> |
		
		<?php $wp_optimize->wp_optimize_url('https://getwpo.com/faqs/', __("FAQs", 'wp-optimize')); ?> |

		<?php $wp_optimize->wp_optimize_url('https://www.simbahosting.co.uk/s3/shop/', __("More plugins", 'wp-optimize')); ?>				
	</p>

	<div class="wpo-logo__container">
		<img class="wpo-logo" src="<?php echo trailingslashit(WPO_PLUGIN_URL); ?>images/notices/wp_optimize_logo.png" alt="" />
		<?php
			$sqlversion = (string) $wp_optimize->get_db_info()->get_version();
			echo '<strong>WP-Optimize '.(WP_Optimize::is_premium() ? __('Premium', 'wp-optimize') : '' ).' <span class="wpo-version">'.WPO_VERSION.'</span></strong>';
		?>
		<span class="wpo-subheader"><?php echo htmlspecialchars(__('Make your site fast & efficient', 'wp-optimize')); ?></span>
	</div>
	<?php
	$wp_optimize->include_template('pages-menu.php', false, array('menu_items' => WP_Optimize()->get_submenu_items()));
	?>
</header>
<?php
	if ($show_notices) {
		
		$installed = $wp_optimize->get_options()->get_option('installed-for', 0);
		$installed_for = time() - $installed;
		$advert = false;
		if ($installed && $installed_for > 28*86400 && $installed_for < 84*86400) {
			$advert = 'rate_plugin';
		}

		if ($installed && $installed_for > 14*86400) {
			// This is to display the notices.
			$wp_optimize_notices->do_notice($advert);
		}
	}
