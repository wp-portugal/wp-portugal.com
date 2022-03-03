<?php

function mwp_autoload($class)
{
    if (substr($class, 0, 8) === 'Symfony_'
        || substr($class, 0, 8) === 'Monolog_'
        || substr($class, 0, 5) === 'Gelf_'
        || substr($class, 0, 4) === 'MWP_'
        || substr($class, 0, 4) === 'MMB_'
    ) {
        $file = dirname(__FILE__).'/src/'.str_replace('_', '/', $class).'.php';
        if (file_exists($file)) {
            include_once $file;
        }
    }
}

/**
 * @return Monolog_Psr_LoggerInterface
 */
function mwp_logger()
{
    return mwp_container()->getLogger();
}

/**
 * @return MWP_WordPress_Context
 */
function mwp_context()
{
    return mwp_container()->getWordPressContext();
}

function mwp_worker_configuration()
{
    return mwp_container()->getConfiguration();
}

function mwp_format_memory_limit($limit)
{
    if ((string)(int)$limit === (string)$limit) {
        // The number is numeric.
        return mwp_format_bytes($limit);
    }

    $units = strtolower(substr($limit, -1));

    if (!in_array($units, array('b', 'k', 'm', 'g'))) {
        // Invalid size unit.
        return $limit;
    }

    $number = substr($limit, 0, -1);

    if ((string)(int)$number !== $number) {
        // The number isn't numeric.
        return $number;
    }

    switch ($units) {
        case 'g':
            return $number.' GB';
        case 'm':
            return $number.' MB';
        case 'k':
            return $number.' KB';
        case 'b':
        default:
            return $number.' B';
    }
}

function mwp_format_bytes($bytes)
{
    $bytes = (int)$bytes;

    if ($bytes > 1024 * 1024 * 1024) {
        return round($bytes / 1024 / 1024 / 1024, 2).' GB';
    } elseif ($bytes > 1024 * 1024) {
        return round($bytes / 1024 / 1024, 2).' MB';
    } elseif ($bytes > 1024) {
        return round($bytes / 1024, 2).' KB';
    }

    return $bytes.' B';
}

function cleanup_delete_worker($params = array())
{
    $revision_params = get_option('mmb_stats_filter');
    $revision_limit  = isset($revision_params['plugins']['cleanup']['revisions']) ? str_replace('r_', '', $revision_params['plugins']['cleanup']['revisions']) : 5;

    $params_array = explode('_', $params['actions']);
    $return_array = array();

    foreach ($params_array as $param) {
        switch ($param) {
            case 'revision':
                if (mmb_delete_all_revisions($revision_limit)) {
                    $return_array['revision'] = 'OK';
                } else {
                    $return_array['revision_error'] = 'OK, nothing to do';
                }
                break;
            case 'overhead':
                if (mmb_handle_overhead(true)) {
                    $return_array['overhead'] = 'OK';
                } else {
                    $return_array['overhead_error'] = 'OK, nothing to do';
                }
                break;
            case 'comment':
                if (mmb_delete_spam_comments()) {
                    $return_array['comment'] = 'OK';
                } else {
                    $return_array['comment_error'] = 'OK, nothing to do';
                }
                break;
            default:
                break;
        }
    }

    unset($params);

    mmb_response($return_array, true);
}

function mmb_num_revisions($leaveRevsPerPost)
{
    global $wpdb;

    $query = "SELECT SUM(t.cnt) FROM (SELECT COUNT(ID) - {$leaveRevsPerPost} as cnt FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent != 0 GROUP BY post_parent HAVING COUNT(ID) > {$leaveRevsPerPost}) as t";

    return $wpdb->get_var($query);
}

