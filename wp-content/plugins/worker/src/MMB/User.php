<?php

/*************************************************************
 * user.class.php
 * Add Users
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/
class MMB_User extends MMB_Core
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_users($args)
    {
        global $wpdb;

        //$args: $user_roles;
        if (empty($args)) {
            return false;
        }

        $user_roles      = isset($args['user_roles']) ? $args['user_roles'] : array();
        $username_filter = isset($args['username_filter']) ? $args['username_filter'] : '';

        $userlevels    = array();
        $level_strings = array();
        foreach ($user_roles as $user_role) {
            switch (strtolower($user_role)) {
                case 'subscriber' :
                    $userlevels[]    = 0;
                    $level_strings[] = $user_role;
                    break;
                case 'contributor' :
                    $userlevels[]    = 1;
                    $level_strings[] = $user_role;
                    break;
                case 'author' :
                    $userlevels[]    = 2;
                    $level_strings[] = $user_role;
                    break;
                case 'editor' :
                    $userlevels[]    = 7;
                    $level_strings[] = $user_role;
                    break;
                case 'administrator' :
                    $userlevels[]    = 10;
                    $level_strings[] = $user_role;
                    break;
                default:
                    break;
            }
        }

        $users         = array();
        $userlevel_qry = "('".implode("','", $userlevels)."')";
        $queryOR       = '';
        if (!empty($level_strings)) {
            foreach ($level_strings as $level) {
                if (!empty($queryOR)) {
                    $queryOR .= ' OR ';
                }
                $queryOR .= "meta_value LIKE '%{$level}%'";
            }
        }
        $field  = $wpdb->prefix."capabilities";
        $field2 = $wpdb->prefix."user_level";

        $metaQuery  = "SELECT * from {$wpdb->usermeta} WHERE meta_key = '{$field}' AND ({$queryOR})";
        $user_metas = $wpdb->get_results($metaQuery);

        if ($user_metas == false || empty($user_metas)) {
            $metaQuery  = "SELECT * from {$wpdb->usermeta} WHERE meta_key = '{$field2}' AND meta_value IN {$userlevel_qry}";
            $user_metas = $wpdb->get_results($metaQuery);
        }

        $include = array(0 => 0);
        if (is_array($user_metas) && !empty($user_metas)) {
            foreach ($user_metas as $user_meta) {
                $include[] = $user_meta->user_id;
            }
        }

        $args            = array(0, 0);
        $args['include'] = $include;
        $args['fields']  = 'all_with_meta';
        if (!empty($username_filter)) {
            $args['search'] = $username_filter;
        }
        $temp_users = get_users($args);
        $user       = array();
        foreach ((array) $temp_users as $temp) {
            $user['user_id']         = $temp->ID;
            $user['user_login']      = $temp->user_login;
            $user['wp_capabilities'] = array_keys($temp->$field);
            $users[]                 = $user;
        }

        return array('users' => $users);
    }

    public function add_user($args)
    {
        if (!function_exists('username_exists') || !function_exists('email_exists')) {
            include_once ABSPATH.WPINC.'/registration.php';
        }

        if (username_exists($args['user_login'])) {
            return array('error' => 'Username already exists');
        }

        if (email_exists($args['user_email'])) {
            return array('error' => 'Email already exists');
        }

        if (!function_exists('wp_insert_user')) {
            include_once ABSPATH.'wp-admin/includes/user.php';
        }

        $user_id = wp_insert_user($args);

        if ($user_id) {
            if ($args['email_notify']) {
                //require_once ABSPATH . WPINC . '/pluggable.php';
                wp_new_user_notification($user_id, $args['user_pass']);
            }

            return $user_id;
        } else {
            return array('error' => 'User not added. Please try again.');
        }
    }

    public function edit_users($args)
    {
        if (empty($args)) {
            return false;
        }
        if (!function_exists('get_user_to_edit')) {
            include_once ABSPATH.'wp-admin/includes/user.php';
        }
        if (!function_exists('wp_update_user')) {
            include_once ABSPATH.WPINC.'/user.php';
        }

        extract($args);
        //$args: $users, $new_role, $new_password, $user_edit_action
        // if action is edit-user $args are: $users, $new_role, $new_password, $user_edit_action, $new_first_name, $new_last_name, $new_user_email, $new_description, $new_user_url

        $return = array();
        if (count($users)) {
            foreach ($users as $user) {
                $result   = '';
                $user_obj = $this->mmb_get_user_info($user);
                if ($user_obj != false) {
                    switch ($user_edit_action) {
                        case 'change-password':
                            if ($new_password) {
                                $user_data             = array();
                                $userdata['user_pass'] = $new_password;
                                $userdata['ID']        = $user_obj->ID;
                                $result                = wp_update_user($userdata);
                            } else {
                                $result = array('error' => 'No password provided.');
                            }
                            break;
                        case 'change-role':
                            if ($new_role) {
                                if ($user != $username) {
                                    if (!$this->last_admin($user_obj)) {
                                        $user_data        = array();
                                        $userdata['ID']   = $user_obj->ID;
                                        $userdata['role'] = strtolower($new_role);
                                        $result           = wp_update_user($userdata);
                                    } else {
                                        $result = array('error' => 'Cannot change role to the only one left admin user.');
                                    }
                                } else {
                                    $result = array('error' => 'Cannot change role to user assigned for ManageWP.');
                                }
                            } else {
                                $result = array('error' => 'No role provided.');
                            }
                            break;
                        case 'change-description':
                            $userdata                = array();
                            $userdata['ID']          = $user_obj->ID;
                            $userdata['description'] = trim($change_description);
                            $result                  = wp_update_user($userdata);
                            break;
                        case 'delete-user':
                            if ($user != $username) {
                                if (!$this->last_admin($user_obj)) {
                                    if ($reassign_user) {
                                        $to_user = $this->mmb_get_user_info($reassign_user);
                                        if ($to_user != false) {
                                            $result = wp_delete_user($user_obj->ID, $to_user->ID);
                                        } else {
                                            $result = array('error' => 'User not deleted. User to reassign posts doesn\'t exist.');
                                        }
                                    } else {
                                        $result = wp_delete_user($user_obj->ID);
                                    }
                                } else {
                                    $result = array('error' => 'Cannot delete the only one left admin user.');
                                }
                            } else {
                                $result = array('error' => 'Cannot delete user assigned for ManageWP.');
                            }

                            break;
                        case 'edit-user':
                            if (!$new_user_email) {
                                $result = array('error' => 'No email provided.');
                                break;
                            }

                            if (!$new_role) {
                                $result = array('error' => 'No role provided.');
                                break;
                            }

                            if ($user == $username) {
                                $result = array('error' => 'Cannot change role to user assigned for ManageWP.');
                                break;
                            }

                            if ($this->last_admin($user_obj) && $new_role != 'administrator') {
                                $result = array('error' => 'Cannot change role to the only one left admin user.');
                                break;
                            }

                            $userdata       = array();
                            $userdata['ID'] = $user_obj->ID;

                            if ($new_password) {
                                $userdata['user_pass'] = $new_password;
                            }

                            $userdata['first_name']  = $new_first_name;
                            $userdata['last_name']   = $new_last_name;
                            $userdata['user_email']  = $new_user_email;
                            $userdata['role']        = strtolower($new_role);
                            $userdata['description'] = trim($new_description);
                            $userdata['user_url']    = $new_user_url;
                            $result                  = wp_update_user($userdata);
                            break;
                        default:
                            $result = array('error' => 'Wrong action provided. Please try again.');
                            break;
                    }
                } else {
                    $result = array('error' => 'User not found.');
                }

                if (is_wp_error($result)) {
                    $result = array('error' => $result->get_error_message());
                }

                $return[$user] = $result;
            }
        }

        return $return;
    }

    //Check if user is the only one admin on the site
    public function last_admin($user_obj)
    {
        global $wpdb;
        $field        = $wpdb->prefix."capabilities";
        $capabilities = array_map('strtolower', array_keys($user_obj->$field));
        $result       = count_users();
        if (in_array('administrator', $capabilities)) {
            if (!function_exists('count_users')) {
                include_once ABSPATH.WPINC.'/user.php';
            }

            $result = count_users();
            if ($result['avail_roles']['administrator'] == 1) {
                return true;
            }
        }

        return false;
    }
}
