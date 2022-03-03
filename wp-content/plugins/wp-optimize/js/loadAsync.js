/**
 * This function will work cross-browser for loading scripts asynchronously
 */
function loadAsync(src, callback) {
	var scriptTag,
		ready = false;

	scriptTag = document.createElement('script');
	scriptTag.type = 'text/javascript';
	scriptTag.src = src;
	scriptTag.onreadystatechange = function() {
		// console.log( this.readyState ); //uncomment this line to see which ready states are called.
		if (!ready
			&& (!this.readyState || this.readyState == 'complete')
		) {
			ready = true;
			typeof callback === 'function' && callback();
		}
	};
	scriptTag.onload = scriptTag.onreadystatechange
	
	document.getElementsByTagName("head")[0].appendChild(scriptTag)
}