function mmb_delete_all_revisions($filter)
{
    global $wpdb;

    $num_rev = isset($filter['num_to_keep']) ? (int)str_replace("r_", "", $filter['num_to_keep']) : 5;

    $allRevisions = $wpdb->get_results("SELECT post_parent FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent != 0 GROUP BY post_parent HAVING COUNT(ID) > {$num_rev}");

    if (!is_array($allRevisions)) {
        return false;
    }

    foreach ($allRevisions as $revision) {
        $toKeep = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent = '{$revision->post_parent}' ORDER BY post_date DESC LIMIT ".$num_rev);

        $keepArray = array();
        foreach ($toKeep as $keep) {
            $keepArray[] = $keep->ID;
        }

        if (empty($keepArray)) {
            continue;
        }

        $keepQuery = implode(', ', $keepArray);
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent = '{$revision->post_parent}' AND ID NOT IN ({$keepQuery})");
    }

    return true;
}

function mmb_handle_overhead($clear = false)
{
    /** @var wpdb $wpdb */
    global $wpdb;
    $query            = 'SHOW TABLE STATUS';
    $tables           = $wpdb->get_results($query, ARRAY_A);
    $tableOverhead    = 0;
    $tablesToOptimize = array();

    foreach ($tables as $table) {
        if (!isset($table['Engine']) || $table['Engine'] !== 'MyISAM' || $table['Data_free'] == 0) {
            continue;
        }

        if ($wpdb->base_prefix === $wpdb->prefix && !preg_match('/^'.preg_quote($wpdb->prefix).'/Ui', $table['Name'])) {
            continue;
        }

        if ($wpdb->base_prefix !== $wpdb->prefix && !preg_match('/^'.preg_quote($wpdb->prefix).'\d+_/Ui', $table['Name'])) {
            continue;
        }

        $tableOverhead      += $table['Data_free'] / 1024;
        $tablesToOptimize[] = $table['Name'];
    }

    if (!$clear) { // we should only return the overhead
        return round($tableOverhead, 3);
    }

    $optimize = true;

    foreach ($tablesToOptimize as $tableToOptimize) {
        $query    = 'OPTIMIZE TABLE '.$tableToOptimize;
        $optimize = ((bool)$wpdb->query($query)) && $optimize;
    }

    return $optimize;
}

function mmb_num_spam_comments()
{
    global $wpdb;
    $sql       = "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = 'spam'";
    $num_spams = $wpdb->get_var($sql);

    return $num_spams;
}

function mmb_delete_spam_comments()
{
    global $wpdb;
    $spam  = 1;
    $total = 0;
    while (!empty($spam)) {
        $getCommentsQuery = "SELECT * FROM $wpdb->comments WHERE comment_approved = 'spam' LIMIT 1000";
        $spam             = $wpdb->get_results($getCommentsQuery);

        if (empty($spam)) {
            break;
        }

        $commentIds = array();
        foreach ($spam as $comment) {
            $commentIds[] = $comment->comment_ID;

            // Avoid queries to comments by caching the comment.
            // Plugins which hook to 'delete_comment' might call get_comment($id), which in turn returns the cached version.
            wp_cache_add($comment->comment_ID, $comment, 'comment');
            do_action('delete_comment', $comment->comment_ID);
            wp_cache_delete($comment->comment_ID, 'comment');
        }

        $commentIdsList = implode(', ', array_map('intval', $commentIds));
        $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_ID IN ($commentIdsList)");
        $wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE comment_id IN ($commentIdsList)");

        $total += count($spam);
        if (!empty($spam)) {
            usleep(10000);
        }
    }

    return $total;
}

function mwp_is_nio_shell_available()
{
    static $check;
    if (isset($check)) {
        return $check;
    }
    try {
        $process = new Symfony_Process_Process("cd .", dirname(__FILE__), array(), null, 1);
        $process->run();
        $check = $process->isSuccessful();
    } catch (Exception $e) {
        $check = false;
    }

    return $check;
}

function mwp_is_shell_available()
{
    if (mwp_container()->getParameter('disable_shell')) {
        return false;
    }

    if (mwp_is_safe_mode()) {
        return false;
    }
    if (!function_exists('proc_open') || !function_exists('escapeshellarg')) {
        return false;
    }

    $neededFunction   = array('proc_get_status', 'proc_open');
    $disabledFunction = mwp_get_disabled_functions();

    if (count(array_diff($neededFunction, $disabledFunction)) != count($neededFunction)) {
        return false;
    }

    if (!mwp_is_nio_shell_available()) {
        return false;
    }

    return true;
}

