jQuery(document).ready(function($){
	let options = {
		color: true,
		mode: 'hsl',
		palettes : false,
		hide: true,
		change: function(event, ui) {
			// change the headline color
			let color = ui.color.toHsl();
			$("#lwa-hue-preview .lwa").css( '--accent-hue', color.h).css('--accent-l', color.l+"%").css('--accent-s', color.s+"%");
			$('#lwa-template-hsl-h').val(color.h);
			$('#lwa-template-hsl-s').val(color.s);
			$('#lwa-template-hsl-l').val(color.l);
		}
	};
	let colorpicker = $('#lwa-template-colorpicker').wpColorPicker(options);
	// fix colorpicker current value to convert hsl to real value
	colorpicker.iris('color',colorpicker.iris('color', true).toString());

	//Navigation Tabs
	$('.tabs-active .nav-tab-wrapper .nav-tab').on('click', function(){
		el = $(this);
		elid = el.attr('id');
		$('.lwa-menu-group').hide();
		$('.'+elid).show();
	});
	$('.nav-tab-wrapper .nav-tab').on('click', function(){
		$('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active').blur();
	});
	var navUrl = document.location.toString();
	if (navUrl.match('#')) { //anchor-based navigation
		var nav_tab = navUrl.split('#');
		var current_tab = 'a#lwa-menu-' + nav_tab[1];
		$(current_tab).trigger('click');
		if( nav_tab.length > 2 ){
			section = $("#lwa-opt-"+nav_tab[2]);
			if( section.length > 0 ){
				section.children('h2').trigger('click');
				$('html, body').animate({ scrollTop: section.offset().top - 30 }); //sends user back to current section
			}
		}
	}else{
		//set to general tab by default, so we can also add clicked subsections
		document.location = navUrl+"#custom-meta-tags";
	}
	$('.tabs-active .nav-tab-wrapper .nav-tab.nav-tab-active').trigger('click');
	$('.nav-tab-link').on('click', function(){ $($(this).attr('rel')).trigger('click'); }); //links to mimick tabs
	$('input[type="submit"]').on('click', function(){
		var el = $(this).parents('.postbox').first();
		var docloc = document.location.toString().split('#');
		var newloc = docloc[0];
		if( docloc.length > 1 ){
			var nav_tab = docloc[1].split('#');
			newloc = newloc + "#" + nav_tab[0];
			if( el.attr('id') ){
				newloc = newloc + "#" + el.attr('id').replace('lwa-opt-','');
			}
		}
		document.location = newloc;
	});
});