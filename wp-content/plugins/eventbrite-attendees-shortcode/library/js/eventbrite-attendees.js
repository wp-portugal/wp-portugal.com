(function($) {
   
   // Last element
   $('.eb-attendees-list ul').each(function(index, element) {
	   $(this).children('li:last-child').addClass('last');
   });
   
   // Move the website
   $('.eb-attendee-list-item.website').each(function(index, element) {	   
	   $(this).appendTo( $(this).parent('ul') );
   });
   
   // Filter external links
   $('.eb-attendees-list a').each(function(index, element) {
	   $(this).prop('target','_blank');
   });
   
})(jQuery);