<?php
/*
 * This is the page users will see logged out.
 * You can edit this, but for upgrade safety you should copy and modify this file into a folder.
 * See https://docs.loginwithajax.com/advanced/templates/ for more information.
 *
 * This template copies the default template and with a little jQuery the label is switched above the input field
*/
/* @var array $lwa  Array of data supplied to widget */
include( LOGIN_WITH_AJAX_PATH . '/templates/default/login.php');
if( !defined('LWA_TEMPLATE_CLASSIC_LOADED') ){
?>
<script type="text/javascript">
	jQuery(document).ready( function($){
		let rememberme = $('.lwa-classic .lwa-links input[name="rememberme"]');
		rememberme.prev().before( rememberme );
		$('.lwa-classic .grid-container').removeClass('grid-container');
	});
</script>
<?php
}
define('LWA_TEMPLATE_CLASSIC_LOADED', true);