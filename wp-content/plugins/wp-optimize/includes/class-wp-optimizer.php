<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

/**
 * This class invokes optimiazations. The optimizations themselves live in the 'optimizations' sub-directory of the plugin.  The proper way to obtain access to the instance is via WP_Optimize()->get_optimizer()
 */
class WP_Optimizer {
	
	public function get_retain_info() {
	
		$options = WP_Optimize()->get_options();
	
		$retain_enabled = $options->get_option('retention-enabled', 'false');
		$retain_period = (($retain_enabled) ? $options->get_option('retention-period', '2') : null);

		return array($retain_enabled, $retain_period);
	}

	/**
	 * Get data retention options
	 *
	 * @return array
	 */
	public function get_revisions_retain_info() {
		$options = WP_Optimize()->get_options();

		$revisions_retention_enabled = $options->get_option('revisions-retention-enabled', 'false');
		$revisions_retention_count = $revisions_retention_enabled ? $options->get_option('revisions-retention-count', '2') : null;

		return array($revisions_retention_enabled, $revisions_retention_count);
	}
	
	public function get_optimizations_list() {
	
		$optimizations = array();
		
		$optimizations_dir = WPO_PLUGIN_MAIN_PATH.'optimizations';
		
		if ($dh = opendir($optimizations_dir)) {
			while (($file = readdir($dh)) !== false) {
				if ('.' == $file || '..' == $file || '.php' != substr($file, -4, 4) || !is_file($optimizations_dir.'/'.$file) || 'inactive-' == substr($file, 0, 9)) continue;
				$optimizations[] = substr($file, 0, (strlen($file) - 4));
			}
			closedir($dh);
		}
		
		return apply_filters('wp_optimize_get_optimizations_list', $optimizations);

	}
	
