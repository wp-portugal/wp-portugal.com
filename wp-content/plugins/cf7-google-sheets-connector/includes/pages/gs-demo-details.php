<div class="cd-faq-items">
	<ul id="basics" class="cd-faq-group">
		<li class="content-visible">
			<a class="cd-faq-trigger" data-id="5" href="#0"><?php echo esc_html( __( 'Upgrade to CF7 Google Sheet Connector PRO', 'gsconnector' ) ); ?></a>
			<div class="cd-faq-content cd-faq-content5" style="display: block;">
				
				<div class="gs-demo-fields gs-second-block">
					
					<p>
					  <a class="cf_pro_link" href="<?php echo "https://cf7demo.gsheetconnector.com/wp-admin/" ?>" target="_blank"><label><?php echo esc_html( __( 'Click Here Demo', 'gsconnector' ) ); ?></label></a>
					  <?php //echo "https://cf7demo.gsheetconnector.com/wp-admin/" ?>
					</p>
					<!-- <p>
					  <label><?php //echo esc_html( __( 'UserName : ', 'gsconnector' ) ); ?></label>
					  <?php //echo "demo-repo-user" ?>
					</p>
					<p>
					  <label><?php //echo esc_html( __( 'Password : ', 'gsconnector' ) ); ?></label>
					  <?php //echo "!rJaJ@ixux!EIAwiW0FwKkTe" ?>
					</p> -->
					<p>
					  <a class="cf_pro_link" href="https://docs.google.com/spreadsheets/d/1ooBdX0cgtk155ww9MmdMTw8kDavIy5J1m76VwSrcTSs/" target="_blank" rel="noopener"><label><?php echo esc_html( __( 'Sheet URL (Click Here to view Sheet with submitted data.)', 'gsconnector' ) ); ?></label></a>
					  <!-- <a href="https://docs.google.com/spreadsheets/d/1ooBdX0cgtk155ww9MmdMTw8kDavIy5J1m76VwSrcTSs/" target="_blank" rel="noopener">Click Here to view Sheet with data submitted.</a> -->
					</p>

					<p class="gsh_cf7_pro_img"> 
						<img width="250" height="200" alt="CF7-GSheetConnector" src="<?php echo GS_CONNECTOR_URL; ?>assets/img/CF7-GSheetConnector-desktop-img.png" class="">
					</p>
					<p class="gsh_cf7_pro_feat"> 
						<ul style="list-style: square;margin-left:30px">
							<li>Google sheets API (Up-to date )</li>
							<li>One Click Authentication</li>
							<li>Click & Fetch Sheet Automated</li>
							<li>Automated Sheet Name & Tab Name</li>
							<li>Manually Adding Sheet Name & Tab Name</li>
							<li>Quick Configuration</li>
							<li>Latest WordPress & PHP Support</li>
							<li>Support WordPress multisite</li>
							<li>Multiple Forms to Sheets</li>
							<li>Role Management</li>
							<li>Automatic Updates</li>
							<li>Add Special Mail Tags</li>
							<li>Custom Ordering</li>
							<li>Image / PDF Attachment Link</li>
							<li>10-day, Money-back Guarantee</li>
							<li>Custom tags can be Add</li>
							<li>Excellent Priority Support</li>
						</ul>
					</p>

					<p>
					  <a class="cf_pro_link_buy" href="https://www.gsheetconnector.com/cf7-google-sheet-connector-pro?gsheetconnector-ref=17" target="_blank" rel="noopener"><label><?php echo esc_html( __( 'Buy Now - $29.00', 'gsconnector' ) ); ?></label></a>
					</p>
				 </div>
				
			</div>
		</li>
	</ul>
</div>
<script>
jQuery(document).ready(function($){
	//update these values if you change these breakpoints in the style.css file (or _layout.scss if you use SASS)
	var MqM= 768,
		MqL = 1024;

	var faqsSections = $('.cd-faq-group'),
		faqTrigger = $('.cd-faq-trigger'),
		faqsContainer = $('.cd-faq-items'),
		faqsCategoriesContainer = $('.cd-faq-categories'),
		faqsCategories = faqsCategoriesContainer.find('a'),
		closeFaqsContainer = $('.cd-close-panel');
	
	//select a faq section 
	faqsCategories.on('click', function(event){
		event.preventDefault();
		var selectedHref = $(this).attr('href'),
			target= $(selectedHref);
		if( $(window).width() < MqM) {
			faqsContainer.scrollTop(0).addClass('slide-in').children('ul').removeClass('selected').end().children(selectedHref).addClass('selected');
			closeFaqsContainer.addClass('move-left');
			$('body').addClass('cd-overlay');
		} else {
	        $('body,html').animate({ 'scrollTop': target.offset().top - 19}, 200); 
		}
	});

	//close faq lateral panel - mobile only
	$('body').bind('click touchstart', function(event){
		if( $(event.target).is('body.cd-overlay') || $(event.target).is('.cd-close-panel')) { 
			closePanel(event);
		}
	});
	

	//show faq content clicking on faqTrigger
	faqTrigger.on('click', function(event){
		event.preventDefault();
		var dataid = $(this).attr('data-id');
		//$(this).next('.cd-faq-content').slideToggle(200).end().parent('li').toggleClass('content-visible');
		for (var i = 1 ; i <= 5; i++) {
			if(i!=dataid && i!=5)
			{
				$('.cd-faq-content'+i).hide(200).end().parent('li').removeClass('content-visible');
			}
		}
		$(this).next('.cd-faq-content'+dataid).slideToggle(200).end().parent('li').toggleClass('content-visible');
	});

});
</script>