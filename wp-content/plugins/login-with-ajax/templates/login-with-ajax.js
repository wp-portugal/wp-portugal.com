/* Customize from here downwards */
/** @param {jQuery} jQuery */
jQuery(document).ready( function($) {
	// some backwards compatability here - will deprecate in 5.0
	if( $('#LoginWithAjax').length > 0 ){
		$('#LoginWithAjax').addClass('lwa');
		$('#LoginWithAjax_Status').addClass('lwa-status');
		$('#LoginWithAjax_Register').addClass('lwa-register');
		$('#LoginWithAjax_Remember').addClass('lwa-remember');
		$('#LoginWithAjax_Links_Remember').addClass('lwa-links-remember');
		$('#LoginWithAjax_Links_Remember_Cancel').addClass('lwa-links-remember-cancel');
		$('#LoginWithAjax_Form').addClass('lwa-form');
	}
	// compatibility workaround, first 5 elements will take an ID
	$('.lwa-bones').each( function(i){
		$(this).attr('id','lwa-'+ (i+1));
	});
	/*
	 * links
	 * add action input htmls
	 */

	//Remember and register form AJAX
	$(document).on('submit', 'form.lwa-form, form.lwa-remember, div.lwa-register form', function( event ){
		event.preventDefault();
		LoginWithAJAX.submit(this);
	});

	//Catch login actions
	$(document).on('lwa_login',
		/**
		 * Fired when users logs in and decides to either replace the widget or reload the page.
		 * @param {Event} event
		 * @param {Object} data
		 * @param {jQuery} form
		 */
		function(event, data, form){
			if( data.result === true && (data.skip === 'undefined' || !data.skip) ){
				//Login Successful - Extra stuff to do
				if( data.widget != null ){
					$.get( data.widget, function(widget_result) {
						var newWidget = $(widget_result);
						form.parent('.lwa').replaceWith(newWidget);
						var lwaSub = newWidget.find('.').show();
						var lwaOrg = newWidget.parent().find('.lwa-title');
						lwaOrg.replaceWith(lwaSub);
					});
				}else{
					if(data.redirect == null){
						window.location.reload();
					}else{
						window.location = data.redirect;
					}
				}
			}
		}
	);

	// Modal
	$('.lwa-modal-trigger').each( function(i,e){
		$(e).find('.lwa-modal-trigger-el, button, a').first().on('click', function(){
			var modal_id = $(this).closest('.lwa-modal-trigger').first().data('modal-id');
			$('#'+modal_id+', #'+modal_id+' .lwa-modal-popup').addClass('active');
		});
	});
	$('.lwa-modal-overlay').each( function(i,e){
		$('body').append(e);
	});
	$('.lwa-modal-overlay .lwa-close-modal').click( function(e){
		let modal = $(this).closest('.lwa-modal-overlay');
		if( !modal.attr('data-prevent-close') ) {
			modal.removeClass('active').find('.lwa-modal-popup').removeClass('active');
			$(document).triggerHandler('lwa_modal_close', [modal]);
		}
	});
	$('.lwa-modal-overlay').click( function(e){
		var target = $(e.target);
		if( target.hasClass('lwa-modal-overlay') ) {
			let modal = $(this);
			if( !modal.attr('data-prevent-close') ){
				modal.removeClass('active').find('.lwa-modal-popup').removeClass('active');
				$(document).triggerHandler('lwa_modal_close', modal);
			}
		}
	});


	//Visual Effects for hidden items
	$(document).on('click', '.lwa-links-register-inline-cancel, .lwa-links-remember-cancel', function(event){
		event.preventDefault();
		let lwa = $(this).closest('.lwa');
		lwa.find('.lwa-form').slideDown('slow');
		lwa.find('.lwa-remember, .lwa-register').slideUp('slow');
	});
	//Register
	$(document).on('click', '.lwa-links-register-inline', function(event){
		let lwa = $(this).closest('.lwa');
		var register_form = lwa.find('.lwa-register');
		if( register_form.length > 0 ){
			event.preventDefault();
			register_form.slideDown('slow');
			lwa.find('.lwa-remember, .lwa-form').slideUp('slow');
		}
	});
	//Remember
	$(document).on('click', '.lwa-links-remember', function(event){
		let lwa = $(this).closest('.lwa');
		var remember_form = lwa.find('.lwa-remember');
		if( remember_form.length > 0 ){
			event.preventDefault();
			remember_form.slideDown('slow');
			lwa.find('.lwa-register, .lwa-form').slideUp('slow');
		}
	});

	// initialize minimalistic themes
	if( $('.lwa-minimalistic').length ){
		lwa_init_minimalistic();
	}

	$(document).triggerHandler('lwa_loaded');
});

const lwa_init_minimalistic = function(){
	jQuery('.lwa-minimalistic .input-field label').each( function(i){
		// move the label above the input
		jQuery(this).next('input').after(this);
	});
	// clean up WP-style inputs
	jQuery('.lwa-minimalistic p > label > input').each( function(i,el){
		let input = jQuery(this);
		let label = input.parent();
		let p = label.parent();
		let div = jQuery('<div class="input-field"></div>');
		// move the label above the input, remove any br's
		input.appendTo(div);
		label.appendTo(div);
		label.find('br').remove();
		if( !input.attr('placeholder') ){
			input.attr('placeholder', label.text());
		}
		p.replaceWith(div);
	});
};

/**
 * Various functions related to the submission and execution of a Login With AJAX form.
 * @type {{submit: LoginWithAJAX.submit, addStatusElement: LoginWithAJAX.addStatusElement, handleStatus: LoginWithAJAX.handleStatus, finish: LoginWithAJAX.finish}}
 */