function mwp_get_disabled_functions()
{
    $list = array_merge(explode(',', ini_get('disable_functions')), explode(',', ini_get('suhosin.executor.func.blacklist')));
    $list = array_map('trim', $list);
    $list = array_map('strtolower', $list);
    $list = array_filter($list);

    return $list;
}

function mwp_is_safe_mode()
{
    $value = ini_get("safe_mode");
    if ((int)$value === 0 || strtolower($value) === "off") {
        return false;
    }

    return true;
}

function mmb_response($response = false, $success = true)
{
    if (!$success) {
        if (!is_scalar($response)) {
            $response = json_encode($response);
        }
        throw new MWP_Worker_Exception(MWP_Worker_Exception::GENERAL_ERROR, $response);
    }

    throw new MWP_Worker_ActionResponse($response);
}

function mmb_remove_site($params)
{
    mwp_core()->deactivate(false, false);
    mwp_remove_current_key();

    mmb_response(
        array(
            'removed_data' => 'Site removed successfully. <br /><br /><b>ManageWP Worker plugin was not deactivated.</b>',
        ),
        true
    );
}

function mwp_get_stats(array $params)
{
    mwp_context()->requireWpRewrite();
    mwp_context()->requireTaxonomies();
    mwp_context()->requirePostTypes();
    mwp_context()->requireTheme();

    mwp_logger()->debug('Starting get_stats after everything was required');

    $return = mwp_core()->get_stats_instance()->get_stats($params);
    mmb_response($return);
}

function mmb_update_worker_plugin($params)
{
    if (!empty($params['version'])) {
        $recoveryKit = new MwpRecoveryKit();
        update_option('mwp_incremental_update_active', time());
        try {
            $files = $recoveryKit->recover($params['version']);
        } catch (Exception $e) {
            update_option('mwp_incremental_update_active', '');
            throw $e;
        }
        update_option('mwp_incremental_update_active', '');
        mmb_response(array('files' => $files, 'success' => 'ManageWP Worker plugin successfully updated'), true);
    } else {
        mmb_response(mwp_core()->update_worker_plugin($params), true);
    }
}

function mmb_install_addon($params)
{
    mwp_context()->requireTheme();
    mwp_load_required_components();
    $return = mwp_core()->get_installer_instance()->install_remote_file($params);
    mmb_response($return, true);
}

function mmb_do_upgrade($params)
{
    mwp_context()->requireTheme();
    $return = mwp_core()->get_installer_instance()->do_upgrade($params);
    mmb_response($return, true);
}

function mmb_bulk_action_comments($params)
{
    $return = mwp_core()->get_comment_instance()->bulk_action_comments($params);
    if (is_array($return) && array_key_exists('error', $return)) {
        mmb_response($return['error'], false);
    } else {
        mmb_response($return, true);
    }
}

function mmb_add_user($params)
{
    $return = mwp_core()->get_user_instance()->add_user($params);
    if (is_array($return) && array_key_exists('error', $return)) {
        mmb_response($return['error'], false);
    } else {
        mmb_response($return, true);
    }
}

function mmb_edit_users($params)
{
    $users       = mwp_core()->get_user_instance()->edit_users($params);
    $response    = 'User updated.';
    $check_error = false;
    foreach ($users as $username => $user) {
        $check_error = is_array($user) && array_key_exists('error', $user);
        if ($check_error) {
            $response = $username.': '.$user['error'];
        }
    }
    mmb_response($response, !$check_error);
}

function mmb_iframe_plugins_fix($update_actions)
{
    foreach ($update_actions as $key => $action) {
        $update_actions[$key] = str_replace('target="_parent"', '', $action);
    }

    return $update_actions;
}

