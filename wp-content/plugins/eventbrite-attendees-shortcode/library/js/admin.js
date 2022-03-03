function preview() {	
	
	var Version,
		$this,
		ThisID,
		Values = [];
		
	jQuery('input[name^="_Eventbrite_"],select[name^="_Eventbrite_"]').each(function() {
		$this = jQuery(this);
				
		ThisID = $this.prop('name').replace('_Eventbrite_', '').toLowerCase();
		
		if ( '' !== $this.val() )
			Values.push(ThisID + '="' + $this.val() + '"');
	});
	jQuery('#_Eventbrite_output').text('[eventbrite-attendees ' + Values.join(' ') + ']');
}

jQuery(document).ready(
	function($) {
		var post_type 	= $('[name="post_type"]').val();
		var meta_box	= $('#eventbrite-attendees-' + post_type + '-meta-box');
		if ( meta_box.length ) {
	
			var $Controls = {
				"inputs" 	: $('input[name^="_Eventbrite_"]'), 
				"selects" 	: $('select[name^="_Eventbrite_"]')
			};
	
			/* Set all selects to their first value */
			$Controls.selects.each(function() { this.selectedIndex = 0; });
			
			/* Watch the keys */
			$Controls.inputs.on('keyup', preview).on('keypress', function(e) {
				if( e.which == 13 )
					e.preventDefault();
			});
			$Controls.selects.on('click change', preview);
		
		} // end meta_box
		
	}
);