<?php

/*
 * Class for displaying Gsheet Connector PRO adds
 * @since 2.8
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

/**
 * GS_Connector_Adds Class
 * @since 2.8
 */
class GS_Connector_Adds {
   public function __construct() {
      add_action( 'admin_init', array( $this, 'display_adds_block' ) );
      add_action( 'wp_ajax_set_adds_interval', array( $this, 'set_adds_interval' ) );
      add_action( 'wp_ajax_close_adds_interval', array( $this, 'close_adds_interval' ) );
   }
   
   public function display_adds_block() {
      $get_display_interval = get_option( 'gs_display_add_interval' );
      $close_add_interval = get_option( 'gs_close_add_interval' );
      
      if( $close_add_interval === "off" ) {
         return;
      }
      
      if ( ! empty( $get_display_interval ) ) {
         $adds_interval_date_object = DateTime::createFromFormat( "Y-m-d", $get_display_interval );
         $adds_interval_timestamp = $adds_interval_date_object->getTimestamp();
      }
      
      if ( empty( $get_display_interval ) || current_time( 'timestamp' ) > $adds_interval_timestamp ) {
         add_action( 'admin_notices', array( $this, 'show_gs_adds' ) );
      }         
   }
   
   public function set_adds_interval() {
      // check nonce
      check_ajax_referer( 'gs_adds_ajax_nonce', 'security' );
      $time_interval = date( 'Y-m-d', strtotime( '+30 day' ) );
      update_option( 'gs_display_add_interval', $time_interval );
      wp_send_json_success();
   }
   
   public function close_adds_interval() {
      // check nonce
      check_ajax_referer( 'gs_adds_ajax_nonce', 'security' );
      update_option( 'gs_close_add_interval', 'off' );
      wp_send_json_success();
   }
   
   public function show_gs_adds() {
      $ajax_nonce   = wp_create_nonce( "gs_adds_ajax_nonce" );
      $review_text = '<div class="gs-adds-notice success">';
      $review_text .= 'Upgrade to <a href="https://www.gsheetconnector.com/cf7-google-sheet-connector-pro" target"_blank" >CF7 GSheetConnector PRO</a> with automated sheets to setup within few clicks. Grab the deal with discounted price.';
      $review_text .= '<ul class="review-rating-list">';
      $review_text .= '<li><a href="javascript:void(0);" class="set-adds-interval" title="Nope, may be later">Nope, may be later.</a></li>';
      $review_text .= '<li><a href="javascript:void(0);" class="close-adds-interval" title="Dismiss">Dismiss</a></li>';
      $review_text .= '</ul>';
      $review_text .= '<input type="hidden" name="gs_adds_ajax_nonce" id="gs_adds_ajax_nonce" value="' . $ajax_nonce . '" /></div>';

      $rating_block = Gs_Connector_Utility::instance()->admin_notice( array(
         'type'    => 'review',
         'message' => $review_text
      ) );
      echo $rating_block;
   }
   
}
// construct an instance so that the actions get loaded
$gs_connector_adds = new GS_Connector_Adds();