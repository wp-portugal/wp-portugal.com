<style>
html,
body,
div,
span,
applet,
object,
iframe,
h1,
h2,
h3,
h4,
h5,
h6,
p,
blockquote,
pre,
a,
abbr,
acronym,
address,
big,
cite,
code,
del,
dfn,
em,
img,
ins,
kbd,
q,
s,
samp,
small,
strike,
strong,
sub,
sup,
tt,
var,
b,
u,
i,
center,
dl,
dt,
dd,
ol,
ul,
li,
fieldset,
form,
label,
legend,
table,
caption,
tbody,
tfoot,
thead,
tr,
th,
td,
article,
aside,
canvas,
details,
embed,
figure,
figcaption,
footer,
header,
hgroup,
menu,
nav,
output,
ruby,
section,
summary,
time,
mark,
audio,
video {
  margin: 0;
  padding: 0;
  border: 0;
  font-size: 100%;
  font: inherit;
  vertical-align: baseline;
}
/* HTML5 display-role reset for older browsers */
article,
aside,
details,
figcaption,
figure,
footer,
header,
hgroup,
menu,
nav,
section,
main {
  display: block;
}
body {
  line-height: 1;
}
ol,
ul {
  list-style: none;
}
blockquote,
q {
  quotes: none;
}
blockquote:before,
blockquote:after,
q:before,
q:after {
  content: "";
  content: none;
}
table {
  border-collapse: collapse;
  border-spacing: 0;
}
*,
*::after,
*::before {
  -webkit-box-sizing: border-box;
  -moz-box-sizing: border-box;
  box-sizing: border-box;
}

html {
  font-size: 62.5%;
}

