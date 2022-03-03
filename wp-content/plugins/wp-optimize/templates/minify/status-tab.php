<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>
<div id="wp-optimize-minify-status" class="wpo_section wpo_group<?php echo (!$wpo_minify_options['enabled']) ? ' wpo-feature-is-disabled' : ''; ?>">
	<form>
		<div class="wpo-info wpo-show">
			<a class="wpo-info__trigger" href="#"><span class="dashicons dashicons-sos"></span> <?php _e('How to use the minify feature', 'wp-optimize'); ?> <span class="wpo-info__close"><?php _e('Close', 'wp-optimize'); ?></span></a>
			<div class="wpo-info__content">
				<p><strong><?php _e('Not sure how to use the Minify feature?', 'wp-optimize'); ?></strong> <br><?php _e('Watch our how-to video below.', 'wp-optimize'); ?></p>
				<div class="wpo-video-preview">
					<a href="https://vimeo.com/402556749" data-embed="https://player.vimeo.com/video/402556749?color=df6926&title=0&byline=0&portrait=0" target="_blank"><img src="<?php echo trailingslashit(WPO_PLUGIN_URL); ?>images/notices/minify-video-preview.png" alt="<?php _e('Minify video preview', 'wp-optimize');?>" /></a>
				</div>
				<small>(<?php _e('Loads a video hosted on vimeo.com', 'wp-optimize'); ?>) - <a href="https://vimeo.com/402556749" target="_blank"><?php _e('Open the video in a new window', 'wp-optimize'); ?></a></small>
				<p><a href="<?php echo WP_Optimize()->maybe_add_affiliate_params('https://getwpo.com/faqs/category/minification/'); ?>"><?php _e('Read the documentation', 'wp-optimize'); ?></a></p>
			</div>
		</div>
		<div id="wpo_settings_warnings"></div>
		<?php if ($show_information_notice) : ?>
			<div class="notice notice-warning wpo-warning is-dismissible below-h2 wp-optimize-minify-status-information-notice wpo-show">
				<p>
					<span class="dashicons dashicons-shield"></span>
					<strong><?php _e('CSS, JavaScript and HTML minification is an advanced feature.', 'wp-optimize'); ?></strong><br>
					<?php _ex('While enabling it will work just fine for most sites, it might need specific configuration to work properly on your website.', '"it" refers to the Minify feature.', 'wp-optimize'); ?><br>
					<?php _ex('If you encounter an issue and are not sure what to do, disable the feature and ask for help on the support forum.', '"it" refers to the Minify feature.', 'wp-optimize'); ?>
					<?php _ex('We will do our best to help you configure it.', '"it" refers to the Minify feature.', 'wp-optimize'); ?>
					<a href="<?php echo WP_Optimize()->maybe_add_affiliate_params('https://getwpo.com/faqs/category/minification/'); ?>"><?php _e('Read the documentation', 'wp-optimize'); ?></a>
				</p>
			</div>
		<?php endif; ?>
		<div class="wpo-fieldgroup wpo-show">
			<div class="switch-container">
				<label class="switch">
					<input
						name="enabled"
						id="wpo_min_enable_minify"
						class="wpo-save-setting"
						type="checkbox"
						value="true"
						<?php echo WPO_MINIFY_PHP_VERSION_MET ? '' : 'disabled'; ?>
						<?php checked($wpo_minify_options['enabled']); ?>
					>
					<span class="slider round"></span>
				</label>
				<label for="wpo_min_enable_minify">
					<?php if (WPO_MINIFY_PHP_VERSION_MET) {
						_e('Enable Minify', 'wp-optimize');
					} else {
						echo __('The PHP version on your server is too old.', 'wp-optimize').' '.__('Update PHP to enable minification of JS, CSS and HTML on this website', 'wp-optimize');
						?>
						<span tabindex="0" data-tooltip="<?php esc_attr_e('PHP version requirement (5.4 minimum) not met', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span></span>
						<?php
					}
					?>
				</label>
			</div>

			<p><?php _e('If this is turned on, then the default settings are that JavaScript and CSS on this website will be concatenated and minified and HTML will be minified.', 'wp-optimize'); ?> <?php _e('You can adjust the settings in the tabs above to control this to meet your requirements.', 'wp-optimize'); ?></p>

			<?php if (!empty($active_minify_plugins)) : ?>
				<div class="notice notice-error">
					<p>
						<?php printf(__('It looks like you already have an active minify plugin (%s) installed. Having more than one active plugin to minify front end assets might cause unexpected results and waste of resources.', 'wp-optimize'), implode(', ', $active_minify_plugins)); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) : ?>
				<div class="notice notice-warning">
					<p><span class="dashicons dashicons-info"></span> <?php printf(__('The constant %s is set to true, so no JavaScript or CSS file will be minified.', 'wp-optimize'), '<code>SCRIPT_DEBUG</code>'); ?></p>
				</div>
			<?php endif; ?>

			<div class="wpo-minify-features">

				<div class="wpo-fieldgroup__subgroup">
					<div class="switch-container">
						<label class="switch">
							<input
								name="html_minification"
								id="wpo_min_enable_minify_html"
								class="wpo-save-setting"
								type="checkbox"
								value="true"
								<?php checked($wpo_minify_options['html_minification']);?>
							>
							<span class="slider round"></span>
						</label>
						<label for="wpo_min_enable_minify_html">
							<?php _e('Process HTML (works only when cache pre-loading)', 'wp-optimize'); ?>
							<?php // Note: the comment added by WPO regarding cacheing will not be removed (it's added later in the process) ?>
							<span tabindex="0" data-tooltip="<?php esc_attr_e('All HTML will be minified. It takes effect only when cache pre-loading because it takes time.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
						</label>
					</div>
				</div>

				<div class="wpo-fieldgroup__subgroup">
					<div class="switch-container">
						<label class="switch">
							<input
								name="enable_js"
								id="wpo_min_enable_minify_js"
								class="wpo-save-setting"
								type="checkbox"
								value="true"
								data-tabname="js"
								<?php checked($wpo_minify_options['enable_js']);?>
							>
							<span class="slider round"></span>
						</label>
						<label for="wpo_min_enable_minify_js">
							<?php _e('Process JavaScript files', 'wp-optimize'); ?>
							<span tabindex="0" data-tooltip="<?php esc_attr_e('The JavaScript files will be combined and minified to lower the number and size of requests.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
							<a href="#" class="js--wpo-goto" data-tab="js"><?php _e('Settings', 'wp-optimize'); ?></a>
						</label>
					</div>
				</div>

				<div class="wpo-fieldgroup__subgroup">
					<div class="switch-container">
						<label class="switch">
							<input
								name="enable_css"
								id="wpo_min_enable_minify_css"
								class="wpo-save-setting"
								type="checkbox"
								value="true"
								data-tabname="css"
								<?php checked($wpo_minify_options['enable_css']);?>
							>
							<span class="slider round"></span>
						</label>
						<label for="wpo_min_enable_minify_css">
							<?php _e('Process CSS files', 'wp-optimize'); ?>
							<span tabindex="0" data-tooltip="<?php esc_attr_e('The stylesheets will be combined and minified to lower the number and size of requests.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
							<a href="#" class="js--wpo-goto" data-tab="css"><?php _e('Settings', 'wp-optimize'); ?></a>
						</label>
					</div>
				</div>			
			</div>
		</div>

		<div class="wpo-fieldgroup">
			<p class="actions">
				<input
					class="button button-primary purge_minify_cache <?php echo $can_purge_the_cache ? '' : 'disabled'; ?>"
					type="submit"
					value="<?php wp_optimize_minify_config()->always_purge_everything() ? esc_attr_e('Purge the minified files', 'wp-optimize') : esc_attr_e('Reset the minified files', 'wp-optimize'); ?>"
					<?php echo WPO_MINIFY_PHP_VERSION_MET && $can_purge_the_cache ? '' : 'disabled'; ?>
				/>
				<img class="wpo_spinner" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>" alt="...">
				<span class="save-done dashicons dashicons-yes display-none"></span>
			</p>
			<p>
				<span><?php _e("The new minified files will be regenerated when visiting your website's pages.", 'wp-optimize'); ?> <a href="https://getwpo.com/faqs/what-does-reset-the-minified-files-actually-do/"><?php _e('Read more about what this does in our FAQs.', 'wp-optimize'); ?></a> (<?php _e('This will also purge the page cache', 'wp-optimize'); ?>)</span>
			</p>
			<?php if (WPO_MINIFY_PHP_VERSION_MET) : ?>
				<?php _e('Minify cache size:', 'wp-optimize'); ?>
				<ul class="ul-disc">
					<li><?php _e('Current cache:', 'wp-optimize'); ?>
						<strong id="wpo_min_cache_size">
							<?php
								if ($wpo_minify_options['enabled']) {
									echo esc_html(WP_Optimize_Minify_Cache_Functions::get_cachestats($cache_dir));
								} else {
									_e('No minified files are present', 'wp-optimize');
								}
							?>
						</strong>
						<a href="#" class="js--wpo-goto" data-tab="advanced"><?php _e('View the files', 'wp-optimize'); ?></a>
					</li>
					<li>
						<?php _e('Total cache:', 'wp-optimize'); ?>
						<strong id="wpo_min_cache_total_size">
							<?php
								if ($wpo_minify_options['enabled']) {
									echo esc_html(WP_Optimize_Minify_Cache_Functions::get_cachestats(WPO_CACHE_MIN_FILES_DIR));
								} else {
									_e('No minified files are present', 'wp-optimize');
								}
							?>
						</strong>
						<strong tabindex="0" data-tooltip="<?php _e('This includes the older, non-expired cache, as well as the temporary files used to generate the minified files.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span></strong>
					</li>
				</ul>
			<?php endif; ?>
			<p>
				<?php _e('Last Minify cache update:', 'wp-optimize'); ?>
				<strong id="wpo_min_cache_time">
					<?php
					if (empty($wpo_minify_options['last-cache-update'])) {
						_e('Never.', 'wp-optimize');
					} elseif (!$wpo_minify_options['enabled']) {
						echo '-';
					} else {
						echo WP_Optimize_Minify_Cache_Functions::format_date_time($wpo_minify_options['last-cache-update']);
					}
					?>
				</strong>
			</p>
			<?php if ($wpo_minify_options['debug']) : ?>
				<p class="actions">
					<input
						class="button minify_increment_cache"
						type="button"
						value="<?php esc_attr_e('Increment cache', 'wp-optimize'); ?>"
						<?php echo WPO_MINIFY_PHP_VERSION_MET ? '' : 'disabled'; ?>
					/>
					<img class="wpo_spinner" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>" alt="...">
					<span class="save-done dashicons dashicons-yes display-none"></span>
					<strong tabindex="0" data-tooltip="<?php _e('This will reset the files generated by minify, but use the existing minify temporary files.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span></strong>
				</p>
				<?php
					// This is only necessary if the everything isn't purged
					if (!wp_optimize_minify_config()->always_purge_everything()) :
				?>
					<p class="actions">
						<input
							class="button purge_all_minify_cache"
							type="button"
							value="<?php esc_attr_e('Delete all the files generated by minifcation', 'wp-optimize'); ?>"
							<?php echo WPO_MINIFY_PHP_VERSION_MET ? '' : 'disabled'; ?>
						/>
						<img class="wpo_spinner" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>" alt="...">
						<span class="save-done dashicons dashicons-yes display-none"></span>
						<strong tabindex="0" data-tooltip="<?php _e('If you are using an unsupported cache plugin, then you will also need to purge your page cache when doing this.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span></strong>
					</p>
				<?php
					endif;
				?>
			<?php endif; ?>
		</div>
	</form>
</div><!-- end #wp-optimize-minify-status -->