function mmb_execute_php_code($params)
{
    ob_start();
    $errorHandler = new MWP_Debug_EvalErrorHandler();
    set_error_handler(array($errorHandler, 'handleError'));

    if (!empty($params['code64'])) {
        $params['code'] = base64_decode(substr($params['code64'], 2));
    }

    $returnValue = eval($params['code']); // This code handles the "Execute PHP Snippet" functionality on ManageWP and is not a security issue.
    $errors      = $errorHandler->getErrorMessages();
    restore_error_handler();
    $return = array('output' => ob_get_clean(), 'returnValue' => $returnValue);

    if (count($errors)) {
        $return['errorLog'] = $errors;
    }

    $lastError  = error_get_last();
    $fatalError = null;

    if (($lastError !== null)
        && ($lastError['type'] & (E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR))
        && (strpos($lastError['file'], __FILE__) !== false)
        && (strpos($lastError['file'], 'eval()') !== false)
    ) {
        $return['fatalError'] = $lastError;
    }

    mmb_response($return, true);
}

function mmb_upload_file_action($params)
{
    $transactions = getUploadMessages();

    if (!file_exists($params['file_path'])) {
        mmb_response(array('message' => $transactions['path_not_exist'], 'ok' => false), true);
    }

    if (!is_writable($params['file_path'])) {
        mmb_response(array('message' => $transactions['permissions_denied'], 'ok' => false), true);
    }

    $pathName = $params['file_path'];
    if (substr($pathName, -1) !== '/') {
        $pathName = $pathName.'/';
    }
    $filePath = $pathName.$params['file_name'];

    if (file_exists($filePath) && !$params['overwrite']) {
        mmb_response(array('message' => $transactions['file_exist'], 'ok' => false), true);
    }

    $file = fopen($filePath, 'w');
    if (!fwrite($file, base64_decode($params['content']))) {
        mmb_response(array('message' => $transactions['upload_failed'], 'ok' => false), true);
    }

    fclose($file);
    $isWritable = is_writable($filePath);
    $result     = array(
        'pathName'    => $filePath,
        'fileName'    => basename($filePath),
        'date'        => filemtime($filePath),
        'permissions' => substr(sprintf('%o', fileperms($filePath)), -4),
        'fileType'    => pathinfo($filePath, PATHINFO_EXTENSION),
        'fileSize'    => filesize($filePath),
        'hasSubDir'   => false,
        'writable'    => $isWritable,
        'editable'    => $isWritable
    );

    mmb_response(array('message' => $transactions['upload_success'], 'ok' => true, 'result' => $result), true);
}

function mmb_edit_plugins_themes($params)
{
    $return = mwp_core()->get_installer_instance()->edit($params);
    mmb_response($return, true);
}

function mmb_worker_brand($params)
{
    $worker_brand       = get_option('mwp_worker_brand');
    $current_from_orion = !empty($worker_brand['from_orion']) ? $worker_brand['from_orion'] : false;
    $from_orion         = !empty($params['brand']['from_orion']) ? $params['brand']['from_orion'] : false;

    if ($from_orion === false && $current_from_orion !== $from_orion) {
        mmb_response(true, true); //@TODO: Maybe return mmb_response(true, false)
        return;
    }

    update_option("mwp_worker_brand", $params['brand']);
    mmb_response(true, true);
}

function mmb_maintenance_mode($params)
{
    global $wp_object_cache;

    $default = get_option('mwp_maintenace_mode');
    $params  = empty($default) ? $params : array_merge($default, $params);
    update_option("mwp_maintenace_mode", $params);

    if (!empty($wp_object_cache)) {
        @$wp_object_cache->flush();
    }
    mmb_response(true, true);
}

