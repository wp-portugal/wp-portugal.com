<?php

/*************************************************************
 * comment.class.php
 * Get comments
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/
class MMB_Comment extends MMB_Core
{
    public function get_comments($args)
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        $where = '';

        extract($args);

        if (!empty($filter_comments)) {
            $where .= " AND (c.comment_author LIKE '%".esc_sql($filter_comments)."%' OR c.comment_content LIKE '%".esc_sql($filter_comments)."%')";
        }
        $comment_array    = array();
        $comment_statuses = array('approved', 'pending', 'spam', 'trash');
        foreach ($args as $checkbox => $checkbox_val) {
            if ($checkbox_val == "on") {
                $status_val = str_replace("mwp_get_comments_", "", $checkbox);
                if ($status_val == 'approved') {
                    $status_val = 1;
                } elseif ($status_val == 'pending') {
                    $status_val = 0;
                }
                $comment_array[] = "'".$status_val."'";
            }
        }
        if (!empty($comment_array)) {
            $where .= " AND c.comment_approved IN (".implode(",", $comment_array).")";
        }

        $sql_query = "$wpdb->comments as c, $wpdb->posts as p WHERE c.comment_post_ID = p.ID ".$where;

        $comments_total    = $wpdb->get_results("SELECT count(*) as total_comments FROM ".$sql_query);
        $total             = $comments_total[0]->total_comments;
        $comments_approved = $this->comment_total();

        $query_comments = $wpdb->get_results("SELECT c.comment_ID, c.comment_post_ID, c.comment_author, c.comment_author_email, c.comment_author_url, c.comment_author_IP, c.comment_date, c.comment_content, c.comment_approved, c.comment_parent, p.post_title, p.post_type, p.guid FROM ".$sql_query." ORDER BY c.comment_date DESC LIMIT 500");
        $comments       = array();
        foreach ($query_comments as $comments_info) {
            $comment_total_approved = 0;
            if (isset($comments_approved[$comments_info->comment_post_ID]['approved'])) {
                $comment_total_approved = $comments_approved[$comments_info->comment_post_ID]['approved'];
            }
            $comment_total_pending = 0;
            if (isset($comments_approved[$comments_info->comment_post_ID]['pending'])) {
                $comment_total_pending = $comments_approved[$comments_info->comment_post_ID]['pending'];
            }
            $comment_parent_author = '';
            if ($comments_info->comment_parent > 0) {
                $select_parent_author     = "SELECT comment_author FROM $wpdb->comments WHERE comment_ID = ".$comments_info->comment_parent;
                $select_parent_author_res = $wpdb->get_row($select_parent_author);
                $comment_parent_author    = $select_parent_author_res->comment_author;
            }

            $comments[$comments_info->comment_ID] = array(
                "comment_post_ID"        => $comments_info->comment_post_ID,
                "comment_author"         => $comments_info->comment_author,
                "comment_author_email"   => $comments_info->comment_author_email,
                "comment_author_url"     => $comments_info->comment_author_url,
                "comment_author_IP"      => $comments_info->comment_author_IP,
                "comment_date"           => $comments_info->comment_date,
                "comment_content"        => htmlspecialchars($comments_info->comment_content),
                "comment_approved"       => $comments_info->comment_approved,
                "comment_parent"         => $comments_info->comment_parent,
                "comment_parent_author"  => $comment_parent_author,
                "post_title"             => htmlspecialchars($comments_info->post_title),
                "post_type"              => $comments_info->post_type,
                "guid"                   => $comments_info->guid,
                "comment_total_approved" => $comment_total_approved,
                "comment_total_pending"  => $comment_total_pending,
            );
        }

        return array('comments' => $comments, 'total' => $total);
    }

    public function comment_total()
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $totals                    = array();
        $select_total_approved     = "SELECT COUNT(*) as total, p.ID FROM $wpdb->comments as c, $wpdb->posts as p WHERE c.comment_post_ID = p.ID AND c.comment_approved = 1 GROUP BY p.ID";
        $select_total_approved_res = $wpdb->get_results($select_total_approved);

        if (!empty($select_total_approved_res)) {
            foreach ($select_total_approved_res as $row) {
                $totals[$row->ID]['approved'] = $row->total;
            }
        }
        $select_total_pending     = "SELECT COUNT(*) as total, p.ID FROM $wpdb->comments as c, $wpdb->posts as p WHERE c.comment_post_ID = p.ID AND c.comment_approved = 0 GROUP BY p.ID";
        $select_total_pending_res = $wpdb->get_results($select_total_pending);
        if (!empty($select_total_pending_res)) {
            foreach ($select_total_pending_res as $row) {
                $totals[$row->ID]['pending'] = $row->total;
            }
        }

        return $totals;
    }

    public function bulk_action_comments($args)
    {
        $docomaction = $args['docomaction'];

        foreach ($args as $val) {
            if (!empty($val) && is_numeric($val)) {
                if ($docomaction == 'delete') {
                    wp_delete_comment($val, true);
                } elseif ($docomaction == 'unapprove' || $docomaction == 'untrash' || $docomaction == 'unspam') {
                    wp_set_comment_status($val, 'hold');
                } elseif ($docomaction == 'approve') {
                    wp_set_comment_status($val, 'approve');
                } elseif ($docomaction == 'spam') {
                    wp_set_comment_status($val, 'spam');
                } elseif ($docomaction == 'trash') {
                    wp_set_comment_status($val, 'trash');
                }
            }
        }

        return "comments updated";
    }
}
