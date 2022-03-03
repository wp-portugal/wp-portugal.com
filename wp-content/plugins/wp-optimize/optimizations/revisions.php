<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

class WP_Optimization_revisions extends WP_Optimization {

	public $ui_sort_order = 1000;
	
	public $available_for_auto = true;
	
	public $auto_default = true;

	public $setting_default = true;

	public $available_for_saving = true;

	/**
	 * Prepare data for preview widget.
	 *
	 * @param array $params
	 *
	 * @return array
	 */
	public function preview($params) {

		$retention_subquery = '';

		if ('true' == $this->retention_enabled) {
			$retention_subquery = ' and post_modified < NOW() - INTERVAL ' . $this->retention_period . ' WEEK';
		}

		// get data requested for preview.
		$sql = $this->wpdb->prepare(
			"SELECT `ID`, `post_title`, `post_date`".
			" FROM `" . $this->wpdb->posts . "`".
			" WHERE post_type = 'revision'".
			$retention_subquery.
			" ORDER BY `ID` LIMIT %d, %d;",
			array(
				$params['offset'],
				$params['limit'],
			)
		);

		$posts = $this->wpdb->get_results($sql, ARRAY_A);

		// fix empty revision titles.
		if (!empty($posts)) {
			foreach ($posts as $key => $post) {
				$posts[$key]['post_title'] = '' == $post['post_title'] ? '('.__('no title', 'wp-optimize').')' : $post['post_title'];
			}
		}

		// get total count revisions for optimization.
		$sql = "SELECT COUNT(*) FROM `" . $this->wpdb->posts . "` WHERE post_type = 'revision'".$retention_subquery.";";

		$total = $this->wpdb->get_var($sql);

		return array(
			'id_key' => 'ID',
			'columns' => array(
				'ID' => __('ID', 'wp-optimize'),
				'post_title' => __('Title', 'wp-optimize'),
				'post_date' => __('Date', 'wp-optimize'),
			),
			'offset' => $params['offset'],
			'limit' => $params['limit'],
			'total' => $total,
			'data' => $this->htmlentities_array($posts, array('ID')),
			'message' => $total > 0 ? '' : __('No post revisions found', 'wp-optimize'),
		);
	}

	/**
	 * Do actions after optimize() function.
	 */
	public function after_optimize() {
		$message = sprintf(_n('%s post revision deleted', '%s post revisions deleted', $this->processed_count, 'wp-optimize'), number_format_i18n($this->processed_count));

		if ($this->is_multisite_mode()) {
			$message .= ' ' . sprintf(_n('across %s site', 'across %s sites', count($this->blogs_ids), 'wp-optimize'), count($this->blogs_ids));
		}

		$this->logger->info($message);
		$this->register_output($message);
	}

	/**
	 * Do optimization.
	 */
	public function optimize() {

		if ('true' == $this->revisions_retention_enabled) {
			$this->optimize_by_posts();
			return;
		}

		$clean = "DELETE FROM `" . $this->wpdb->posts . "` WHERE post_type = 'revision'";

		if ('true' == $this->retention_enabled) {
			$clean .= '
				AND post_modified < NOW() - INTERVAL ' . $this->retention_period . ' WEEK';
		}

		// if posted ids in params, then remove only selected items. used by preview widget.
		if (isset($this->data['ids'])) {
			$clean .= ' AND `ID` in ('.join(',', $this->data['ids']).')';
		}

		$clean .= ';';

		$revisions = $this->query($clean);
		$this->processed_count += $revisions;

		// clean orphaned post meta.
		$clean = "DELETE pm FROM `" . $this->wpdb->postmeta . "` pm LEFT JOIN `" . $this->wpdb->posts . "` p ON pm.post_id = p.ID WHERE p.ID IS NULL";
		$this->query($clean);
	}

