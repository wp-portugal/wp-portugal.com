<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit();
}
$cf7gs_tools_service = new Gs_Connector_Free_Init();
?>
<div class="wrap">
   <textarea readonly="readonly" onclick="this.focus();this.select()" id="cf7gs-system-info" name="cf7gs-system-info" title="<?php echo __( 'To copy the system info, click below then press Ctrl + C (PC) or Cmd + C (Mac).', 'googlesheet' ); ?>">
<?php echo $cf7gs_tools_service->get_cf7gs_system_info(); ?>
   </textarea>
</div>      