function mmb_plugin_actions()
{
    global $pagenow, $current_user, $mmode;
    if (!is_admin() && !(defined('WP_CLI') && WP_CLI) && !in_array($pagenow, array('wp-login.php'))) {
        $mmode = get_option('mwp_maintenace_mode');
        if (!empty($mmode)) {
            if (isset($mmode['active']) && $mmode['active'] == true) {
                $status_code = empty($mmode['status_code']) ? 503 : $mmode['status_code'];
                if (!empty($current_user->ID) && !empty($mmode['hidecaps'])) {
                    $usercaps = array();
                    if (isset($current_user->caps) && !empty($current_user->caps)) {
                        $usercaps = $current_user->caps;
                    }
                    foreach ($mmode['hidecaps'] as $cap => $hide) {
                        if (!$hide) {
                            continue;
                        }

                        foreach ($usercaps as $ucap => $val) {
                            if ($ucap == $cap) {
                                ob_end_clean();
                                ob_end_flush();
                                if (!headers_sent()) {
                                    if ($status_code == 503) {
                                        header(sprintf('%s 503 Service Unavailable', isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1'), true, $status_code);
                                    } else {
                                        header(sprintf('%s %d Service Unavailable', isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1', $status_code), true, $status_code);
                                    }
                                }
                                die($mmode['template']);
                            }
                        }
                    }
                } else {
                    if (!headers_sent()) {
                        if ($status_code == 503) {
                            header(sprintf('%s 503 Service Unavailable', isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1'), true, 503);
                        } else {
                            header(sprintf('%s %d Service Unavailable', isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1', $status_code), true, $status_code);
                        }
                    }
                    die($mmode['template']);
                }
            }
        }
    }

    if (file_exists(dirname(__FILE__).'/log')) {
        unlink(dirname(__FILE__).'/log');
    }
}

function mwb_edit_redirect_override($location = false, $comment_id = false)
{
    if (isset($_COOKIE[MMB_XFRAME_COOKIE])) {
        $location = get_site_url().'/wp-admin/edit-comments.php';
    }

    return $location;
}

function mwp_set_plugin_priority()
{
    $pluginBasename = 'worker/init.php';
    $activePlugins  = get_option('active_plugins');

    if (!is_array($activePlugins) || reset($activePlugins) === $pluginBasename) {
        return;
    }

    $workerKey = array_search($pluginBasename, $activePlugins);

    if ($workerKey === false || $workerKey === null) {
        return;
    }

    unset($activePlugins[$workerKey]);
    array_unshift($activePlugins, $pluginBasename);
    update_option('active_plugins', array_values($activePlugins));
}

/**
 * @return MMB_Core
 */
function mwp_core()
{
    static $core;

    if (!$core instanceof MMB_Core) {
        $core = new MMB_Core();
    }

    return $core;
}

/**
 * Auto-loads classes that may not exists after this plugin's update.
 */
function mwp_load_required_components()
{
    class_exists('MWP_Http_ResponseInterface');
    class_exists('MWP_Http_Response');
    class_exists('MWP_Http_LegacyWorkerResponse');
    class_exists('MWP_Http_JsonResponse');
    class_exists('MWP_Worker_ActionResponse');
    class_exists('MWP_Worker_Exception');
    class_exists('MWP_Event_ActionResponse');
    class_exists('MWP_Event_MasterResponse');
}

function mwp_uninstall()
{
    delete_option('mwp_core_autoupdate');
    delete_option('mwp_recovering');
    delete_option('mwp_container_parameters');
    delete_option('mwp_container_site_parameters');
    $loaderName = '0-worker.php';
    try {
        $mustUsePluginDir = rtrim(WPMU_PLUGIN_DIR, '/');
        $loaderPath       = $mustUsePluginDir.'/'.$loaderName;

        if (!file_exists($loaderPath)) {
            return;
        }

        $removed = @unlink($loaderPath);

        if (!$removed) {
            $error = error_get_last();
            throw new Exception(sprintf('Unable to remove loader: %s', $error['message']));
        }
    } catch (Exception $e) {
        mwp_logger()->error('Unable to remove loader', array('exception' => $e));
    }
}

function mwp_get_service_key()
{
    $serviceKey = mwp_context()->optionGet('mwp_service_key');
    if (empty($serviceKey)) {
        $serviceKey = mwp_generate_uuid4();
        mwp_context()->optionSet('mwp_service_key', $serviceKey, true);
    }

    return $serviceKey;
}

function mwp_get_communication_keys()
{
    return mwp_context()->optionGet('mwp_communication_keys', array());
}

function mwp_remove_current_key()
{
    mwp_remove_communication_key(!empty($_SERVER['HTTP_MWP_SITE_ID']) ? $_SERVER['HTTP_MWP_SITE_ID'] : 'any');
}

function mwp_remove_communication_key($siteId)
{
    if ($siteId === 'any') {
        mwp_context()->optionDelete('mwp_communication_key');
        return;
    }

    $keys = mwp_context()->optionGet('mwp_communication_keys', array());

    if (empty($keys[$siteId])) {
        return;
    }

    unset($keys[$siteId]);
    mwp_context()->optionSet('mwp_communication_keys', $keys, true);
}

function mwp_get_basic_communication_key()
{
    $key = mwp_context()->optionGet('mwp_communication_key');
    if (!empty($key)) {
        mwp_context()->optionSet('mwp_key_last_used_any', time(), true);
    }

    return $key;
}

function mwp_add_as_site_communication_key($key)
{
    $siteId = !empty($_SERVER['HTTP_MWP_SITE_ID']) ? $_SERVER['HTTP_MWP_SITE_ID'] : null;

    if (empty($siteId)) {
        return;
    }

    $keys = mwp_context()->optionGet('mwp_communication_keys', array());

    if (is_array($keys) && !empty($keys[$siteId])) {
        return;
    }

    mwp_accept_potential_key($key);
}

function mwp_get_communication_key($id = null)
{
    $siteId = !empty($_SERVER['HTTP_MWP_SITE_ID']) ? $_SERVER['HTTP_MWP_SITE_ID'] : $id;

    if (empty($siteId)) {
        return mwp_get_basic_communication_key();
    }

    $keys = mwp_context()->optionGet('mwp_communication_keys', array());

    if (is_array($keys) && !empty($keys[$siteId])) {
        mwp_context()->optionSet('mwp_key_last_used_'.$siteId, time(), true);

        return $keys[$siteId]['key'];
    }

    return mwp_get_basic_communication_key();
}

function mwp_accept_potential_key($keyToAccept = '')
{
    $siteId = !empty($_SERVER['HTTP_MWP_SITE_ID']) ? $_SERVER['HTTP_MWP_SITE_ID'] : null;
    $addKey = !empty($keyToAccept) ? $keyToAccept : mwp_get_potential_key();

    if (!empty($siteId)) {
        $keys = mwp_context()->optionGet('mwp_communication_keys', array());

        if (empty($keys) || !is_array($keys)) {
            $keys = array();
        }

        $time          = time();
        $keys[$siteId] = array(
            'key'   => $addKey,
            'added' => $time,
        );

        mwp_context()->optionSet('mwp_communication_keys', $keys, true);
        mwp_context()->optionSet('mwp_key_last_used_'.$addKey, $time, true);
    } else {
        mwp_context()->optionSet('mwp_communication_key', $addKey, true);
    }

    mwp_context()->optionDelete('mwp_potential_key', true);
    mwp_context()->optionDelete('mwp_potential_key_time', true);

    return $addKey;
}

function mwp_get_potential_key()
{
    $potentialKey     = mwp_context()->optionGet('mwp_potential_key', null);
    $potentialKeyTime = mwp_context()->optionGet('mwp_potential_key_time', 0);
    $now              = time();

    if (empty($potentialKey) || empty($potentialKeyTime) || !is_numeric($potentialKeyTime) || ($now - $potentialKeyTime) > 86400) {
        $potentialKey     = mwp_generate_uuid4();
        $potentialKeyTime = $now;
        mwp_context()->optionSet('mwp_potential_key', $potentialKey, true);
        mwp_context()->optionSet('mwp_potential_key_time', $potentialKeyTime, true);
    }

    return $potentialKey;
}

function mwp_provision_keys()
{
    mwp_get_service_key();
    mwp_get_potential_key();
}

function mwp_add_post_to_link_monitor_check($postId)
{
    if (wp_get_post_parent_id($postId) !== 0) {
        return;
    }

    $postsToSendToLinkMonitor = mwp_context()->transientGet('mwp_link_monitor_posts');
    if ($postsToSendToLinkMonitor === false) {
        $postsToSendToLinkMonitor = array();
    }

    if (in_array($postId, $postsToSendToLinkMonitor)) {
        return;
    }

    $postsToSendToLinkMonitor[] = $postId;

    //transient will expire after 30 days from time of update
    mwp_context()->transientSet('link_monitor_posts', $postsToSendToLinkMonitor, 2592000);
}

function mwp_send_posts_to_link_monitor()
{
    $postsToSendToLinkMonitor = mwp_context()->transientGet('link_monitor_posts');
    if ($postsToSendToLinkMonitor === false) {
        return;
    }

    $siteIds = array_keys(mwp_context()->optionGet('mwp_communication_keys', array()));

    foreach ($siteIds as $siteId) {
        $body = array(
            'qName'   => 'ha.link_monitor',
            'content' => array(
                'postIds' => $postsToSendToLinkMonitor,
                'siteId'  => $siteId
            ),
            'delay'   => 0
        );

        // Queue the scan
        $url                     = 'https://link-monitor-produce.managewp.com/produce';
        $headers['content-type'] = 'application/json';
        wp_remote_post($url, array(
                'method'  => 'POST',
                'timeout' => 5,
                'headers' => $headers,
                'body'    => json_encode($body),
            )
        );
    }


    // Clear transient
    mwp_context()->transientDelete('link_monitor_posts');
}

function mwp_link_monitor_cron_recurrence_interval($schedules)
{
    $schedules['every_five_minutes'] = array(
        'interval' => 300,
        'display'  => __('Every 5 Minutes')
    );

    return $schedules;
}

function mwp_generate_uuid4()
{
    $data = null;
    if (function_exists('openssl_random_pseudo_bytes')) {
        $data = @openssl_random_pseudo_bytes(16);
    }

    if (empty($data)) {
        $data = '';
        for ($i = 0; $i < 16; ++$i) {
            $data .= chr(mt_rand(0, 255));
        }
    }

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function mwp_refresh_live_public_keys($params = array())
{
    $liveKeys     = null;
    $lastResponse = null;
    $servers      = array('cdn.managewp.com', 'keys.managewp.com');

    foreach ($servers as $server) {
        if (!empty($liveKeys)) {
            continue;
        }

        $lastResponse = mwp_get_and_decode_public_keys($server);

        if (is_array($lastResponse) && !empty($lastResponse['keys']) && !empty($lastResponse['success'])) {
            $liveKeys = $lastResponse['keys'];
        }
    }

    if (empty($liveKeys)) {
        return $lastResponse;
    }

    mwp_context()->optionSet('mwp_public_keys_refresh_time', time(), true);
    mwp_context()->optionSet('mwp_public_keys', $liveKeys, true);

    return $lastResponse;
}

function mwp_get_and_decode_public_keys($domain)
{
    $liveContent = mwp_get_public_keys_from_live($domain);

    if ($liveContent['success'] === false) {
        return array(
            'success' => false,
            'message' => $liveContent['message'],
        );
    }

    $liveContent = $liveContent['result'];

    if (empty($liveContent)) {
        return array(
            'success' => false,
            'message' => 'Empty content received from live.',
        );
    }

    $liveKeys = @json_decode($liveContent, true);

    if (empty($liveKeys)) {
        return array(
            'success' => false,
            'message' => 'Could not json decode the received keys. Received: '.$liveKeys,
        );
    }

    return array(
        'success' => true,
        'keys'    => $liveKeys,
    );
}

function mwp_get_public_keys_from_live($domain)
{
    $result = wp_remote_get("https://$domain/public-keys");

    if (!is_array($result) || empty($result['body'])) {
        return mwp_get_public_keys_from_live_fallback($domain);
    }

    return array(
        'success' => true,
        'result'  => $result['body'],
    );
}

function mwp_get_public_keys_from_live_fallback($domain)
{
    $fixedDomainMap = array(
        'keys.managewp.com' => '216.69.138.218',
    );

    $originalDomain = $domain;
    $domain         = dns_resolve_key_domain($domain);

    if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $domain) !== 1 && !empty($fixedDomainMap[$domain])) {
        $domain = $fixedDomainMap[$domain];
    }

    $transportToUse = get_secure_protocol();

    if ($transportToUse == null) {
        return array(
            'success' => false,
            'message' => 'Could not find a transport to use.',
        );
    }

    $socket = @stream_socket_client("$transportToUse://$domain:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, @stream_context_create(array(
        'ssl' => array(
            'peer_name'         => $originalDomain,
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
            'cafile'            => dirname(__FILE__).'/publickeys/godaddy_g2_root.cer',
        ),
    )));

    if (!$socket) {
        return array(
            'success' => false,
            'message' => 'Failed opening a socket to ManageWP keys (on '.$domain.'). Error: '.$errstr.', Error number: '.$errno,
        );
    }

    $requestContent = <<<EOL
GET /public-keys HTTP/1.1
Host: cdn.managewp.com
Accept-Language: en-US,en;q=0.9,hr;q=0.8,sr;q=0.7
Upgrade-Insecure-Requests: 1
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.94 Safari/537.36
Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8
Cache-Control: max-age=0
Authority: cdn.managewp.com
Connection: close


EOL;


    if (@fwrite($socket, $requestContent) === false) {
        return array(
            'success' => false,
            'message' => 'Could not write the public-key request to the socket.',
        );
    }

    return read_stream_response($socket);
}

function get_secure_protocol()
{
    $transports         = array_flip(stream_get_transports());
    $preferredTransport = array(
        'tls',
        'tlsv1.2',
        'tlsv1.1',
        'tlsv1.0',
    );

    $transportToUse = null;

    foreach ($preferredTransport as $transport) {
        if (!empty($transportToUse) || !isset($transports[$transport])) {
            continue;
        }

        $transportToUse = $transport;
    }

    return $transportToUse;
}

function read_stream_response($socket)
{
    do {
        $line = @fgets($socket);
    } while ($line !== false && $line !== "\n" && $line !== "\r\n");

    if ($line === false) {
        return array(
            'success' => false,
            'message' => 'No response received from the public-key server.',
        );
    }

    $content = @stream_get_contents($socket);

    @fclose($socket);

    if ($content === false || !is_string($content)) {
        return array(
            'success' => false,
            'message' => 'Invalid response received from the public-key server.',
        );
    }

    return array(
        'success' => true,
        'result'  => $content,
    );
}

function dns_resolve_key_domain($domain)
{
    $transportToUse = get_secure_protocol();

    if ($transportToUse == null) {
        return $domain;
    }

    $socket = @stream_socket_client("$transportToUse://1.1.1.1:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT);

    if (!$socket) {
        return $domain;
    }

    $requestContent = <<<PHP
GET /dns-query?name=[DOMAIN]&type=A HTTP/1.1
Host: 1.1.1.1
Accept: application/dns-json
Connection: close


PHP;

    $requestContent = str_replace('[DOMAIN]', $domain, $requestContent);

    if (@fwrite($socket, $requestContent) === false) {
        return $domain;
    }

    $result = read_stream_response($socket);

    if ($result['success'] === false || empty($result['result'])) {
        return $domain;
    }

    $content = @json_decode($result['result'], true);

    if (empty($content['Answer']) || !is_array($content['Answer'])) {
        return $domain;
    }

    $record = $content['Answer'][count($content['Answer']) - 1];

    if (empty($record['data']) || preg_match('/^\d+\.\d+\.\d+\.\d+$/', $record['data']) !== 1) {
        return $domain;
    }

    return $record['data'];
}

function site_in_mwp_maintenance_mode()
{
    $class   = 'notice notice-warning is-dismissible';
    $message = esc_html__('The site is currently in maintenance mode.', 'worker');
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}

function getUploadMessages()
{
    return array(
        'path_not_exist'     => 8,
        'file_exist'         => 9,
        'upload_failed'      => 10,
        'upload_success'     => 11,
        'permissions_denied' => 13,
    );
}
