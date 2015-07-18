<?php

add_action('wp_ajax_get_terms','prefix_ajax_get_terms');
add_action('wp_ajax_nopriv_get_terms','prefix_ajax_get_terms');

function prefix_ajax_get_terms(){
	if( !wp_verify_nonce( $_POST['getterms_nonce'] , 'myajax-term-nonce' ) ) {
		die ( 'Not allowed.');
	}
	$term_text = sanitize_text_field($_POST['term_text']); 
	
	$solution  = "";
	$uploaddir = wp_upload_dir();
	if (($handle = fopen($uploaddir['baseurl']."/glossario.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    	if( mb_strtolower($data[0]) === mb_strtolower($term_text)) {
    	  $solution = $data[1];
    	} 
    }
    fclose($handle);
	}

	$response = json_encode( $solution  );
 
  // send response 
  header( "Content-Type: application/json" );
  echo $response;
	
	die();
	
}