	/**
	 * Optimize post revisions but keep `x` revisions for each post
	 */
	private function optimize_by_posts() {
		// get data requested for preview.
		$sql = "SELECT `post_parent`, GROUP_CONCAT(`ID`)".
			" FROM `" . $this->wpdb->posts . "`".
			" WHERE post_type = 'revision'".
			" GROUP BY `post_parent`".
			" ORDER BY `post_parent`";

		$results = $this->wpdb->get_results($sql, ARRAY_N);
		$post_parents = array();
		$revisions = '';
		foreach ($results as $row) {
			array_push($post_parents, $row[0]);
			$tmp = explode(',', $row[1]);
			rsort($tmp);
			$tmp = implode(',', array_slice($tmp, $this->revisions_retention_count));
			if ('' !== $tmp) {
				$revisions .= $tmp . ',';
			}
		}
		$revisions = rtrim($revisions, ',');
		$revisions = explode(',', $revisions);

		while (count($revisions) > 0) {
			$delete_this_time = array_splice($revisions, 0, min(count($revisions), 250));
			$clean = "DELETE FROM `" . $this->wpdb->posts . "` WHERE `ID` IN (" . implode(',', $delete_this_time) . ")";
			$count = $this->query($clean);
			$this->processed_count += $count;
		}

		// clean orphaned post meta.
		$clean = "DELETE pm FROM `" . $this->wpdb->postmeta . "` pm LEFT JOIN `" . $this->wpdb->posts . "` p ON pm.post_id = p.ID WHERE p.ID IS NULL";
		$this->query($clean);
	}

	/**
	 * Do actions after get_info() function.
	 */
	public function after_get_info() {
		if ($this->found_count > 0) {
			$message = sprintf(_n('%s post revision in your database', '%s post revisions in your database', $this->found_count, 'wp-optimize'), number_format_i18n($this->found_count));
		} else {
			$message = __('No post revisions found', 'wp-optimize');
		}

		if ($this->is_multisite_mode()) {
			$message .= ' '.sprintf(_n('across %s site', 'across %s sites', count($this->blogs_ids), 'wp-optimize'), count($this->blogs_ids));
		}

		// add preview link to message.
		if ($this->found_count > 0) {
			$message = $this->get_preview_link($message);
		}

		$this->register_output($message);
	}
	
	public function get_info() {
		if ('true' == $this->revisions_retention_enabled) {
			$sql = "SELECT `post_parent`, GROUP_CONCAT(`ID`)".
			" FROM `" . $this->wpdb->posts . "`".
			" WHERE post_type = 'revision'".
			" GROUP BY `post_parent`".
			" ORDER BY `post_parent`";

			$results = $this->wpdb->get_results($sql, ARRAY_N);
			$post_parents = array();
			$revisions = '';
			foreach ($results as $row) {
				array_push($post_parents, $row[0]);
				$tmp = explode(',', $row[1]);
				rsort($tmp);
				$tmp = implode(',', array_slice($tmp, $this->revisions_retention_count));
				if (!empty($tmp)) {
					$revisions .= $tmp . ',';
				}
			}
			$revisions = rtrim($revisions, ',');
			if (!empty($revisions)) {
				$revisions = explode(',', $revisions);
				$this->found_count += count($revisions);
			}
		} else {
			$sql = "SELECT COUNT(*) FROM `" . $this->wpdb->posts . "` WHERE post_type = 'revision'";

			if ('true' == $this->retention_enabled) {
				$sql .= ' and post_modified < NOW() - INTERVAL ' . $this->retention_period . ' WEEK';
			}
			$sql .= ';';
	
			$revisions = $this->wpdb->get_var($sql);
	
			$this->found_count += $revisions;
		}
	}
	
	/**
	 * Returns appropriate label string based on option value
	 *
	 * @return string
	 */
	public function settings_label() {
	
		if ('true' == $this->retention_enabled && 'true' == $this->revisions_retention_enabled) {
			return sprintf(__('Clean post revisions which are older than %d weeks and keep at least %d revisions', 'wp-optimize'), $this->retention_period, $this->revisions_retention_count);
		}

		if ('true' == $this->retention_enabled && 'false' == $this->revisions_retention_enabled) {
			return sprintf(__('Clean post revisions which are older than %d weeks', 'wp-optimize'), $this->retention_period);
		}

		if ('false' == $this->retention_enabled && 'true' == $this->revisions_retention_enabled) {
			return sprintf(__('Clean post revisions but keep at least %d revisions', 'wp-optimize'), $this->revisions_retention_count);
		}

		return __('Clean all post revisions', 'wp-optimize');
	}

	public function get_auto_option_description() {
		return __('Clean all post revisions', 'wp-optimize');
	}
}
