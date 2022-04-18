<?php
/*
 * This is the page users will see logged out.
 * You can edit this, but for upgrade safety you should copy and modify this file into a folder.
 * See https://docs.loginwithajax.com/advanced/templates/ for more information.
*/
/* @var array $lwa  Array of data supplied to widget */

/*
 * This template makes use of the regular modal template, we just provide the template for the modal to load. You can do the same for your own custom modal by registering a new template and injecting it into the modal below.
 */
$lwa['template'] = 'minimalistic';
$lwa['template-parent'] = 'modal-minimalistic';
$template_path = LoginWithAjax::get_template_path('modal');
if( file_exists($template_path) ){
	include($template_path.'/login.php');
}else{
	// get the default template path
	include( LOGIN_WITH_AJAX_PATH . '/templates/modal/login.php');
}
?>