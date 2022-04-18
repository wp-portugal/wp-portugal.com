/* Customize from here downwards */
jQuery(document).ready( function($) {
	//TODO some backwards compatability here -
	if( $('#LoginWithAjax').length > 0 ){
		$('#LoginWithAjax').addClass('lwa');
		$('#LoginWithAjax_Status').addClass('lwa-status');
		$('#LoginWithAjax_Register').addClass('lwa-register');
		$('#LoginWithAjax_Remember').addClass('lwa-remember');
		$('#LoginWithAjax_Links_Remember').addClass('lwa-links-remember');
		$('#LoginWithAjax_Links_Remember_Cancel').addClass('lwa-links-remember-cancel');
		$('#LoginWithAjax_Form').addClass('lwa-form');
	}
	/*
	 * links
	 * add action input htmls
	 */
	//Remember and register form AJAX
	$('form.lwa-form, form.lwa-remember, div.lwa-register form').submit(function(event){
		//Stop event, add loading pic...
		event.preventDefault();
		var form = $(this);
		var statusElement = form.find('.lwa-status');
		if( statusElement.length == 0 ){
			statusElement = $('<span class="lwa-status"></span>');
			form.prepend(statusElement);
		}
		var ajaxFlag = form.find('.lwa-ajax');
		if( ajaxFlag.length == 0 ){
			ajaxFlag = $('<input class="lwa-ajax" name="lwa" type="hidden" value="1" />');
			form.prepend(ajaxFlag);
		}
		$('<div class="lwa-loading"></div>').prependTo(form);
		//Make Ajax Call
		var form_action = form.attr('action');
		if( typeof LWA !== 'undefined' ) form_action = LWA.ajaxurl;
		$.ajax({
			type : 'POST',
			url : form_action,
			data : form.serialize(),
			success : function(data){
				lwaAjax( data, statusElement );
				$(document).trigger('lwa_' + data.action, [data, form]);
			},
			error : function(){ lwaAjax({}, statusElement); },
			dataType : 'jsonp'
		});
		//trigger event
	});

	//Catch login actions
	$(document).on('lwa_login', function(event, data, form){
		if(data.result === true){
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
	});

	//Registration overlay
	$('.lwa-modal').each( function(i,e){
		var modal = $(e);
		modal.parents('.lwa').data('modal', modal);
		$('body').append($('<div class="lwa"></div>').append(modal));
	});
	$(document).on('click', ".lwa-links-modal",  function(e){
		var target = $(this).parents('.lwa').data('modal');
		if( typeof target != 'undefined' && target.length > 0 ){
			e.preventDefault();
			target.reveal({
				modalbgclass: 'lwa-modal-bg',
				dismissmodalclass: 'lwa-modal-close'    //the class of a button or element that will close an open modal
			});
		}
	});
	//Register
	$('.lwa-links-register-inline').on('click', function(event){
		var register_form = $(this).parents('.lwa').find('.lwa-register');
		if( register_form.length > 0 ){
			event.preventDefault();
			register_form.show('slow');
			$(this).parents('.lwa').find('.lwa-remember').hide('slow');
		}
	});
	$('.lwa-links-register-inline-cancel').on('click', function(event){
		event.preventDefault();
		$(this).parents('.lwa-register').hide('slow');
	});

	//Visual Effects for hidden items
	//Remember
	$(document).on('click', '.lwa-links-remember', function(event){
		var remember_form = $(this).parents('.lwa').find('.lwa-remember');
		if( remember_form.length > 0 ){
			event.preventDefault();
			remember_form.show('slow');
			$(this).parents('.lwa').find('.lwa-register').hide('slow');
		}
	});
	$(document).on('click', '.lwa-links-remember-cancel', function(event){
		event.preventDefault();
		$(this).parents('.lwa-remember').hide('slow');
	});

	//Handle a AJAX call for Login, RememberMe or Registration
	function lwaAjax( data, statusElement ){
		$('.lwa-loading').remove();
		statusElement = $(statusElement);
		if(data.result === true){
			//Login Successful
			statusElement.removeClass('lwa-status-invalid').addClass('lwa-status-confirm').html(data.message); //modify status content
		}else if( data.result === false ){
			//Login Failed
			statusElement.removeClass('lwa-status-confirm').addClass('lwa-status-invalid').html(data.error); //modify status content
			//We assume a link in the status message is for a forgotten password
			statusElement.find('a').on('click', function(event){
				var remember_form = $(this).parents('.lwa').find('form.lwa-remember');
				if( remember_form.length > 0 ){
					event.preventDefault();
					remember_form.show('slow');
				}
			});
		}else{
			//If there already is an error element, replace text contents, otherwise create a new one and insert it
			statusElement.removeClass('lwa-status-confirm').addClass('lwa-status-invalid').html('An error has occured. Please try again.'); //modify status content
		}
	}

});

/* http://zurb.com/playground/reveal-modal-plugin */
/*
 * jQuery Reveal Plugin 1.0
 * www.ZURB.com
 * Copyright 2010, ZURB
 * Free to use under the MIT license.
 * http://www.opensource.org/licenses/mit-license.php
*/

(function($) {

	/*---------------------------
	 Defaults for Reveal
	----------------------------*/

	/*---------------------------
	 Listener for data-reveal-id attributes
	----------------------------*/

	$('a[data-reveal-id]').on('click', function(e) {
		e.preventDefault();
		var modalLocation = $(this).attr('data-reveal-id');
		$('#'+modalLocation).reveal($(this).data());
	});

	/*---------------------------
	 Extend and Execute
	----------------------------*/

	$.fn.reveal = function(options) {


		var defaults = {
			animation: 'fadeAndPop', //fade, fadeAndPop, none
			animationspeed: 300, //how fast animtions are
			closeonbackgroundclick: true, //if you click background will modal close?
			dismissmodalclass: 'close-reveal-modal', //the class of a button or element that will close an open modal
			modalbgclass : 'reveal-modal-bg'
		};

		//Extend dem' options
		var options = $.extend({}, defaults, options);

		return this.each(function() {

			/*---------------------------
			 Global Variables
			----------------------------*/
			var modal = $(this),
				topMeasure  = parseInt(modal.css('top')),
				topOffset = modal.height() + topMeasure,
				locked = false,
				modalBG = $('.'+options.modalbgclass);

			/*---------------------------
			 Create Modal BG
			----------------------------*/
			if(modalBG.length == 0) {
				modalBG = $('<div class="'+options.modalbgclass+'" />').insertAfter(modal);
			}
			if( modal.find('.'+options.dismissmodalclass).length == 0 ){
				modal.append('<a class="'+options.dismissmodalclass+'">&#215;</a>');
			}

			/*---------------------------
			 Open & Close Animations
			----------------------------*/
			//Entrance Animations
			modal.bind('reveal:open', function () {
				modalBG.unbind('click.modalEvent');
				$('.' + options.dismissmodalclass).unbind('click.modalEvent');
				if(!locked) {
					lockModal();
					if(options.animation == "fadeAndPop") {
						modal.css({'top': $(document).scrollTop()-topOffset, 'opacity' : 0, 'visibility' : 'visible', 'display':'block'});
						modalBG.fadeIn(options.animationspeed/2);
						modal.delay(options.animationspeed/2).animate({
							"top": $(document).scrollTop()+topMeasure + 'px',
							"opacity" : 1
						}, options.animationspeed,unlockModal());
					}
					if(options.animation == "fade") {
						modal.css({'opacity' : 0, 'visibility' : 'visible', 'top': $(document).scrollTop()+topMeasure, 'display':'block'});
						modalBG.fadeIn(options.animationspeed/2);
						modal.delay(options.animationspeed/2).animate({
							"opacity" : 1
						}, options.animationspeed,unlockModal());
					}
					if(options.animation == "none") {
						modal.css({'visibility' : 'visible', 'top':$(document).scrollTop()+topMeasure, 'display':'block'});
						modalBG.css({"display":"block"});
						unlockModal()
					}
				}
				modal.unbind('reveal:open');
			});

			//Closing Animation
			modal.bind('reveal:close', function () {
				if(!locked) {
					lockModal();
					if(options.animation == "fadeAndPop") {
						modalBG.delay(options.animationspeed).fadeOut(options.animationspeed);
						modal.animate({
							"top":  $(document).scrollTop()-topOffset + 'px',
							"opacity" : 0
						}, options.animationspeed/2, function() {
							modal.css({'top':topMeasure, 'opacity' : 1, 'visibility' : 'hidden'});
							unlockModal();
						});
					}
					if(options.animation == "fade") {
						modalBG.delay(options.animationspeed).fadeOut(options.animationspeed);
						modal.animate({
							"opacity" : 0
						}, options.animationspeed, function() {
							modal.css({'opacity' : 1, 'visibility' : 'hidden', 'top' : topMeasure});
							unlockModal();
						});
					}
					if(options.animation == "none") {
						modal.css({'visibility' : 'hidden', 'top' : topMeasure});
						modalBG.css({'display' : 'none'});
					}
				}
				modal.unbind('reveal:close');
			});

			/*---------------------------
			 Open and add Closing Listeners
			----------------------------*/
			//Open Modal Immediately
			modal.trigger('reveal:open')

			//Close Modal Listeners
			var closeButton = $('.' + options.dismissmodalclass).bind('click.modalEvent', function () {
				modal.trigger('reveal:close')
			});

			if(options.closeonbackgroundclick) {
				modalBG.css({"cursor":"pointer"})
				modalBG.bind('click.modalEvent', function () {
					modal.trigger('reveal:close')
				});
			}
			$('body').on('keyup', function(e) {
				if(e.which===27){ modal.trigger('reveal:close'); } // 27 is the keycode for the Escape key
			});


			/*---------------------------
			 Animations Locks
			----------------------------*/
			function unlockModal() {
				locked = false;
			}
			function lockModal() {
				locked = true;
			}

		});//each call
	}//orbit plugin call
})(jQuery);