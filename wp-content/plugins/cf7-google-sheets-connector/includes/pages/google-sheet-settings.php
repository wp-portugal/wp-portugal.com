<?php
/*
 * Google Sheet configuration and settings page
 * @since 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit();
}

$active_tab = ( isset ( $_GET['tab'] ) && sanitize_text_field( $_GET["tab"] )) ?  sanitize_text_field( $_GET['tab'] ) : 'integration';

?>

<div class="wrap">
	<?php
       $tabs = array(  
        'integration' => __( 'Integration', 'gsconnector' ),
         'support' => __( 'Support', 'gsconnector' ),
         'faq' => __( 'FAQ', 'gsconnector' ),
         'demos' => __( 'Demos', 'gsconnector' ),
         'system-status' => __( 'System Status', 'gsconnector' ),
         );
       echo '<div id="icon-themes" class="icon32"><br></div>';
       echo '<h2 class="nav-tab-wrapper">';
       foreach( $tabs as $tab => $name ){
           $class = ( $tab == $active_tab ) ? ' nav-tab-active' : '';
           echo "<a class='nav-tab$class' href='?page=wpcf7-google-sheet-config&tab=$tab'>$name</a>";

       }
       echo '</h2>';
   	switch ( $active_tab ){
        case 'integration' :
   		   $gs_intigrate = new Gs_Connector_Free_Init();
			   $gs_intigrate->google_sheet_config();
   		   break;
		case 'support' :
   		   include( GS_CONNECTOR_PATH . "includes/pages/gs-integrate-support.php" );
   		   break;
		case 'faq' :
   		   include( GS_CONNECTOR_PATH . "includes/pages/gs-integrate-faq.php" );
   		   break;
		case 'demos' :
   		   include( GS_CONNECTOR_PATH . "includes/pages/gs-integrate-demo.php" );
   		   break;
		case 'system-status' :
   		   include( GS_CONNECTOR_PATH . "includes/pages/gs-integrate-info.php" );
   		   break;
	}
	?>
</div>

