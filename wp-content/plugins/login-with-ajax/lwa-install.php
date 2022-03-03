<?php
global $wp_version;
$lwa_data = get_option('lwa_data');
if( !is_array($lwa_data) ) $lwa_data = array();
//no DB changes necessary
//add notices
if( version_compare($wp_version, '4.3', '>=') && get_option('lwa_notice') ){
    //upgrading from old version, first time we have lwa_version too, so must check for lwa_notice presence
    if( empty($lwa_data['notices']) ) $lwa_data['notices'] = array();
    $lwa_data['notices']['password_link'] = 1;
    update_option('lwa_data', $lwa_data);
    delete_option('lwa_notice');
    delete_option('lwa_notification_override');
}
update_option('lwa_version', LOGIN_WITH_AJAX_VERSION);