const LoginWithAJAX = {
	/**
	 * Handles submission of a LWA form.
	 * @param {jQuery} form Form object which will be submitted.
	 * @param {object} args Additional args passed as an object or serializeArray() comopatbible format for merging in.
	 * @param {boolean} override If set to true then only args will be used to submit AJAX request, form data will be ignored.
	 */
	submit : function( form, args = null, override = null ){
		//Stop event, add loading pic...
		if( !(form instanceof jQuery) ) form = jQuery(form);
		var response = { result : null, skip : false };
		var statusElement = LoginWithAJAX.addStatusElement(form);
		jQuery(document).triggerHandler('lwa_pre_ajax', [response, form, statusElement]);
		if( response.result === null ) {
			var ajaxFlag = form.find('.lwa-ajax');
			if( ajaxFlag.length == 0 ){
				ajaxFlag = jQuery('<input class="lwa-ajax" name="lwa" type="hidden" value="1" />');
				form.prepend(ajaxFlag);
			}
			LoginWithAJAX.start( form );
			// Prepare form data, merge in data from args if passed
			let form_data;
			if( override ){
				form_data = [];
			}else{
				form_data = form.serializeArray();
			}
			if( args !== null ){
				if( !Array.isArray(args) ){
					// merge in args via each()
					Object.keys(args).forEach( function(key){
						form_data.unshift({'name':key, 'value' : args[key]});
					});
				}else{
					// merge directly
					form_data.push.apply(form_data, args);
				}
			}
			let form_string = jQuery.param(form_data);
			// Make Ajax Call
			let form_action = ( typeof LWA === 'undefined' ) ? form.attr('action') : LWA.ajaxurl;
			jQuery.ajax({
				type: 'POST',
				url: form_action,
				data: form_string,
				success: function (response) {
					jQuery(document).triggerHandler('lwa_' + response.action, [response, form, statusElement]);
					if( response.skip === 'undefined' || !response.skip ){
						LoginWithAJAX.handleStatus(response, statusElement);
					}
				},
				error: function( jqXHR, textStatus, errorThrown ) {
					response.result = false;
					jQuery(document).triggerHandler('lwa_ajax_error', [response, jqXHR, textStatus, errorThrown, form, statusElement]);
					if( !response.skip ) {
						response.error = textStatus + ' : ' + errorThrown;
						LoginWithAJAX.handleStatus({}, statusElement);
					}else{
						LoginWithAJAX.finish( form );
					}
				},
				complete: function( jqXHR, textStatus ){
					jQuery(document).triggerHandler('lwa_ajax_complete', [jqXHR, textStatus, form, statusElement]);
					if( textStatus !== 'success' && textStatus !== 'error' ){ // this is already executed on success or error
						LoginWithAJAX.finish( form );
					}
				},
				dataType: 'jsonp'
			});
		}else{
			if( !response.skip ) {
				LoginWithAJAX.handleStatus(response, statusElement);
			}
		}
	},

	/**
	 * Handles a status element of a form based on the return data of an AJAX call
	 * @param {Object} response The response object of an AJAX call.
	 * @param {jQuery} statusElement The status element in a form where error or success messages are to be added based on the response.
	 */
	handleStatus : function( response, statusElement ){
		this.finish();
		statusElement = jQuery(statusElement);
		if(response.result === true){
			//Login Successful
			statusElement.removeClass('lwa-status-invalid').addClass('lwa-status-confirm').html(response.message); //modify status content
		}else if( response.result === false ){
			//Login Failed
			statusElement.removeClass('lwa-status-confirm').addClass('lwa-status-invalid').html(response.error); //modify status content
			//We assume a link in the status message is for a forgotten password
			statusElement.find('a').on('click', function(event){
				var remember_form = jQuery(this).parents('.lwa').find('form.lwa-remember');
				if( remember_form.length > 0 ){
					event.preventDefault();
					remember_form.show('slow');
				}
			});
		}else{
			//If there already is an error element, replace text contents, otherwise create a new one and insert it
			statusElement.removeClass('lwa-status-confirm').addClass('lwa-status-invalid').html('An error has occured. Please try again.'); //modify status content
		}
		jQuery(document).triggerHandler('lwa_handleStatus', [response, statusElement]);
	},

	/**
	 * Prepends a status element to the supplied form element.
	 * @param {jQuery} form The form element to add the status element to.
	 * @return {jQuery} The status element jQuery object.
	 */
	addStatusElement : function( form ){
		let statusElement = form.find('.lwa-status');
		jQuery(document).triggerHandler('lwa_addStatusElement', [form, statusElement]);
		if( statusElement.length === 0 ){
			statusElement = jQuery('<span class="lwa-status" role="alert"></span>');
			form.prepend(statusElement);
		}
		return statusElement;
	},

	start : function( wrapper ){
		if( wrapper.hasClass('lwa') ){
			wrapper.addClass('lwa-is-working');
		}else{
			wrapper.closest('.lwa').addClass('lwa-is-working');
		}
		jQuery('<div class="lwa-loading"></div>').prependTo(wrapper.closest('.lwa-wrapper'));
	},

	/**
	 * Remove spinners etc. from all forms, or the form wrapper if supplied.
	 * @param {jQuery} wrapper Form or wrapper element contaning the spinner.
	 */
	finish : function( wrapper = null ){
		jQuery('.lwa-loading').remove();
		if( wrapper && wrapper.hasClass('lwa-is-working') ){
			wrapper.removeClass('lwa-is-working');
		}else if( wrapper ){
			wrapper.closest('.lwa-is-working').removeClass('lwa-is-working');
		}else{
			jQuery('.lwa-is-working').removeClass('lwa-is-working');
		}
	},
};
// shortcut - legacy
const lwaAjax = LoginWithAJAX.handleStatus;
