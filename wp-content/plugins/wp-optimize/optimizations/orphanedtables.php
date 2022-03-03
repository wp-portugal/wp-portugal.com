<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

class WP_Optimization_orphanedtables extends WP_Optimization {

	public $available_for_auto = false;

	public $setting_default = true;

	public $changes_table_data = true;

	public $run_multisite = false;

	private $last_message;

	/**
	 * Display or hide optimization in optimizations list.
	 *
	 * @return bool
	 */
	public function display_in_optimizations_list() {
		return false;
	}

	/**
	 * Run optimization.
	 */
	public function optimize() {
		// check if the data contain action attribute then lead to innoDb conversion.
		if (isset($this->data['optimization_action']) && 'toinnodb' == $this->data['optimization_action']) {
			$table = $this->optimizer->get_table($this->data['optimization_table']);
			if (false === $table) {
				$this->register_meta('error', 1);
				$this->register_meta('message', sprintf(__('The table "%s" does not exist.', 'wp-optimize'), $this->data['optimization_table']));
				return false;
			}
			$result = $this->convert_table($table);

			if (false === $result) {
				$this->register_meta('message', $this->last_message);
				return false;
			}
			$this->register_meta('success', 1);
			return true;
		}
		
		// check if single table name posted or optimize all tables.
		if (isset($this->data['optimization_table']) && '' != $this->data['optimization_table']) {
			$table = $this->optimizer->get_table($this->data['optimization_table']);

			if (false === $table) {
				$this->register_meta('error', 1);
				$this->register_meta('message', sprintf(__('The table "%s" does not exist.', 'wp-optimize'), $this->data['optimization_table']));
				return false;
			}

			$result = $this->delete_table($table);
		
			if (false === $result) {
				$this->register_meta('message', $this->last_message);
				return false;
			}
			$this->register_meta('success', 1);
		} else {
			// delete all orphaned tables if table name was not selected.
			$tables = $this->optimizer->get_tables();
			$deleted = 0;

			foreach ($tables as $table) {
				if ($table->is_using) continue;

				if ($this->delete_table($table)) {
					$deleted++;
				}
			}

			$this->register_output(sprintf(_n('%s orphaned table deleted', '%s orphaned tables deleted', $deleted), $deleted));

			if ($deleted > 0) {
				$this->register_output(sprintf(_n('Deleting %s orphaned table was unsuccessful', 'Repairing %s orphaned tables were unsuccessful', $deleted), $deleted));
			}
		}
	}
			
			
	/**
	 * Alter table engine to InnoDB.
	 *
	 * @param object $table_obj object contains information about database table.
	 *
	 * @return bool
	 */
	private function convert_table($table_obj) {
		global $wpdb;
		$inno_db = 0;
		
		// check InnoDB is Active
		$mysql_engine = $wpdb->get_results('SHOW ENGINES');
		foreach ($mysql_engine as $check) {
			if ('InnoDB' == $check->Engine && ('DEFAULT' == $check->Support || 'YES' == $check->Support)) {
				$inno_db=1;
			}
		}
		

		if (0 == $inno_db) return false;
		// If InnoDB is active then convert MyISAM to InnoDB.
		else {
			$table_name = sanitize_text_field($table_obj->Name);
			$sql_query = $wpdb->prepare("ALTER TABLE `%1s`  ENGINE=InnoDB", $table_name);
			$this->logger->info($sql_query);
			$result = $wpdb->query($sql_query);
		}
		// check if alter query finished successfully.
		if ('' != $wpdb->last_error) {
			$this->last_message = $wpdb->last_error;
			$this->logger->info($wpdb->last_error);
		}
		
		return $result;
	}

	/**
	 * Drop table from database.
	 *
	 * @param object $table_obj object contains information about database table.
	 *
	 * @return bool
	 */
	private function delete_table($table_obj) {
		global $wpdb;

		// don't delete table if it in use and plugin active.
		if (!$table_obj->can_be_removed) return true;

		$table_name = sanitize_text_field($table_obj->Name);
		$sql_query = $wpdb->prepare("DROP TABLE `%1s`", $table_name);

		$this->logger->info($sql_query);

		$result = $wpdb->query($sql_query);

		// check if drop query finished successfully.
		if ('' != $wpdb->last_error) {
			$this->last_message = $wpdb->last_error;
			$this->logger->info($wpdb->last_error);
		}

		return $result;
	}

	/**
	 * Get count of unused database tables, i.e. not using by any of installed plugin.
	 *
	 * @return int
	 */
	public function get_unused_tables_count() {
		$tablesinfo = $this->optimizer->get_tables();

		$unused_tables = 0;

		if (!empty($tablesinfo)) {
			foreach ($tablesinfo as $tableinfo) {
				if (false == $tableinfo->is_using) {
					$unused_tables++;
				}
			}
		}

		return $unused_tables;
	}

	/**
	 * Register info about optimization.
	 */
	public function get_info() {

		$corrupted_tables = $this->get_unused_tables_count();

		if (0 == $corrupted_tables) {
			$this->register_output(__('No corrupted tables found', 'wp-optimize'));
		} else {
			$this->register_output(sprintf(_n('%s corrupted table found', '%s corrupted tables found', $corrupted_tables), $corrupted_tables));
		}
	}

	/**
	 * Returns settings label.
	 *
	 * @return string
	 */
	public function settings_label() {
		return __('Delete orphaned database tables', 'wp-optimize');
	}
}
