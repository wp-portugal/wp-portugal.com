jQuery( document ).ready( function(){
	jQuery( document ).on( 'click', '#nua-generate-api', function( e ){
		e.preventDefault();
		var apiKey = btoa( nuaAdmin.info );
		jQuery( '#nua-api' ).val( apiKey );
	} );
} );