jQuery(document).ready(function () {
   /**
    * verify the api code
    * @since 1.0
    */
    jQuery(document).on('click', '#save-gs-code', function () {
        jQuery( ".loading-sign" ).addClass( "loading" );
        var data = {
        action: 'verify_gs_integation',
        code: jQuery('#gs-code').val(),
        security: jQuery('#gs-ajax-nonce').val()
      };
      jQuery.post(ajaxurl, data, function (response ) {
          if( ! response.success ) { 
            jQuery( ".loading-sign" ).removeClass( "loading" );
            jQuery( "#gs-validation-message" ).empty();
            jQuery("<span class='error-message'>Access code Can't be blank</span>").appendTo('#gs-validation-message');
          } else {
            jQuery( ".loading-sign" ).removeClass( "loading" );
            jQuery( "#gs-validation-message" ).empty();
            jQuery("<span class='gs-valid-message'>Your Google Access Code is Authorized and Saved.</span>").appendTo('#gs-validation-message'); 
            setTimeout(function () { location.reload(); }, 7000);
          }
      });
      
    });  
    
    /**
    * deactivate the api code
    * @since 4.2
    */
    jQuery(document).on('click', '#deactivate-log', function () {
        jQuery(".loading-sign-deactive").addClass( "loading" );
		var txt;
		var r = confirm("Are You sure you want to deactivate Google Integration ?");
		if (r == true) {
			var data = {
				action: 'deactivate_gs_integation',
				security: jQuery('#gs-ajax-nonce').val()
			};
			jQuery.post(ajaxurl, data, function (response ) {
				if ( response == -1 ) {
					return false; // Invalid nonce
				}
			 
				if( ! response.success ) {
					alert('Error while deactivation');
					jQuery( ".loading-sign-deactive" ).removeClass( "loading" );
					jQuery( "#deactivate-message" ).empty();
					
				} else {
					jQuery( ".loading-sign-deactive" ).removeClass( "loading" );
					jQuery( "#deactivate-message" ).empty();
					jQuery("<span class='gs-valid-message'>Your account is removed. Reauthenticate again to integrate Contact Form with Google Sheet.</span>").appendTo('#deactivate-message');
		   		    setTimeout(function () { location.reload(); }, 5000);
				}
			});
		} else {
			jQuery( ".loading-sign-deactive" ).removeClass( "loading" );
		}
        
      
      
    }); 
    
    /**
     * Clear debug
     */
      jQuery(document).on('click', '.debug-clear', function () { 
         jQuery( ".clear-loading-sign" ).addClass( "loading" );
         var data = {
            action: 'gs_clear_log',
            security: jQuery('#gs-ajax-nonce').val()
         };
         jQuery.post(ajaxurl, data, function (response ) {
            if( response.success ) { 
               jQuery( ".clear-loading-sign" ).removeClass( "loading" );
               jQuery( "#gs-validation-message" ).empty();
               jQuery("<span class='gs-valid-message'>Logs are cleared.</span>").appendTo('#gs-validation-message'); 
            }
         });
      });
      
});