	/**
	 * Currently, there is only one sort rule (so, the parameter's value is ignored)
	 *
	 * @param  array  $optimizations An array of optimizations (i.e. WP_Optimization instances).
	 * @param  string $sort_on       Specify sort.
	 * @param  string $sort_rule     Sort Rule.
	 * @return array
	 */
	public function sort_optimizations($optimizations, $sort_on = 'ui_sort_order', $sort_rule = 'traditional') {// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ('run_sort_order' == $sort_on) {
			uasort($optimizations, array($this, 'sort_optimizations_run_traditional'));
		} else {
			uasort($optimizations, array($this, 'sort_optimizations_ui_traditional'));
		}
		return $optimizations;
	}
	
	public function sort_optimizations_ui_traditional($a, $b) {
		return $this->sort_optimizations_traditional($a, $b, 'ui_sort_order');
	}
	
	public function sort_optimizations_run_traditional($a, $b) {
		return $this->sort_optimizations_traditional($a, $b, 'run_sort_order');
	}
	
	public function sort_optimizations_traditional($a, $b, $sort_on = 'ui_sort_order') {
	
		if (!is_a($a, 'WP_Optimization')) return (!is_a($b, 'WP_Optimization')) ? 0 : 1;
		if (!is_a($b, 'WP_Optimization')) return -1;
	
		$sort_order_a = empty($a->$sort_on) ? 0 : $a->$sort_on;
		$sort_order_b = empty($b->$sort_on) ? 0 : $b->$sort_on;
	
		if ($sort_order_a == $sort_order_b) return 0;
		
		return ($sort_order_a < $sort_order_b) ? (-1) : 1;
	
	}
	
	/**
	 * This method returns an array of available optimizations.
	 * Each array key is an optimization ID, and the value is an object,
	 * as returned by get_optimization()
	 *
	 * @return [array] array of optimizations or WP_Error objects
	 */
	public function get_optimizations() {
	
		$optimizations = $this->get_optimizations_list();
	
		$optimization_objects = array();
		
		foreach ($optimizations as $optimization) {
			$optimization_object = $this->get_optimization($optimization);
			if (is_wp_error($optimization_object)) {
				WP_Optimize()->log('Failed to load optimization ' . $optimization . ' - ' . $optimization_object->get_error_message());
			} else {
				$optimization_objects[$optimization] = $optimization_object;
			}
		}
	
		return apply_filters('wp_optimize_get_optimizations', $optimization_objects);
	
	}
	
	/**
	 * This method returns an object for a specific optimization.
	 *
	 * @param  string $which_optimization An optimization ID.
	 * @param  array  $data               An array of anny options $data.
	 * @return array                      WP_Error Will return the optimization, or a WP_Error object if it was not found.
	 */
	public function get_optimization($which_optimization, $data = array()) {

		$optimization_class = apply_filters('wp_optimize_optimization_class', 'WP_Optimization_'.$which_optimization);
		
		if (!class_exists('WP_Optimization')) include_once(WPO_PLUGIN_MAIN_PATH.'includes/class-wp-optimization.php');
	
		if (!class_exists($optimization_class)) {
			$optimization_file = WPO_PLUGIN_MAIN_PATH.'optimizations/'.$which_optimization.'.php';
			$class_file = apply_filters('wp_optimize_optimization_class_file', $optimization_file);
			if (!preg_match('/^[a-z]+$/', $which_optimization) || !file_exists($class_file)) {
				return new WP_Error('no_such_optimization', __('No such optimization', 'wp-optimize'), $which_optimization);
			}
			
			include_once($class_file);
			
			if (!class_exists($optimization_class)) {
				return new WP_Error('no_such_optimization', __('No such optimization', 'wp-optimize'), $which_optimization);
			}
		}

		// set sites option for Multisite cron job.
		if (defined('DOING_CRON') && DOING_CRON && is_multisite()) {
			$options = WP_Optimize()->get_options();
			$data['optimization_sites'] = $options->get_option('wpo-sites-cron', array('all'));
		}
		
		$optimization = new $optimization_class($data);
		
		return $optimization;
	
	}

	/**
	 * The method to call to perform an optimization.
	 *
	 * @param  string|object $which_optimization An optimization ID, or a WP_Optimization object.
	 * @return array                             Array of results from the optimization.
	 */
	public function do_optimization($which_optimization) {
		
		$optimization = (is_object($which_optimization) && is_a($which_optimization, 'WP_Optimization')) ? $which_optimization : $this->get_optimization($which_optimization);

		if (is_wp_error($optimization)) {
			WP_Optimize()->log('Error occurred. Unknown optimization.');
			return $optimization;
		}

		WP_Optimize()->change_time_limit();

		$optimization->init();
	
		if (apply_filters('wp_optimize_do_optimization', true, $which_optimization, $optimization)) {

			$optimization->before_optimize();

			if ($optimization->run_multisite) {
				foreach ($optimization->blogs_ids as $blog_id) {
					$optimization->switch_to_blog($blog_id);
					$optimization->optimize();
					$optimization->restore_current_blog();
				}
			} else {
				$optimization->optimize();
			}

			$optimization->after_optimize();

		}
		
		do_action('wp_optimize_after_optimization', $which_optimization, $optimization);

		$results = $optimization->get_results();
			
		return $results;
	}
	
	/**
	 * The method to call to get information about an optimization.
	 * As with do_optimization, it is somewhat modelled after the template interface
	 *
	 * @param  string|object $which_optimization An optimization ID, or a WP_Optimization object.
	 * @return array                             returns the optimization information
	 */
	public function get_optimization_info($which_optimization) {
	
		$optimization = (is_object($which_optimization) && is_a($which_optimization, 'WP_Optimization')) ? $which_optimization : $this->get_optimization($which_optimization);
		
		if (is_wp_error($optimization)) return $optimization;

		WP_Optimize()->change_time_limit();

		$optimization->before_get_info();

		if ($optimization->run_multisite) {
			foreach ($optimization->blogs_ids as $blog_id) {
				$optimization->switch_to_blog($blog_id);
				$optimization->get_info();
				$optimization->restore_current_blog();
			}
		} else {
			$optimization->get_info();
		}

		$optimization->after_get_info();

		return $optimization->get_results();
	}
	
	/**
	 * THis runs the list of optimizations.
	 *
	 * @param  array  $optimization_options Whether to do an optimization depends on what keys are set (legacy - can be changed hopefully).
	 * @param  string $which_option         Specify which option.
	 * @return array                        Returns an array of result objects.
	 */
	public function do_optimizations($optimization_options, $which_option = 'dom') {
	
		$results = array();
		
		if (empty($optimization_options)) return $results;
	
		$optimizations = $this->sort_optimizations($this->get_optimizations(), 'run_sort_order');

		foreach ($optimizations as $optimization_id => $optimization) {
			$option_id = call_user_func(array($optimization, 'get_'.$which_option.'_id'));
			
			if (isset($optimization_options[$option_id])) {
				// if options saved as a string then compare with string (for support different versions)
				if (is_string($optimization_options[$option_id]) && 'false' === $optimization_options[$option_id]) continue;

				if ('auto' == $which_option && empty($optimization->available_for_auto)) continue;

				WP_Optimize()->change_time_limit();

				$results[$optimization_id] = $this->do_optimization($optimization);
			}
		}

		// Run action after all optimizations completed.
		do_action('wp_optimize_after_optimizations');

		return $results;
		
	}
	
	public function get_table_prefix($allow_override = false) {
		$wpdb = $GLOBALS['wpdb'];
		if (is_multisite() && !defined('MULTISITE')) {
			// In this case (which should only be possible on installs upgraded from pre WP 3.0 WPMU), $wpdb->get_blog_prefix() cannot be made to return the right thing. $wpdb->base_prefix is not explicitly marked as public, so we prefer to use get_blog_prefix if we can, for future compatibility.
			$prefix = $wpdb->base_prefix;
		} else {
			$prefix = $wpdb->get_blog_prefix(0);
		}
		return ($allow_override) ? apply_filters('wp_optimize_get_table_prefix', $prefix) : $prefix;
	}

	/**
	 * Returns information about database tables.
	 *
	 * @param bool $update refresh or no cached data
	 *
	 * @return mixed
	 */
	public function get_tables($update = false) {
		static $tables_info = null;

		if (false === $update && null !== $tables_info) return $tables_info;

		$wpo_db_info = WP_Optimize()->get_db_info();

		$table_status = WP_Optimize()->get_db_info()->get_show_table_status($update);

		// Filter on the site's DB prefix (was not done in releases up to 1.9.1).
		$table_prefix = $this->get_table_prefix();
		
		if (is_array($table_status)) {

			$corrupted_tables_count = 0;

			foreach ($table_status as $index => $table) {
				$table_name = $table->Name;
				
				$include_table = (0 === stripos($table_name, $table_prefix));
				
				$include_table = apply_filters('wp_optimize_get_tables_include_table', $include_table, $table_name, $table_prefix);

				if (!$include_table && '' !== $table_prefix) {
					unset($table_status[$index]);
					continue;
				}

				$table_status[$index]->Engine = $wpo_db_info->get_table_type($table_name);

				$table_status[$index]->is_optimizable = $wpo_db_info->is_table_optimizable($table_name);
				$table_status[$index]->is_type_supported = $wpo_db_info->is_table_type_optimize_supported($table_name);
				// add information about corrupted tables.
				$is_needing_repair = $wpo_db_info->is_table_needing_repair($table_name);
				$table_status[$index]->is_needing_repair = $is_needing_repair;
				if ($is_needing_repair) $corrupted_tables_count++;

				$table_status[$index] = $this->join_plugin_information($table_name, $table_status[$index]);

				$table_status[$index]->blog_id = $wpo_db_info->get_table_blog_id($table_name);
			}

			WP_Optimize()->get_options()->update_option('corrupted-tables-count', $corrupted_tables_count);
		}

		$tables_info = apply_filters('wp_optimize_get_tables', $table_status);
		return $tables_info;
	}

	/**
	 * Returns information about single table by table name.
	 *
	 * @param String $table_name table name
	 * @return Object|Boolean table information object.
	 */
	public function get_table($table_name) {
	
		$db_info = WP_Optimize()->get_db_info();
	
		$table = $db_info->get_table_status($table_name);
		
		if (false === $table) return false;

		$table->is_optimizable = $db_info->is_table_optimizable($table_name);
		$table->is_type_supported = $db_info->is_table_type_optimize_supported($table_name);
		$table->is_needing_repair = $db_info->is_table_needing_repair($table_name);

		// add information about plugins.
		$table = $this->join_plugin_information($table_name, $table);

		$table = apply_filters('wp_optimize_get_table', $table);
		return $table;
	}

	/**
	 * Add information about relationship database tables with plugins.
	 *
	 * @param {string} $table_name
	 * @param {object} $table_obj
	 *
	 * @return {object}
	 */
	public function join_plugin_information($table_name, $table_obj) {
		// set can be removed flag.
		$can_be_removed = false;
		// set WP core table flag.
		$wp_core_table = false;
		// set WP actionscheduler table flag.
		$wp_actionscheduler_table = (false !== stripos($table_name, 'actionscheduler_'));
		// add information about using table by any of installed plugins.
		$table_obj->is_using = WP_Optimize()->get_db_info()->is_table_using_by_plugin($table_name);
		// if table belongs to any plugin then add plugins status.
		$plugins = WP_Optimize()->get_db_info()->get_table_plugin($table_name);

		if (false !== $plugins) {
			// if belongs to any of plugin then we can remove table if plugin not active.
			$can_be_removed = true;

			$plugin_status = array();
			foreach ($plugins as $plugin) {
				$status = WP_Optimize()->get_db_info()->get_plugin_status($plugin);

				if (__('WordPress core', 'wp-optimize') == $plugin) $wp_core_table = true;
				// if plugin is active then we can't remove.
				if ($wp_core_table || $status['active'] || $wp_actionscheduler_table) $can_be_removed = false;

				if ($status['installed'] || $status['active'] || !$table_obj->is_using) {
					$plugin_status[] = array(
						'plugin' => $plugin,
						'status' => $status,
					);
				}
			}

			$table_obj->plugin_status = $plugin_status;
		}

		$table_obj->wp_core_table = $wp_core_table;
		$table_obj->can_be_removed = $can_be_removed;

		return $table_obj;
	}

	/**
	 * This function grabs a list of tables
	 * and information regarding each table and returns
	 * the results to optimizations-table.php and optimizationstable.php
	 *
	 * @param int $blog_id filter tables by prefix
	 *
	 * @return Array - an array of data such as table list, innodb info and data free
	 */
	public function get_table_information($blog_id = 0) {
		// Get table information.
		$tablesstatus = $this->get_tables();

		// Set defaults.
		$table_information = array();
		$table_information['total_gain'] = 0;
		$table_information['inno_db_tables'] = 0;
		$table_information['non_inno_db_tables'] = 0;
		$table_information['table_list'] = '';
		$table_information['is_optimizable'] = true;

		// Make a list of tables to optimize.
		foreach ($tablesstatus as $each_table) {
			// if $blog_id is set then filter tables
			if ($blog_id > 0 && $blog_id != $each_table->blog_id) continue;

			$table_information['table_list'] .= $each_table->Name.'|';

			// check if table type supported.
			if (!$each_table->is_type_supported) continue;

			// check if table is optimizable.
			if (!$each_table->is_optimizable) {
				$table_information['is_optimizable'] = false;
			}

			// calculate total gain value.
			$table_information['total_gain'] += $each_table->Data_free;

			// count InnoDB tables.
			if ('InnoDB' == $each_table->Engine) {
				$table_information['inno_db_tables']++;
			} else {
				$table_information['non_inno_db_tables']++;
			}
		}

		return $table_information;
	}
	
	/**
	 * What sort of linkback to enable or disable: valid values are 'trackbacks' or 'comments', and whether to enable or disable.
	 *
	 * @param string  $type   Specify the type of linkbacks.
	 * @param boolean $enable If it is enabled or disabled.
	 */
	public function enable_linkbacks($type, $enable = true) {
	
		$wpdb = $GLOBALS['wpdb'];
		$wpo_options = WP_Optimize()->get_options();
		
		$new_status = $enable ? 'open' : 'closed';
		
		switch ($type) {
			case "trackbacks":
			$thissql = "UPDATE `".$wpdb->posts."` SET ping_status='".$new_status."' WHERE post_status = 'publish' AND post_type = 'post';";
			$wpdb->query($thissql);
				break;

			case "comments":
			$thissql = "UPDATE `".$wpdb->posts."` SET comment_status='".$new_status."' WHERE post_status = 'publish' AND post_type = 'post';";
			$wpdb->query($thissql);
				break;

			default:
				break;
		}
		$wpo_options->update_option($type.'_action', array('action' => $enable, 'timestamp' => time()));

	}
	
	/**
	 * This function will return total database size and a possible gain of db in KB.
	 *
	 * @param boolean $update - Wether to update the values or not
	 * @return string total db size gained.
	 */
	public function get_current_db_size($update = false) {
		
		if (!$update && $db_size = get_transient('wpo_get_current_db_size')) {
			return $db_size;
		}

		$wp_optimize = WP_Optimize();

		$total_gain = 0;
		$total_size = 0;
		$row_usage = 0;// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $row_usage Used in the foreach below
		$data_usage = 0;
		$index_usage = 0;
		$overhead_usage = 0;// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $overhead_usage Used in the foreach below
		
		$tablesstatus = $this->get_tables();

		foreach ($tablesstatus as $tablestatus) {
			$row_usage += $tablestatus->Rows;// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $row_usage declared up above
			$total_gain += $tablestatus->Data_free;
			$data_usage += $tablestatus->Data_length;
			$index_usage += $tablestatus->Index_length;

			if ('InnoDB' != $tablestatus->Engine) {
				$overhead_usage += $tablestatus->Data_free;// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $overhead_usage declared up above
				$total_gain += $tablestatus->Data_free;
			}
		}

		$total_size = ($data_usage + $index_usage);
		$db_size = array($wp_optimize->format_size($total_size), $wp_optimize->format_size($total_gain));
		set_transient('wpo_get_current_db_size', $db_size, 3600);
		return $db_size;
	}

	/**
	 * This function will return total saved data in KB.
	 *
	 * @param  string $current How big the data is.
	 * @return string          Returns new total value.
	 */
	public function update_total_cleaned($current) {

		$options = WP_Optimize()->get_options();
	
		$previously_saved = floatval($options->get_option('total-cleaned', '0'));
		$converted_current = floatval($current);

		$total_now = strval($previously_saved + $converted_current);

		$options->update_option('total-cleaned', $total_now);

		return $total_now;
	}
	
	
	public function trackback_comment_actions($options) {
	
		$output = array();
		$messages = array();
	
		if (isset($options['comments'])) {
			if (!$options['comments']) {
				$this->enable_linkbacks('comments', false);
				$output[] = __('Comments have now been disabled on all current and previously published posts.', 'wp-optimize');
				$messages[] =  sprintf(__('All comments on existing posts were disabled at %s.', 'wp-optimize'), WP_Optimize()->format_date_time(time()));
			} else {
				$this->enable_linkbacks('comments');
				$output[] = __('Comments have now been enabled on all current and previously published posts.', 'wp-optimize');
				$messages[] =  sprintf(__('All comments on existing posts were enabled at %s.', 'wp-optimize'), WP_Optimize()->format_date_time(time()));
			}
		}
		
		if (isset($options['trackbacks'])) {
			if (!$options['trackbacks']) {
				$this->enable_linkbacks('trackbacks', false);
				$output[] = __('Trackbacks have now been disabled on all current and previously published posts.', 'wp-optimize');
				$messages[] =  sprintf(__('All trackbacks on existing posts were disabled at %s.', 'wp-optimize'), WP_Optimize()->format_date_time(time()));
			} else {
				$this->enable_linkbacks('trackbacks');
				$output[] = __('Trackbacks have now been enabled on all current and previously published posts.', 'wp-optimize');
				$messages[] =  sprintf(__('All trackbacks on existing posts were enabled at %s.', 'wp-optimize'), WP_Optimize()->format_date_time(time()));
			}
		}
		
		return array('output' => $output,'messages' => $messages);
	}

	/**
	 * Wether InnoDB tables require confirmation to be optimized
	 *
	 * @return boolean
	 */
	public function show_innodb_force_optimize() {
		$tablesstatus = $this->get_table_information();
		return false === $tablesstatus['is_optimizable'] && $tablesstatus['inno_db_tables'] > 0;
	}
}