html * {
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

body {
  font-size: 1.6rem;
  font-family: "Open Sans", sans-serif;
  color: #2d3d4f;
  background-color: #1bbc9d;
}

a {
  text-decoration: none;
}

.pricing-container {
  width: 90%;
  max-width: 1170px;
  margin: 4em auto;
}

.pricing-container {
  margin: 6em auto;
}
.pricing-container.full-width {
  width: 100%;
  max-width: none;
}

.pricing-switcher {
  text-align: center;
}

.pricing-switcher .fieldset {
  display: inline-block;
  position: relative;
  padding: 2px;
  border-radius: 50em;
  border: 2px solid #2d3e50;
}

.pricing-switcher input[type="radio"] {
  position: absolute;
  opacity: 0;
}

.pricing-switcher label {
  position: relative;
  z-index: 1;
  display: inline-block;
  float: left;
  width: 90px;
  height: 40px;
  line-height: 40px;
  cursor: pointer;
  font-size: 1.4rem;
  color: #ffffff;
}

.pricing-switcher .switch {
  position: absolute;
  top: 2px;
  left: 2px;
  height: 40px;
  width: 90px;
  background-color: #2d3e50;
  border-radius: 50em;
  -webkit-transition: -webkit-transform 0.5s;
  -moz-transition: -moz-transform 0.5s;
  transition: transform 0.5s;
}

.pricing-switcher input[type="radio"]:checked + label + .switch,
.pricing-switcher input[type="radio"]:checked + label:nth-of-type(n) + .switch {
  -webkit-transform: translateX(90px);
  -moz-transform: translateX(90px);
  -ms-transform: translateX(90px);
  -o-transform: translateX(90px);
  transform: translateX(90px);
}

.no-js .pricing-switcher {
  display: none;
}

.pricing-list {
  margin: 2em 0 0;
}

.pricing-list > li {
  position: relative;
  margin-bottom: 1em;
}

@media only screen and (min-width: 768px) {
  .pricing-list {
    margin: 3em 0 0;
  }
  .pricing-list:after {
    content: "";
    display: table;
    clear: both;
  }
  .pricing-list > li {
    width: 33.3333333333%;
    float: left;
    padding-left: 5px;
    padding-right: 5px;
  }
  .has-margins .pricing-list > li {
    width: 32.3333333333%;
    float: left;
    margin-right: 1.5%;
  }
  .has-margins .pricing-list > li:last-of-type {
    margin-right: 0;
  }
}

.pricing-wrapper {
  position: relative;
}

.touch .pricing-wrapper {
  -webkit-perspective: 2000px;
  -moz-perspective: 2000px;
  perspective: 2000px;
}

.pricing-wrapper.is-switched .is-visible {
  -webkit-transform: rotateY(180deg);
  -moz-transform: rotateY(180deg);
  -ms-transform: rotateY(180deg);
  -o-transform: rotateY(180deg);
  transform: rotateY(180deg);
  -webkit-animation: rotate 0.5s;
  -moz-animation: rotate 0.5s;
  animation: rotate 0.5s;
}

.pricing-wrapper.is-switched .is-hidden {
  -webkit-transform: rotateY(0);
  -moz-transform: rotateY(0);
  -ms-transform: rotateY(0);
  -o-transform: rotateY(0);
  transform: rotateY(0);
  -webkit-animation: rotate-inverse 0.5s;
  -moz-animation: rotate-inverse 0.5s;
  animation: rotate-inverse 0.5s;
  opacity: 0;
}

.pricing-wrapper.is-switched .is-selected {
  opacity: 1;
}

.pricing-wrapper.is-switched.reverse-animation .is-visible {
  -webkit-transform: rotateY(-180deg);
  -moz-transform: rotateY(-180deg);
  -ms-transform: rotateY(-180deg);
  -o-transform: rotateY(-180deg);
  transform: rotateY(-180deg);
  -webkit-animation: rotate-back 0.5s;
  -moz-animation: rotate-back 0.5s;
  animation: rotate-back 0.5s;
}

.pricing-wrapper.is-switched.reverse-animation .is-hidden {
  -webkit-transform: rotateY(0);
  -moz-transform: rotateY(0);
  -ms-transform: rotateY(0);
  -o-transform: rotateY(0);
  transform: rotateY(0);
  -webkit-animation: rotate-inverse-back 0.5s;
  -moz-animation: rotate-inverse-back 0.5s;
  animation: rotate-inverse-back 0.5s;
  opacity: 0;
}

.pricing-wrapper.is-switched.reverse-animation .is-selected {
  opacity: 1;
}

.pricing-wrapper > li {
  background-color: #ffffff;
  -webkit-backface-visibility: hidden;
  backface-visibility: hidden;
  outline: 1px solid transparent;
}

.pricing-wrapper > li::after {
  content: "";
  position: absolute;
  top: 0;
  right: 0;
  height: 100%;
  width: 50px;
  pointer-events: none;
  background: -webkit-linear-gradient(right, #ffffff, rgba(255, 255, 255, 0));
  background: linear-gradient(to left, #ffffff, rgba(255, 255, 255, 0));
}

.pricing-wrapper > li.is-ended::after {
  display: none;
}

.pricing-wrapper .is-visible {
  position: relative;
  z-index: 5;
}

.pricing-wrapper .is-hidden {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  width: 100%;
  z-index: 1;
  -webkit-transform: rotateY(180deg);
  -moz-transform: rotateY(180deg);
  -ms-transform: rotateY(180deg);
  -o-transform: rotateY(180deg);
  transform: rotateY(180deg);
}

.pricing-wrapper .is-selected {
  z-index: 3 !important;
}

@media only screen and (min-width: 768px) {
  .pricing-wrapper > li::before {
    content: "";
    position: absolute;
    z-index: 6;
    left: -1px;
    top: 50%;
    bottom: auto;
    -webkit-transform: translateY(-50%);
    -moz-transform: translateY(-50%);
    -ms-transform: translateY(-50%);
    -o-transform: translateY(-50%);
    transform: translateY(-50%);
    height: 50%;
    width: 1px;
    background-color: #b1d6e8;
  }
  .pricing-wrapper > li::after {
    display: none;
  }
  .exclusive .pricing-wrapper > li {
    box-shadow: inset 0 0 0 3px #2d3e50;
  }
  .has-margins .pricing-wrapper > li,
  .has-margins .exclusive .pricing-wrapper > li {
    box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
  }
  :nth-of-type(1) > .pricing-wrapper > li::before {
    display: none;
  }
  .has-margins .pricing-wrapper > li {
    border-radius: 4px 4px 6px 6px;
  }
  .has-margins .pricing-wrapper > li::before {
    display: none;
  }
}

@media only screen and (min-width: 1500px) {
  .full-width .pricing-wrapper > li {
    padding: 2.5em 0;
  }
}

.no-js .pricing-wrapper .is-hidden {
  position: relative;
  -webkit-transform: rotateY(0);
  -moz-transform: rotateY(0);
  -ms-transform: rotateY(0);
  -o-transform: rotateY(0);
  transform: rotateY(0);
  margin-top: 1em;
}

@media only screen and (min-width: 768px) {
  .exclusive .pricing-wrapper > li::before {
    display: none;
  }
  .exclusive + li .pricing-wrapper > li::before {
    display: none;
  }
}

.pricing-header h2 {
  padding: 0.9em 0.9em 0.6em;
  font-weight: 400;
  margin-bottom: 30px;
  margin-top: 10px;
  text-transform: uppercase;
  text-align: center;
}

.pricing-header {
  height: auto;
  padding: 1.9em 0 1.6em;
  pointer-events: auto;
  text-align: center;
  color: #173d50;
  background-color: transparent;
}

.exclusive .pricing-header {
  color: #1bbc9d;
  background-color: transparent;
}

.pricing-header h2 {
  font-size: 2.8rem;
  letter-spacing: 2px;
}

.currency,
.value {
  font-size: 3rem;
  font-weight: 300;
}

.duration {
  font-weight: 700;
  font-size: 1.3rem;
  color: #8dc8e4;
  text-transform: uppercase;
}

.exclusive .duration {
  color: #f3b6ab;
}

.duration::before {
  content: "/";
  margin-right: 2px;
}

.value {
  font-size: 7rem;
  font-weight: 300;
}

.currency,
.duration {
  color: #1bbc9d;
}

.exclusive .currency,
.exclusive .duration {
  color: #2d3e50;
}

.currency {
  display: inline-block;
  margin-top: 10px;
  vertical-align: top;
  font-size: 2rem;
  font-weight: 700;
}

.duration {
  font-size: 1.4rem;
}

.pricing-body {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.is-switched .pricing-body {
  overflow: hidden;
}

.pricing-body {
  overflow-x: visible;
}

.pricing-features {
  width: 600px;
}

.pricing-features:after {
  content: "";
  display: table;
  clear: both;
}

.pricing-features li {
  width: 100px;
  float: left;
  padding: 1.6em 1em;
  font-size: 1.5rem;
  text-align: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pricing-features em {
  display: block;
  margin-bottom: 5px;
  font-weight: 600;
}

.pricing-features {
  width: auto;
}

.pricing-features li {
  float: none;
  width: auto;
  padding: 1em;
}

.exclusive .pricing-features li {
  margin: 0 3px;
}

.pricing-features em {
  display: inline-block;
  margin-bottom: 0;
}

.has-margins .exclusive .pricing-features li {
  margin: 0;
}

.pricing-footer {
  position: absolute;
  z-index: 1;
  top: 0;
  left: 0;
  height: 80px;
  width: 100%;
}

.pricing-footer {
  position: relative;
  height: auto;
  padding: 1.8em 0;
  text-align: center;
}

.pricing-footer::after {
  display: none;
}

.has-margins .pricing-footer {
  padding-bottom: 0;
}

.select {
  position: relative;
  z-index: 1;
  display: block;
  height: 100%;
  overflow: hidden;
  text-indent: 100%;
  white-space: nowrap;
  color: transparent;
}

.select {
  position: static;
  display: inline-block;
  height: auto;
  padding: 1.3em 2em;
  color: #1bbc9d;
  border-radius: 8px;
  border: 2px solid #1bbc9d;
  font-size: 1.4rem;
  text-indent: 0;
  text-transform: uppercase;
  letter-spacing: 2px;
  transition: all 0.6s;
  width: 70%;
}

.no-touch .select:hover {
  background-color: #1bbc9d;
  color: #ffffff;
}

.exclusive .select {
  background-color: #1bbc9d;
  color: #ffffff;
}

.no-touch .exclusive .select:hover {
  background-color: #24e0ba;
}

.secondary-theme .exclusive .select {
  background-color: #1bbc9d;
}

.no-touch .secondary-theme .exclusive .select:hover {
  background-color: #112e3c;
}

.has-margins .select {
  display: block;
  padding: 1.7em 0;
  border-radius: 0 0 4px 4px;
}

@-webkit-keyframes rotate {
  0% {
    -webkit-transform: perspective(2000px) rotateY(0);
  }
  70% {
    -webkit-transform: perspective(2000px) rotateY(200deg);
  }
  100% {
    -webkit-transform: perspective(2000px) rotateY(180deg);
  }
}

@-moz-keyframes rotate {
  0% {
    -moz-transform: perspective(2000px) rotateY(0);
  }
  70% {
    -moz-transform: perspective(2000px) rotateY(200deg);
  }
  100% {
    -moz-transform: perspective(2000px) rotateY(180deg);
  }
}

@keyframes rotate {
  0% {
    -webkit-transform: perspective(2000px) rotateY(0);
    -moz-transform: perspective(2000px) rotateY(0);
    -ms-transform: perspective(2000px) rotateY(0);
    -o-transform: perspective(2000px) rotateY(0);
    transform: perspective(2000px) rotateY(0);
  }
  70% {
    -webkit-transform: perspective(2000px) rotateY(200deg);
    -moz-transform: perspective(2000px) rotateY(200deg);
    -ms-transform: perspective(2000px) rotateY(200deg);
    -o-transform: perspective(2000px) rotateY(200deg);
    transform: perspective(2000px) rotateY(200deg);
  }
  100% {
    -webkit-transform: perspective(2000px) rotateY(180deg);
    -moz-transform: perspective(2000px) rotateY(180deg);
    -ms-transform: perspective(2000px) rotateY(180deg);
    -o-transform: perspective(2000px) rotateY(180deg);
    transform: perspective(2000px) rotateY(180deg);
  }
}

@-webkit-keyframes rotate-inverse {
  0% {
    -webkit-transform: perspective(2000px) rotateY(-180deg);
  }
  70% {
    -webkit-transform: perspective(2000px) rotateY(20deg);
  }
  100% {
    -webkit-transform: perspective(2000px) rotateY(0);
  }
}

@-moz-keyframes rotate-inverse {
  0% {
    -moz-transform: perspective(2000px) rotateY(-180deg);
  }
  70% {
    -moz-transform: perspective(2000px) rotateY(20deg);
  }
  100% {
    -moz-transform: perspective(2000px) rotateY(0);
  }
}

@keyframes rotate-inverse {
  0% {
    -webkit-transform: perspective(2000px) rotateY(-180deg);
    -moz-transform: perspective(2000px) rotateY(-180deg);
    -ms-transform: perspective(2000px) rotateY(-180deg);
    -o-transform: perspective(2000px) rotateY(-180deg);
    transform: perspective(2000px) rotateY(-180deg);
  }
  70% {
    -webkit-transform: perspective(2000px) rotateY(20deg);
    -moz-transform: perspective(2000px) rotateY(20deg);
    -ms-transform: perspective(2000px) rotateY(20deg);
    -o-transform: perspective(2000px) rotateY(20deg);
    transform: perspective(2000px) rotateY(20deg);
  }
  100% {
    -webkit-transform: perspective(2000px) rotateY(0);
    -moz-transform: perspective(2000px) rotateY(0);
    -ms-transform: perspective(2000px) rotateY(0);
    -o-transform: perspective(2000px) rotateY(0);
    transform: perspective(2000px) rotateY(0);
  }
}

@-webkit-keyframes rotate-back {
  0% {
    -webkit-transform: perspective(2000px) rotateY(0);
  }
  70% {
    -webkit-transform: perspective(2000px) rotateY(-200deg);
  }
  100% {
    -webkit-transform: perspective(2000px) rotateY(-180deg);
  }
}

@-moz-keyframes rotate-back {
  0% {
    -moz-transform: perspective(2000px) rotateY(0);
  }
  70% {
    -moz-transform: perspective(2000px) rotateY(-200deg);
  }
  100% {
    -moz-transform: perspective(2000px) rotateY(-180deg);
  }
}

@keyframes rotate-back {
  0% {
    -webkit-transform: perspective(2000px) rotateY(0);
    -moz-transform: perspective(2000px) rotateY(0);
    -ms-transform: perspective(2000px) rotateY(0);
    -o-transform: perspective(2000px) rotateY(0);
    transform: perspective(2000px) rotateY(0);
  }
  70% {
    -webkit-transform: perspective(2000px) rotateY(-200deg);
    -moz-transform: perspective(2000px) rotateY(-200deg);
    -ms-transform: perspective(2000px) rotateY(-200deg);
    -o-transform: perspective(2000px) rotateY(-200deg);
    transform: perspective(2000px) rotateY(-200deg);
  }
  100% {
    -webkit-transform: perspective(2000px) rotateY(-180deg);
    -moz-transform: perspective(2000px) rotateY(-180deg);
    -ms-transform: perspective(2000px) rotateY(-180deg);
    -o-transform: perspective(2000px) rotateY(-180deg);
    transform: perspective(2000px) rotateY(-180deg);
  }
}

@-webkit-keyframes rotate-inverse-back {
  0% {
    -webkit-transform: perspective(2000px) rotateY(180deg);
  }
  70% {
    -webkit-transform: perspective(2000px) rotateY(-20deg);
  }
  100% {
    -webkit-transform: perspective(2000px) rotateY(0);
  }
}

@-moz-keyframes rotate-inverse-back {
  0% {
    -moz-transform: perspective(2000px) rotateY(180deg);
  }
  70% {
    -moz-transform: perspective(2000px) rotateY(-20deg);
  }
  100% {
    -moz-transform: perspective(2000px) rotateY(0);
  }
}

@keyframes rotate-inverse-back {
  0% {
    -webkit-transform: perspective(2000px) rotateY(180deg);
    -moz-transform: perspective(2000px) rotateY(180deg);
    -ms-transform: perspective(2000px) rotateY(180deg);
    -o-transform: perspective(2000px) rotateY(180deg);
    transform: perspective(2000px) rotateY(180deg);
  }
  70% {
    -webkit-transform: perspective(2000px) rotateY(-20deg);
    -moz-transform: perspective(2000px) rotateY(-20deg);
    -ms-transform: perspective(2000px) rotateY(-20deg);
    -o-transform: perspective(2000px) rotateY(-20deg);
    transform: perspective(2000px) rotateY(-20deg);
  }
  100% {
    -webkit-transform: perspective(2000px) rotateY(0);
    -moz-transform: perspective(2000px) rotateY(0);
    -ms-transform: perspective(2000px) rotateY(0);
    -o-transform: perspective(2000px) rotateY(0);
    transform: perspective(2000px) rotateY(0);
  }
}

.pricing-footer button {
    background-color:#FFF;
}
</style>



<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-MJTDHVS');</script>

<script>

jQuery(document).ready(function($){
	//hide the subtle gradient layer (.pricing-list > li::after) when pricing table has been scrolled to the end (mobile version only)
	checkScrolling($('.pricing-body'));
	$(window).on('resize', function(){
		window.requestAnimationFrame(function(){checkScrolling($('.pricing-body'))});
	});
	$('.pricing-body').on('scroll', function(){ 
		var selected = $(this);
		window.requestAnimationFrame(function(){checkScrolling(selected)});
	});

	function checkScrolling(tables){
		tables.each(function(){
			var table= $(this),
				totalTableWidth = parseInt(table.children('.pricing-features').width()),
		 		tableViewport = parseInt(table.width());
			if( table.scrollLeft() >= totalTableWidth - tableViewport -1 ) {
				table.parent('li').addClass('is-ended');
			} else {
				table.parent('li').removeClass('is-ended');
			}
		});
	}

	//switch from monthly to annual pricing tables
	bouncy_filter($('.pricing-container'));

	function bouncy_filter(container) {
		container.each(function(){
			var pricing_table = $(this);
			var filter_list_container = pricing_table.children('.pricing-switcher'),
				filter_radios = filter_list_container.find('input[type="radio"]'),
				pricing_table_wrapper = pricing_table.find('.pricing-wrapper');

			//store pricing table items
			var table_elements = {};
			filter_radios.each(function(){
				var filter_type = $(this).val();
				table_elements[filter_type] = pricing_table_wrapper.find('li[data-type="'+filter_type+'"]');
			});

			//detect input change event
			filter_radios.on('change', function(event){
				event.preventDefault();
				//detect which radio input item was checked
				var selected_filter = $(event.target).val();

				//give higher z-index to the pricing table items selected by the radio input
				show_selected_items(table_elements[selected_filter]);

				//rotate each pricing-wrapper 
				//at the end of the animation hide the not-selected pricing tables and rotate back the .pricing-wrapper
				
				if( !Modernizr.cssanimations ) {
					hide_not_selected_items(table_elements, selected_filter);
					pricing_table_wrapper.removeClass('is-switched');
				} else {
					pricing_table_wrapper.addClass('is-switched').eq(0).one('webkitAnimationEnd oanimationend msAnimationEnd animationend', function() {		
						hide_not_selected_items(table_elements, selected_filter);
						pricing_table_wrapper.removeClass('is-switched');
						//change rotation direction if .pricing-list has the .bounce-invert class
						if(pricing_table.find('.pricing-list').hasClass('bounce-invert')) pricing_table_wrapper.toggleClass('reverse-animation');
					});
				}
			});
		});
	}
	function show_selected_items(selected_elements) {
		selected_elements.addClass('is-selected');
	}

	function hide_not_selected_items(table_containers, filter) {
		$.each(table_containers, function(key, value){
	  		if ( key != filter ) {	
				$(this).removeClass('is-visible is-selected').addClass('is-hidden');

			} else {
				$(this).addClass('is-visible').removeClass('is-hidden is-selected');
			}
		});
	}


});

var new_user_approve_logo = url("./image/new-user-logo.png");
function buy_now(selected_plan_id, selected_billing_cycle, licenses) {

    var handler = FS.Checkout.configure({

         plugin_id:  '5930',
         plan_id:    selected_plan_id,
         public_key: 'pk_4c854593bf607fd795264061bbf57',
         image:      new_user_approve_logo,
         billing_cycle: selected_billing_cycle

    });
    
    var productName = 'New User Approve | Plugin'; 
    var plugin_id=  '5930';
         var plan_id=    selected_plan_id;
         var public_key= 'pk_4c854593bf607fd795264061bbf57';
         var image=      new_user_approve_logo;
        var  billing_cycle= selected_billing_cycle;
    handler.open({

        name     : productName,
        //licenses : licenses,
        purchaseCompleted  : function (response) {
                // The logic here will be executed immediately after the purchase confirmation.
                // alert(response.user.email);
                var trial = response.purchase.trial_ends !== null,
                total = response.purchase.initial_amount.toString(),
                storeUrl = window.location.href,
                storeName = 'New User Approve';

            var dataLayer  = window.dataLayer || [];
            var test = dataLayer.push({
                'event': 'transaction',
                'ecommerce': {
                    'purchase': {
                    'actionField': {
                        'id': response.purchase.id.toString(),        // Transaction ID. Required           
                        'revenue': total,   // Total transaction value (incl. tax and shipping)
                        'tax': 0,
                        'shipping': 0,
                        'license': licenses,
                        'plugin_name': productName,
                        'plugin_id': plugin_id,
                        'plan_id': plan_id,
                        'url': storeUrl,
                    },
                    'products': [
                        {          
                            'name': productName,  // Name or ID is required.
                            'id': plugin_id,
                            'price': total,  
                            'category': 'Plugin',                              
                            'quantity': '1',
                        }
                    ]
                    }
                }
            });
        },
        success  : function (response) {
            
            
        }

    });
    
}

</script>

<div class="pricing-container">
		<div class="pricing-switcher">
			<p class="fieldset">
				<input type="radio" name="duration-1" value="annually" id="annually-1" checked>
				<label for="annually-1">Annually</label>
				<input type="radio" name="duration-1" value="lifetime" id="lifetime-1">
				<label for="lifetime-1">Lifetime</label>
				<span class="switch"></span>
			</p>
		</div>
		<ul class="pricing-list bounce-invert">
			<li>
				<ul class="pricing-wrapper">
					<li data-type="annually" class="is-visible">
						<header class="pricing-header">
							<h2>1 SITE PLAN</h2>
							<div class="price">
								<span class="currency">$</span>
								<span class="value">39</span>
								<span class="duration">Anually</span>
							</div>
						</header>
						<div class="pricing-body">
							<ul class="pricing-features">
								<li>Regular Plugin Updates</li>
								<li>Unlimited Support</li>
								<li>All Core Features Included</li>
								<li>Premium Customization Features</li>
								<li>Auto-Renew Subscription</li>
							</ul>
						</div>
						<footer class="pricing-footer">
							<button onclick="buy_now(11181, 'annual', 1);" class="select">Buy Now</button>
						</footer>
					</li>
					<li data-type="lifetime" class="is-hidden">
						<header class="pricing-header">
							<h2>1 SITE PLAN</h2>
							<div class="price">
								<span class="currency">$</span>
								<span class="value">99</span>
								<span class="duration">one-time</span>
							</div>
						</header>
						<div class="pricing-body">
							<ul class="pricing-features">
								<li>Regular Plugin Updates</li>
								<li>Unlimited Support</li>
								<li>All Core Features Included</li>
								<li>Premium Customization Features</li>
								<li>Auto-Renew Subscription</li>
							</ul>
						</div>
						<footer class="pricing-footer">
							<button onclick="buy_now(12533, 'lifetime', 1);" class="select">Buy Now</button>
						</footer>
					</li>
				</ul>
			</li>
			<li class="exclusive">
				<ul class="pricing-wrapper">
					<li data-type="annually" class="is-visible">
						<header class="pricing-header">
							<h2>5 SITES PLAN</h2>
							<div class="price">
								<span class="currency">$</span>
								<span class="value">99</span>
								<span class="duration">Anually</span>
							</div>
						</header>
						<div class="pricing-body">
							<ul class="pricing-features">
								<li>Regular Plugin Updates</li>
								<li>Unlimited Support</li>
								<li>All Core Features Included</li>
								<li>Premium Customization Features</li>
								<li>Auto-Renew Subscription</li>
							</ul>
						</div>
						<footer class="pricing-footer">
							<button onclick="buy_now(11182, 'annual', 5);" class="select">Buy Now</button>
						</footer>
					</li>
					<li data-type="lifetime" class="is-hidden">
						<header class="pricing-header">
							<h2>5 SITES PLAN</h2>
							<div class="price">
								<span class="currency">$</span>
								<span class="value">249</span>
								<span class="duration">one-time</span>
							</div>
						</header>
						<div class="pricing-body">
							<ul class="pricing-features">
								<li>Regular Plugin Updates</li>
								<li>Unlimited Support</li>
								<li>All Core Features Included</li>
								<li>Premium Customization Features</li>
								<li>Auto-Renew Subscription</li>
							</ul>
						</div>
						<footer class="pricing-footer">
							<button onclick="buy_now(12534, 'lifetime', 5);" class="select">Buy Now</button>
						</footer>
					</li>
				</ul>
			</li>
			<li>
				<ul class="pricing-wrapper">
					<li data-type="annually" class="is-visible">
						<header class="pricing-header">
							<h2>30 SITES PLAN</h2>
							<div class="price">
								<span class="currency">$</span>
								<span class="value">199</span>
								<span class="duration">Annualy</span>
							</div>
						</header>
						<div class="pricing-body">
							<ul class="pricing-features">
								<li>Regular Plugin Updates</li>
								<li>Unlimited Support</li>
								<li>All Core Features Included</li>
								<li>Premium Customization Features</li>
								<li>Auto-Renew Subscription</li>
							</ul>
						</div>
						<footer class="pricing-footer">
							<button onclick="buy_now(11183, 'annual', 30);" class="select">Buy Now</button>
						</footer>
					</li>
					<li data-type="lifetime" class="is-hidden">
						<header class="pricing-header">
							<h2>30 SITES PLAN</h2>
							<div class="price">
								<span class="currency">$</span>
								<span class="value">599</span>
								<span class="duration">one-time</span>
							</div>
						</header>
						<div class="pricing-body">
							<ul class="pricing-features">
								<li>Regular Plugin Updates</li>
								<li>Unlimited Support</li>
								<li>All Core Features Included</li>
								<li>Premium Customization Features</li>
								<li>Auto-Renew Subscription</li>
							</ul>
						</div>
						<footer class="pricing-footer">
							<button onclick="buy_now(12535, 'lifetime', 30);" class="select">Buy Now</button>
						</footer>
					</li>
				</ul>
			</li>
		</ul>
	</div>