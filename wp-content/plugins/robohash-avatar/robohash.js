jQuery(document).ready( function($) {

	update_image( 'select#robohash_bot', 'set' );
	update_image( 'select#robohash_bg', 'bgset' );

	function update_image( selector, key ) {

		$( selector ).on( 'change', function() {

			var regex   = new RegExp( '(/|F)' + key + "_\\w*", "g"),
				spinner = $('#spinner').val(),
				img     = $(this).siblings('img'),
				src     = img.attr('src'),
				url     = src.replace( regex, '$1' + key + '_' + $(this).val() );

			img.attr('src', spinner );
			img.attr('srcset', spinner );

			setTimeout( function() {
				img.attr('src', url );
				img.attr('srcset', url );
			}, 500 );

			input = $(this).siblings('input[name="avatar_default"]');
			value = input.val().replace( regex, '$1' + key + '_' + $(this).val() );
			input.val( value );
		});

	}

});