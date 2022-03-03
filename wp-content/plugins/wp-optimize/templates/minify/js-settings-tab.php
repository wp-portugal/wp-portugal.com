<?php if (!defined('WPO_VERSION'))  die('No direct access allowed'); ?>
<div class="wpo_section wpo_group">
	<div id="wpo_settings_warnings"></div>
	<form>
		<h3><?php _e('JavaScript options', 'wp-optimize'); ?></h3>
		<div class="wpo-fieldgroup">
			<fieldset>
				<label for="enable_js_minification">
					<input
						name="enable_js_minification"
						type="checkbox"
						id="enable_js_minification"
						value="1"
						<?php echo checked($wpo_minify_options['enable_js_minification']); ?>
					>
					<?php _e('Enable minification of JavaScript files', 'wp-optimize'); ?>
				</label>
				<label for="enable_merging_of_js">
					<input
						name="enable_merging_of_js"
						type="checkbox"
						id="enable_merging_of_js"
						value="1"
						<?php echo checked($wpo_minify_options['enable_merging_of_js']); ?>
					>
					<?php _e('Enable merging of JavaScript files', 'wp-optimize'); ?>
					<span tabindex="0" data-tooltip="<?php _e('If some functionality is breaking on the frontend, disabling merging of JavaScript might fix the issues.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
				</label>
				<label for="enable_js_trycatch">
					<input
						name="enable_js_trycatch"
						type="checkbox"
						id="enable_js_trycatch"
						value="1"
						<?php echo checked($wpo_minify_options['enable_js_trycatch']); ?>
					>
					<?php _e('Contain each included file in its own block', 'wp-optimize'); ?>
					<em><?php _e('(enable if trying to isolate a JavaScript error introduced by minifying or merging)', 'wp-optimize'); ?></em>
					<span tabindex="0" data-tooltip="<?php esc_attr_e('When enabled, the content of each JavaScript file that is combined will be wrapped in its own "try / catch" statement. This means that if one file has an error, it should not impede execution of other, independent files.', 'wp-optimize'); ?>"><span class="dashicons dashicons-editor-help"></span> </span>
				</label>
			</fieldset>
		</div>
		<h3><?php _e('Exclude JavaScript from processing', 'wp-optimize'); ?></h3>
		<div class="wpo-fieldgroup">
			<fieldset>
				<label for="exclude_js">
					<?php _e('Any JavaScript files that match the paths below will be completely ignored', 'wp-optmize'); ?>
					<span tabindex="0" data-tooltip="<?php esc_attr_e('Use this if you are having issues with a certain JavaScript file.', 'wp-optmize'); ?> <?php esc_attr_e('Any file present here will be loaded normally by WordPress', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span></span>
				</label>
				<textarea
					name="exclude_js"
					rows="7" cols="50"
					id="exclude_js"
					class="large-text code"
					placeholder="<?php esc_attr_e('e.g.: /wp-includes/js/jquery/jquery.js', 'wp-optimize'); ?>"
				><?php echo esc_textarea($wpo_minify_options['exclude_js']);?></textarea>
				<br>
				<?php _e('Some files known for causing issues when combined / minified are excluded by default.', 'wp-optimize'); ?> <?php _e('You can see / edit them in the Advanced tab.', 'wp-optimize'); ?>
			</fieldset>
		</div>

		<h3><?php _e('Defer JavaScript', 'wp-optimize'); ?></h3>
		<div class="wpo-fieldgroup">
			<fieldset class="async-js-manual-list">
				<h4><label>
					<input
						name="enable_defer_js"
						type="radio" 
						value="individual"
						<?php echo checked($wpo_minify_options['enable_defer_js'], 'individual'); ?>
					>
					<?php _e('Defer selected JavaScript files', 'wp-optimize'); ?>
					<span tabindex="0" data-tooltip="<?php esc_attr_e('The files in the list will be loaded asynchronously, and will not be minified or merged.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span></span>
				</label>
				</h4>
				<div class="defer-js-settings">
					<label for="async_js">
						<?php _e('Any JavaScript files that match the paths below will be loaded asynchronously.', 'wp-optmize'); ?>
						<br/>
						<?php _e('Use this if you have a completely independent script or would like to exclude scripts from page speed tests (PageSpeed Insights, GTMetrix...)', 'wp-optmize'); ?>
						<span tabindex="0" data-tooltip="<?php esc_attr_e('Independent scripts are for example \'analytics\' or \'pixel\' scripts. They are not required for the website to work', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span></span>
					</label>
					<textarea
						name="async_js"
						rows="7"
						cols="50"
						id="async_js"
						class="large-text code"
						placeholder="<?php esc_attr_e('e.g.: /js/main.js', 'wp-optimize'); ?>"
					><?php echo esc_textarea($wpo_minify_options['async_js']); ?></textarea>
				</div>
			</fieldset>
			
			<fieldset>
				<h4>
					<label>
						<input
							name="enable_defer_js"
							type="radio" 
							value="all"
							<?php echo checked($wpo_minify_options['enable_defer_js'], 'all'); ?>
						>
						<?php _e('Defer all the JavaScript files', 'wp-optimize'); ?>
						<span tabindex="0" data-tooltip="<?php esc_attr_e('All files - including the ones processed by WP-Optimize - will be deferred, except the ones in the exclusion list above.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span></span>
					</label>
				</h4>
				<div class="defer-js-settings">
					<div class="notice notice-warning below-h2">
						<p class="wpo_min-bold-green wpo_min-rowintro">
							<?php _e('Some themes and plugins need render blocking scripts to work.', 'wp-optimize'); ?> <?php _e('Please check the browser console for any eventual JavaScript errors caused by deferring the scripts.', 'wp-optimize'); ?>
						</p>
					</div>
					<h4><?php _e('Defer method:', 'wp-optimize'); ?></h4>
					<label>
						<input
							name="defer_js_type"
							type="radio" 
							value="defer"
							<?php echo checked($wpo_minify_options['defer_js_type'], 'defer'); ?>
						>
						<?php _e('Use the "defer" html attribute', 'wp-optimize'); ?>
						<span tabindex="0" data-tooltip="<?php esc_attr_e('Supported by all modern browsers.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span></span>
					</label>
					<label>
						<input
							name="defer_js_type"
							type="radio" 
							value="async_using_js"
							<?php echo checked($wpo_minify_options['defer_js_type'], 'async_using_js'); ?>
						>
						<?php _e('Defer using JavaScript', 'wp-optimize'); ?>
						<em><?php printf(__('(Use this method if you require support for %solder browsers%s).', 'wp-optimize'), '<a href="https://www.w3schools.com/tags/att_script_defer.asp" target="_blank">', '</a>');?></em>
					</label>
					<label for="defer_jquery">
						<input
							name="defer_jquery"
							type="checkbox"
							id="defer_jquery"
							value="1"
							<?php echo checked($wpo_minify_options['defer_jquery']); ?>
						>
						<?php _e('Defer jQuery', 'wp-optimize'); ?> <em><?php _e('(Note that as jQuery is a common dependency, it probably needs to be loaded synchronously).', 'wp-optimize'); ?></em>
						<span tabindex="0" data-tooltip="<?php esc_attr_e('Disable this setting if you have an error \'jQuery undefined\'.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span></span>
					</label>
				</div>
			</fieldset>
		</div>

		<p class="submit">
			<input
				class="wp-optimize-save-minify-settings button button-primary"
				type="submit"
				value="<?php esc_attr_e('Save settings', 'wp-optimize'); ?>"
			>
			<img class="wpo_spinner" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>" alt="...">
			<span class="save-done dashicons dashicons-yes display-none"></span>
		</p>
	</form>
</div>
