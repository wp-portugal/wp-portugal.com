<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

class WP_Optimization_optimizetables extends WP_Optimization {

	protected $auto_id = 'optimize';
	
	protected $setting_id = 'optimize';

	protected $dom_id = 'optimize-db';

	public $available_for_saving = true;

	public $available_for_auto = true;

	public $setting_default = true;

	public $changes_table_data = true;

	public $ui_sort_order = 500;

	public $run_sort_order = 100000;

	public $run_multisite = true;

	public $support_preview = false;

	private $table_information = array();

	/**
	 * Optimize method.
	 */
	public function optimize() {
		// We don't need run anything in this method to avoid issues on multisite installations.
	}

	/**
	 * Run optimization.
	 */
	public function after_optimize() {
		// check if force optimize sent.
		$force = (isset($this->data['optimization_force']) && $this->data['optimization_force']) ? true : false;

		// check if single table name posted or optimize all tables.
		if (isset($this->data['optimization_table']) && '' != $this->data['optimization_table']) {
			$table = $this->optimizer->get_table($this->data['optimization_table']);

			if (false !== $table) {
				$this->optimize_table($table, $force);
				
				// Exit if the UI elements aren't required
				if (isset($this->data['include_ui_elements']) && !$this->data['include_ui_elements']) return;

				$wp_optimize = WP_Optimize();
				$tablestatus = $wp_optimize->get_db_info()->get_table_status($table->Name, true);

				$is_optimizable = $wp_optimize->get_db_info()->is_table_optimizable($table->Name);

				$tableinfo = array(
					'rows' => number_format_i18n($tablestatus->Rows),
					'data_size' => $wp_optimize->format_size($tablestatus->Data_length),
					'index_size' => $wp_optimize->format_size($tablestatus->Index_length),
					'overhead' => $is_optimizable ? $wp_optimize->format_size($tablestatus->Data_free) : '-',
					'type' => $table->Engine,
					'is_optimizable' => $is_optimizable,
				);

				$this->register_meta('tableinfo', $tableinfo);

				$tables = $this->optimizer->get_tables(true);

				$overhead_usage = 0;

				foreach ($tables as $table) {
					if ($table->is_optimizable) {
						$overhead_usage += $table->Data_free;
					}
				}

				$this->register_meta('overhead', $overhead_usage);
				$this->register_meta('overhead_formatted', $wp_optimize->format_size($overhead_usage));
			} else {
				$this->register_meta('error', 1);
				$this->register_meta('message', sprintf(__('The table "%s" does not exist.', 'wp-optimize'), $this->data['optimization_table']));
				return false;

			}
		} else {
			$tables = $this->optimizer->get_tables();

			foreach ($tables as $table) {
				$this->optimize_table($table, $force);
			}
		}
	}

	/**
	 * Optimize table and generate log and output information.
	 *
	 * @param object $table_obj table object returned by $this->optimizer->get_tables().
	 * @param bool 	 $force		if true then will optimize
	 */
	private function optimize_table($table_obj, $force = false) {

		// if not forced and table is not optimizable then exit.
		if (false == $force && (false == $table_obj->is_optimizable || false == $table_obj->is_type_supported)) return;

		if ($table_obj->is_type_supported) {
			$this->logger->info('Optimizing: ' . $table_obj->Name);
			$this->query('OPTIMIZE TABLE `' . $table_obj->Name . '`');

			// For InnoDB Data_free doesn't contain free size.
			if ('InnoDB' != $table_obj->Engine) {
				$this->optimizer->update_total_cleaned(strval($table_obj->Data_free));
			}

			$this->register_output(__('Optimized table:', 'wp-optimize') . ' ' . $table_obj->Name);
		}
	}

	/**
	 * Before get_info() actions.
	 */
	public function before_get_info() {
		$this->table_information['total_gain'] = 0;
		$this->table_information['inno_db_tables'] = 0;
		$this->table_information['non_inno_db_tables'] = 0;
		$this->table_information['table_list'] = '';
		$this->table_information['is_optimizable'] = true;
	}

	/**
	 * Get information to be disbalyed onscreen before optimization.
	 */
	public function get_info() {
		$table_information = $this->optimizer->get_table_information(get_current_blog_id());

		$this->table_information['total_gain'] += $table_information['total_gain'];
		$this->table_information['inno_db_tables'] += $table_information['inno_db_tables'];
		$this->table_information['non_inno_db_tables'] += $table_information['non_inno_db_tables'];
		$this->table_information['table_list'] .= $table_information['table_list'];
		if (!$table_information['is_optimizable']) {
			$this->table_information['is_optimizable'] = false;
		}
	}

	/**
	 * Return info about optimization.
	 */
	public function after_get_info() {
		// This gathers information to be displayed onscreen before optimization.
		$tablesstatus = $this->table_information;

		// Check if database is not optimizable.
		if (false === $tablesstatus['is_optimizable']) {
			if (isset($this->data['optimization_table']) && '' != $this->data['optimization_table']) {
				// This is used for grabbing information before optimizations.
				$this->register_output(__('Total gain:', 'wp-optimize').' '.WP_Optimize()->format_size(($tablesstatus['total_gain'])));
			}

			if ($tablesstatus['inno_db_tables'] > 0) {
				// Output message for how many InnoDB tables will not be optimized.
				$this->register_output(sprintf(__('Tables using the InnoDB engine (%d) will not be optimized.'), $tablesstatus['inno_db_tables']));

				if ($tablesstatus['non_inno_db_tables'] > 0) {
					$this->register_output(sprintf(__('Other tables will be optimized (%s).', 'wp-optimize'), $tablesstatus['non_inno_db_tables']));
				}

				$faq_url = apply_filters('wpo_faq_url', 'https://getwpo.com/faqs/');
				$force_db_option = $this->options->get_option('innodb-force-optimize', 'false');
				$this->register_output('<input id="innodb_force_optimize" name="innodb-force-optimize" type="checkbox" value="true" '.checked($force_db_option, 'true').'><label for="innodb_force_optimize">'.__('Optimize InnoDB tables anyway.', 'wp-optimize').'</label><br><a href="'.$faq_url.'" target="_blank">'.__('Warning: you should read the FAQ on the risks of this operation first.', 'wp-optimize').'</a>');
			}
		} else {
			$this->register_output(sprintf(__('Tables will be optimized (%s).', 'wp-optimize'), $tablesstatus['non_inno_db_tables'] + $tablesstatus['inno_db_tables']));
		}
	}

	public function get_auto_option_description() {
		return __('Optimize database tables', 'wp-optimize');
	}
	
	public function settings_label() {
		return __('Optimize database tables', 'wp-optimize');
	}
}
