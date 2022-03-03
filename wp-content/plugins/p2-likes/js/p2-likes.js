function p2Likes( type, id) {

	if ( id != '' ) {

		jQuery.post( p2_likes.ajaxURL,
			{ action: 'p2_likes_like', type: type, id: id },
			function(data) {

				var parsedJSON = jQuery.parseJSON(data);

				var typeslug = 'post';
				if ( type == 1 ) typeslug = 'comment';

				var likeText = jQuery('.p2-likes-' + typeslug + '-' + id + ' .p2-likes-like').html();
				switch (likeText) {
					case 'Like':
					  jQuery('.p2-likes-' + typeslug + '-' + id + ' .p2-likes-like').html( p2_likes.unlike );
					  break;
					case 'Unlike':
					  jQuery('.p2-likes-' + typeslug + '-' + id + ' .p2-likes-like').html( p2_likes.like);
					  break;
				}

				jQuery('.p2-likes-' + typeslug + '-' + id + ' .p2-likes-count').html(parsedJSON[0]);
				jQuery('.p2-likes-' + typeslug + '-' + id).next('.p2-likes-box').html(parsedJSON[1]).fadeIn();

			}
		);

	}
}

jQuery(function() {

	jQuery('.p2-likes-link').hover(function() {
		jQuery('.p2-likes-box:not(:empty)', this).fadeIn();
	});

	jQuery('.p2-likes-box').live({
		mouseenter: function() {},
		mouseleave: function() {
			jQuery(this).fadeOut();
		}
	});

});
