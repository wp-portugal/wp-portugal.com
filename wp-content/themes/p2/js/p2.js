window.p2 = window.p2 || {};

(function( $ ){

	var p2 = window.p2,
		commentsLists = $(".commentlist"),
		edCanvas = document.getElementById('posttext'),
		locale = new wp.locale( wpLocale ),
		loggedin = false,

		// Spinner used when someone edits a comment, done once here vs. all over the shop
		commentEditSpinnerText = $( '<span class="progress spinner-comment-edit"></span>' ).spin( spinSmall ).css( { display: 'block' } ),
		commentEditSpinnerText = $( '<div>' ).append( commentEditSpinnerText.clone() ).html(),

		// Configuration for spin.js
		spinSmall = { color: '#ccc', length: 2, lines: 8, radius: 3, speed: '1.3', top: 1, left: 1, width: 2 };

	p2.initialize = function() {
		if ( p2StoredVersion < p2CurrentVersion ) {
			// P2 upgrades on admin_init.  If it needs it, send a noop admin-ajax call to trigger.
			$.ajax( ajaxUrl, {
				data: {
					action: 'p2.noop'
				},
				cache: false
			} );
		}

		// ------------------------------------------------
		// Init
		// ------------------------------------------------
		p2.keyboard();
		p2.comment.highlight();
		p2.utility.localizeMicroformatDates();

		// Initialize mention autocompletes if mention data exists.
		if ( mentionData )
			p2.autocomplete.mentions( $( '#posttext, #comment' ) );

		// Activate Tag Suggestions for logged users on front page
		if (isFrontPage && prologueTagsuggest && isUserLoggedIn)
			p2.autocomplete.tags( $( 'input[name="tags"]' ) );

		// Turn on automattic updating
		if (prologuePostsUpdates)
			p2.utility.toggleUpdates('unewposts');
		if (prologueCommentsUpdates)
			p2.utility.toggleUpdates('unewcomments');

		// Activate autgrow on textareas
		p2.utility.autoGrowTextarea($('#posttext, #comment'));

		// Activate tooltips on recent-comments widget
		p2.utility.tooltip($("table.p2-recent-comments a.tooltip"));

		// Hide error messages on load
		$('#posttext_error, #commenttext_error').hide();

		// Bind actions to comments and posts
		$( 'body .post, body .page' ).each( function() { p2.utility.bindActions( this, 'post' ); } );
		$( 'body .comment' ).each( function() { p2.utility.bindActions( this, 'comment' ); } );

		// Check which posts are visibles and add to array and comment querystring
		$("#main > ul > li[id]").each(function() {
			var thisId = $(this).attr('id');
			vpostId = thisId.substring(thisId.indexOf('-') + 1);
			postsOnPage.push(thisId);
			postsOnPageQS += "&vp[]=" + vpostId;
		});

		// Check if recent comments widget is loaded
		if	($("table.p2-recent-comments").length != 0)
			lcwidget = true;

		$(".single #postlist li > div.postcontent, .single #postlist li > h4, li[id^='prologue'] > div.postcontent, li[id^='comment'] > div.commentcontent, li[id^='prologue'] > h4, li[id^='comment'] > h4").hover(function() {
			$(this).parents("li").eq(0).addClass('selected');
		}, function() {
			$(this).parents("li").eq(0).removeClass('selected');
		});

		$.ajaxSetup({
		  timeout: updateRate - 2000,
		  cache: false
		});

		// Only focus textarea if not not on Customizer and URL has no hash
		if( ! window.location.href.match('#') && ! ( ( window.wp || {} ).customize instanceof Function) ) {
			$('#posttext').focus();
		}

		// ------------------------------------------------
		// Events
		// ------------------------------------------------
		$(window).bind('beforeunload', function(e) {
			if ( $( '#posttext' ).val() || $( '#comment' ).val() ) {
				var e = e || window.event;
				if (e) { // For IE and Firefox
					e.returnValue = p2txt.unsaved_changes;
				}
				return p2txt.unsaved_changes;   // For Safari
			}
		});

		// Catch new posts submit
		$("#new_post").submit(function(trigger) {
			if ( $( 'ul.ui-autocomplete' ).is( ':visible' ) )
				return false;

			p2.posts.submit(trigger);
			trigger.preventDefault();
		});

		// Catch new comment submit
		$("#commentform").bind( 'submit', function(trigger) {
			p2.comment.submit(trigger);
			trigger.preventDefault();
			$(this).parents("li").removeClass('replying');
			$(this).parents('#respond').prev("li").removeClass('replying');
		});

		// Let's do a check for logged-in (and maybe being offline) status, since P2 thinks current user is logged in.
		if ( isUserLoggedIn ) {
			// If something is wrong (offline, not logged in) display a nice message to the screen.
			$( '.inputarea, #comment, .comment-reply-link, #comment-submit' ).click( function( event ) {
				$.ajax({
					type: 'POST',
					url: ajaxReadUrl +'&action=logged_in_out&_loggedin=' + nonce,
					success: function( response ) {
						if ( 'logged_in' != response ) {
							// Authentication failure, this message should persist on the screen so the logged out user can click the login link.
							p2.utility.notification( p2txt.oops_not_logged_in + ' <a href="' + login_url + '">' + p2txt.please_log_in + '</a>.', true );
							event.preventDefault();
							return false;
						} else {
							loggedin = true;
						}
					},
					error: function() {
						// Houston, we have a problem.
						p2.utility.notification( p2txt.whoops_maybe_offline );
						event.preventDefault();
						return false;
					}
				});
			});
		}

		$( '.progress' ).spin( spinSmall );

		// Check if new comments or updates appear on scroll and fade out
		$(window).scroll(function() { p2.utility.removeYellow(); });
		$('#cancel-comment-reply-link').click(function() {
			$('#comment').val('');
			if (!isSingle) $("#respond").hide();
			$(this).parents("li").removeClass('replying');
			$(this).parents('#respond').prev("li").removeClass('replying');
			$("#respond").removeClass('replying');
		});

		$("#directions-keyboard").click(function(){
			$('#help').toggle();
			return false;
		});

		$(".show-comments").click(function(){
			var commentList = $(this).closest('.post').find('.commentlist');
			if (isPage) {
				commentList = $('.page .commentlist');
			}
			if (commentList.css('display') == 'none') {
				commentList.show();
			} else {
				commentList.hide();
			}
			return false;
		});

		$("#help").click(function() {
			$(this).toggle();
		});
	};

	p2.autocomplete = {

		// ------------------------------------------------
		// Initialize mentions autocomplete
		// ------------------------------------------------
		mentions: function( element ) {
			element.autocomplete({
				match: /\B@(\w*)$/,
				html: true,
				delay: 10,
				multiValue: true,
				minLength: 1,
				autoFocus: true,

				source: function( request, response ) {
					var matcher = new RegExp( $.ui.autocomplete.escapeRegex( request.term ), "ig" );

					response( $.grep( mentionData, function( mention ) {
						// Generate a label with bolded matches.
						var value = mention.name + ' @' + mention.username,
							label = value.replace( matcher, '<strong>$&</strong>' );

						// Set the new label (with gravatar).
						mention.label = mention.gravatar + ' ' + label;

						return label !== value;
					}) );
				},
				select: function( event, ui ) {
					Caret( this ).replace( '@' + ui.item.username );
					return false;
				},
				// We'll enabling autocomplete only when @ is pressed.
				disabled: true,
				// If a term isn't found disable autocomplete until @ is pressed again.
				matched: function( event, ui ) {
					if ( ui.term === false )
						$( this ).autocomplete( 'disable' );
				}
			});

			// Aggressively limit autocomplete.
			// Only enable it when @ has been recently pressed.
			element.bind( 'keypress', function( event ) {
				switch ( event.which ) {
					case 64: // AT key
						$(this).autocomplete( 'enable' );
						break;
					case $.ui.keyCode.SPACE:
					case $.ui.keyCode.ESCAPE:
					case $.ui.keyCode.ENTER:
						$(this).autocomplete( 'disable' );
						break;
				}
			});
		},

		// ------------------------------------------------
		// Initialize tags autocomplete
		// ------------------------------------------------
		tags: function( element ) {
			element.autocomplete({
				match: /,?\s*([^,]*)$/,
				html: true,
				multiValue: true,
				minLength: 2,
				source: function( request, response ) {
					$.getJSON( ajaxReadUrl, {
						action: 'tag_search',
						term:   request.term
					}, response );
				},
				select: function( event, ui ) {
					Caret( this ).replace( ui.item.value + ', ', { before: /[^,\s]+[^,]*$/ });
					return false;
				}
			});
		}
	};

	p2.comment = {

		// ------------------------------------------------
		// Check for new comments, then loads them inline and into the recent-comments widget
		// ------------------------------------------------
		fetch: function( showNotification ) {
			if (showNotification == null) {
				showNotification = true;
			}
			p2.utility.toggleUpdates('unewcomments');
			var queryString = ajaxReadUrl +'&action=get_latest_comments&load_time=' + pageLoadTime + '&lcwidget=' + lcwidget;
			queryString += postsOnPageQS;

			ajaxCheckComments = $.getJSON(queryString, function(newComments) {
				if (newComments != null) {
					$.each(newComments.comments, function(i,comment) {
						pageLoadTime = newComments.lastcommenttime;
						if (comment.widgetHtml) {
							p2.comment.insert.widget(comment.widgetHtml);
						}
						if (comment.html != '') {
							var thisParentId = 'prologue-'+comment.postID;
							p2.comment.insert.inline(thisParentId, comment.commentParent, comment.html, showNotification);
						}
					});
					if (showNotification) {
						p2.utility.notification(p2txt.n_new_comments.replace('%d', newComments.numberofnewcomments));
					}
				}
			});
			p2.utility.toggleUpdates('unewcomments');
		},
		// ------------------------------------------------
		// Highlight comments on permalink pages
		// ------------------------------------------------
		highlight: function() {
			var comment_id = ( location.hash.match( /^#comment-[0-9]+$/ ) || [] ).pop(),
				highlighter;

			// Check for comment permalinks, which use hashes like #comment-1234
			if ( ! comment_id )
				return;

			highlighter = function() {
				var $el, origColor;

				// Cache the comment element and make sure it exists
				$el = $( comment_id );
				if ( ! $el )
					return;

				// Cache the original background color so we can restore it later
				origColor = $el.css( 'backgroundColor' );

				// Highlight the comment, then fade away the highlight
				$el.css( { backgroundColor: '#F6F3D1' } ).animate( { backgroundColor: origColor }, 2000 );
			};

			// Bind ready handler
			$( highlighter );
		},
		insert: {

			// ------------------------------------------------
			// Insert new comment inline
			// ------------------------------------------------
			inline: function( postParent, comment_parent, commentHtml, showNotification ) {
				postParent = "#"+postParent;
				$(postParent).children('ul.commentlist').show();
				$(postParent).children('.discussion').hide();
				if (0 == comment_parent) {
					if (0 == $(postParent).children('ul.commentlist').length) {
						$(postParent).append('<ul class="commentlist inlinecomments"></ul>');
						commentsLists = $("ul.commentlist");
					}
					$(postParent).children('ul.commentlist').append('<div class="temp_newComments_cnt"></div>');
					var newComment =  $(postParent).children('ul.commentlist').children('div.temp_newComments_cnt');
				} else {
					comment_parent = '#comment-' + comment_parent;
					//$(comment_parent).toggle();
					if (0 == $(comment_parent).children('ul.children').length) {
						$(comment_parent).append('<ul class="children"></ul>');
					}
					$(comment_parent).children('ul.children').append('<div class="temp_newComments_cnt"></div>');
					var newComment =  $(comment_parent).children('ul.children').children('div.temp_newComments_cnt');
				}

				newComment.html(commentHtml);
				var newCommentsLi = newComment.children('li');
				newCommentsLi.addClass("newcomment");
				newCommentsLi.slideDown( 200, function() {
					var cnt = newComment.contents();
					newComment.children('li.newcomment').each(function() {
						if (p2.utility.isElementVisible(this) && !showNotification) {
							$(this).animate({backgroundColor:'transparent'}, {duration: 1000}, function(){
								$(this).removeClass('newcomment');
							});
						}
						p2.utility.bindActions(this, 'comment');
					});
					p2.utility.localizeMicroformatDates(newComment);
					newComment.replaceWith(cnt);
				});
			},

			// ------------------------------------------------
			// Insert and animate new comments into recent comments widget
			// ------------------------------------------------
			widget: function( widgetHtml ) {
				$("table.p2-recent-comments").each(function() {
					var t = $(this);
					var avatar_size = t.attr('avatar');
					if (avatar_size == '-1') widgetHtml = widgetHtml.replace(/<td.*?<\/td>/, '');
					$("tbody", t).html( widgetHtml + $("tbody", t).html());
					var newCommentsElement = $("tbody tr:first", t);
					newCommentsElement.fadeIn("slow");
					$("tbody tr:last", t).fadeOut("slow").remove();
					p2.utility.tooltip($("tbody tr:first td a.tooltip", t));
					if (p2.utility.isElementVisible(newCommentsElement)) {
						$(newCommentsElement).removeClass('newcomment');
					}
				});
			}
		},
		// ------------------------------------------------
		// Submits a new comment via ajax
		// ------------------------------------------------
		submit: function( trigger ) {
			var thisForm = $(trigger.target);
			var thisFormElements = $('#comment, #comment-submit, :input', thisForm).not('input[type="hidden"]');
			var submitProgress = thisForm.find('span.progress');
			var commenttext = $.trim($('#comment', thisForm).val());

			if ('' == commenttext) {
				$("label#commenttext_error").text(p2txt.required_field).show().focus();
				return false;
			}

			if (!isPage)
				p2.utility.toggleUpdates('unewcomments');

			if (typeof ajaxCheckComments != "undefined")
				ajaxCheckComments.abort();

			if ('inline' == $("label#commenttext_error").css('display'))
				$("label#commenttext_error").hide();

			// WP3.1 Compat: Best to use only .prop()
			if ( 'prop' in thisFormElements )
				thisFormElements.prop( 'disabled', true );
			else
				thisFormElements.attr( 'disabled', 'disabled' );

			thisFormElements.addClass('disabled');

			submitProgress.show();

			// If P2 thinks current user is logged in, check again for being logged in,
			// and check for a working connection to the server, to avoid hanging comments.
			if ( isUserLoggedIn && ( 'logged_in' != p2.utility.loggedInOut() ) ) {
				submitProgress.hide();

				// WP3.1 Compat: Best to use only .prop()
				if ( 'prop' in thisFormElements )
					thisFormElements.prop( 'disabled', false );
				else
					thisFormElements.removeAttr( 'disabled', 'disabled' );

				thisFormElements.removeClass('disabled');
				return false;
			}

			var comment_post_ID = $('#comment_post_ID', thisForm).val();
			var comment_parent = $('#comment_parent', thisForm).val();

			var subscribe = 'false';
			if ( $( '#subscribe' ).attr( 'checked' ) ) // WP3.1 Compat: Best to use .prop()
				subscribe = 'subscribe';

			var subscribe_blog = 'false';
			if ( $( '#subscribe_blog' ).attr( 'checked' ) ) // WP3.1 Compat: Best to use .prop()
				subscribe_blog = 'subscribe';

			var dataString = {action: 'new_comment' , _ajax_post: nonce, comment: commenttext,  comment_parent: comment_parent, comment_post_ID: comment_post_ID, subscribe: subscribe, subscribe_blog: subscribe_blog};
			if (!isUserLoggedIn) {
				dataString['author'] = $('#author').val();
				dataString['email'] = $('#email').val();
				dataString['url'] = $('#url').val();
			}
			var errorMessage = '';
			$.ajax( {
				type: "POST",
				url: ajaxReadUrl, // The exception: WP's comment endpoint is "public" (i.e., not in /wp-admin/)
				data: dataString,
				success: function( result ) {
					var $form = $( "#respond" );

					$form.slideUp( 200, function() {
						submitProgress.fadeOut();

						var lastComment = $form.prev( "li" );
						if ( isNaN( result ) || 0 == result || 1 == result )
							errorMessage = result;
						$( '#comment' ).val( '' );
						if ( errorMessage != "" )
							p2.utility.notification( errorMessage );

						p2.comment.fetch( false );

						if ( ! isPage )
							p2.utility.toggleUpdates( 'unewcomments' );

						thisFormElements
							.prop( 'disabled', false )
							.removeClass( 'disabled' );
					} );
				}
			} );
		}
	};

	p2.keyboard = function() {

		// Activate keyboard navigation
		if (!isSingle)	{
			document.onkeypress = function(e) {
				e = e || window.event;
				if (e.target)
					element = e.target;
				else if (e.srcElement)
					element = e.srcElement;

				if( element.nodeType == 3)
					element = element.parentNode;

				if( e.ctrlKey == true || e.altKey == true || e.metaKey == true )
					return;

				var keyCode = (e.keyCode) ? e.keyCode : e.which;

				if (keyCode && (keyCode != 27 && (element.tagName == 'INPUT' || element.tagName == 'TEXTAREA') ) )
					return;

				switch(keyCode) {

					//  "c" key
					case 67:
						if (isFrontPage && isUserLoggedIn) {
							if (commentLoop) {
								$('#'+commentsOnPost[currComment]).removeClass('keyselected');
								$('#'+postsOnPage[currPost]).removeClass('commentloop').addClass('keyselected');
								commentLoop = false;
							} else {
								$('#'+postsOnPage[currPost]).removeClass('keyselected');
								currPost =- 1;
							}
						if (!p2.utility.isElementVisible("#postbox"))
							$.scrollTo('#postbox', 50);
						$("#posttext").focus();
						if (e.preventDefault)
							e.preventDefault();
						else
							e.returnValue = false;
						}
						break;

					//  "k" key
					case 75:
						if (!commentLoop) {
							if (currPost > 0) {
								$('#'+postsOnPage[currPost]).removeClass('keyselected').children('h4').trigger('mouseleave');
								currPost--;
								if (0 != $('#'+postsOnPage[currPost]).children('ul.commentlist').length && !hidecomments) {
									commentLoop = true;
									commentsOnPost.length = 0;
									$('#'+postsOnPage[currPost]).find("li[id^='comment']").each(function() {
										var thisId = $(this).attr('id');
										commentsOnPost.push(thisId);
									});
									currComment = commentsOnPost.length-1;
									$('#'+commentsOnPost[currComment]).addClass('keyselected').children('h4').trigger('mouseenter');
									if (!p2.utility.isElementVisible('#'+commentsOnPost[currComment]))
										$.scrollTo('#'+commentsOnPost[currComment], 150);
									return;
								}
								if (!p2.utility.isElementVisible('#'+postsOnPage[currPost]))
									$.scrollTo('#'+postsOnPage[currPost], 50);
								$('#'+postsOnPage[currPost]).addClass('keyselected').children('h4').trigger('mouseenter');
							} else {
								if (currPost <= 0) {
									$('#'+postsOnPage[currPost]).removeClass('keyselected').children('h4').trigger('mouseleave');
									$.scrollTo('#'+postsOnPage[postsOnPage.length-1], 50);
									currPost = postsOnPage.length-1;
									$('#'+postsOnPage[currPost]).addClass('keyselected').children('h4').trigger('mouseenter');
									return;
								}
							}
						} else {
							if (currComment > 0) {
								$('#'+commentsOnPost[currComment]).removeClass('keyselected').children('h4').trigger('mouseleave');
								currComment--;
								if (!p2.utility.isElementVisible('#'+commentsOnPost[currComment]))
									$.scrollTo('#'+commentsOnPost[currComment], 50);
								$('#'+commentsOnPost[currComment]).addClass('keyselected').children('h4').trigger('mouseenter');
							}
							else {
								if (currComment <= 0) {
									$('#'+commentsOnPost[currComment]).removeClass('keyselected').children('h4').trigger('mouseleave');
									$('#'+postsOnPage[currPost]).addClass('keyselected').children('h4').trigger('mouseenter');
									if (!p2.utility.isElementVisible('#'+postsOnPage[currPost]))
										$.scrollTo('#'+postsOnPage[currPost], 50);
									commentLoop = false;
									return;
								}
							}
						}
						break;

					// "j" key
					case 74:
						p2.utility.removeYellow();
						if (!commentLoop) {
							if (0 != $('#'+postsOnPage[currPost]).children('ul.commentlist').length && !hidecomments) {
								$.scrollTo('#'+postsOnPage[currPost], 150);
								commentLoop = true;
								currComment = 0;
								commentsOnPost.length = 0;
								$('#'+postsOnPage[currPost]).find("li[id^='comment']").each(function() {
									var thisId = $(this).attr('id');
									commentsOnPost.push(thisId);
								});
								$('#'+postsOnPage[currPost]).removeClass('keyselected').children('h4').trigger('mouseleave');
								$('#'+commentsOnPost[currComment]).addClass('keyselected').children('h4').trigger('mouseenter');
								return;
							}
							if (currPost < postsOnPage.length-1) {
								$('#'+postsOnPage[currPost]).removeClass('keyselected').children('h4').trigger('mouseleave');
								currPost++;
								if (!p2.utility.isElementVisible('#'+postsOnPage[currPost]))
									$.scrollTo('#'+postsOnPage[currPost], 50);
								$('#'+postsOnPage[currPost]).addClass('keyselected').children('h4').trigger('mouseenter');
							}
							else if (currPost >= postsOnPage.length-1){
								$('#'+postsOnPage[currPost]).removeClass('keyselected').children('h4').trigger('mouseleave');
								$.scrollTo('#'+postsOnPage[0], 50);
								currPost = 0;
								$('#'+postsOnPage[currPost]).addClass('keyselected').children('h4').trigger('mouseenter');
								return;
							}
						}
						else {
							if (currComment < commentsOnPost.length-1) {
								$('#'+commentsOnPost[currComment]).removeClass('keyselected').children('h4').trigger('mouseleave');
								currComment++;
								if (!p2.utility.isElementVisible('#'+commentsOnPost[currComment]))
									$.scrollTo('#'+commentsOnPost[currComment], 50);
								$('#'+commentsOnPost[currComment]).addClass('keyselected').children('h4').trigger('mouseenter');
							}
							else if (currComment == commentsOnPost.length-1){
								$('#'+commentsOnPost[currComment]).removeClass('keyselected').children('h4').trigger('mouseleave');
								currPost++;
								$('#'+postsOnPage[currPost]).addClass('keyselected').children('h4').trigger('mouseenter');
								commentLoop = false;
								return;
							}
						}
						break;
					case 72:
						$("#help").toggle();
						break;
					case 76:
						if (!isUserLoggedIn)
							window.location.href = login_url;
						break;
					// "r" key
					case 82:
						if (!commentLoop) {
							$('#'+postsOnPage[currPost]).removeClass('keyselected').children('h4').trigger('mouseleave');
							$('#'+postsOnPage[currPost]).find('a.comment-reply-link:first').click();
						} else {
							$('#'+commentsOnPost[currComment]).removeClass('keyselected').children('h4').trigger('mouseleave');
							$('#'+commentsOnPost[currComment]).find('a.comment-reply-link').click();
						}
						p2.utility.removeYellow();
						if (e.preventDefault)
							e.preventDefault();
						else
							e.returnValue = false;
						break;
					// "e" key
					case 69:
						if (!commentLoop) {
							$('#'+postsOnPage[currPost]).find('a.edit-post-link:first').click();
						}
						else {
							$('#'+commentsOnPost[currComment]).find('a.comment-edit-link:first').click();
						}
						if (e.preventDefault)
							e.preventDefault();
						else
							e.returnValue = false;
						break;
					// "o" key
					case 79:
						$("#togglecomments").click();
						if (typeof postsOnPage[currPost] != "undefined") {
							if (!p2.utility.isElementVisible('#'+postsOnPage[currPost])) {
								$.scrollTo('#'+postsOnPage[currPost], 150);
							}
						}
						break;
						// "t" key
					case 84:
						p2.utility.jumpToTop();
						break;
					// "esc" key
					case 27:
						if (element.tagName == 'INPUT' || element.tagName == 'TEXTAREA') {
							if ( e.shiftKey && confirm( p2txt.comment_cancel_ays ) ) {
								$('#cancel-comment-reply-link').click();
								$(element).blur();
							}
						}
						else {
							$('#'+commentsOnPost[currComment]).each(function(e) {
								$(this).removeClass('keyselected');
							});

							$('#'+postsOnPage[currPost]).each(function(e) {
								$(this).addClass('keyselected');
							});

							commentLoop = false;
							$('#'+postsOnPage[currPost]).each(function(e) {
								$(this).removeClass('keyselected');
							});
							currPost =- 1;
						}
							$('#help').hide();

						break;
					case 0:
					case 191:
						$("#help").toggle();
						if (e.preventDefault)
							e.preventDefault();
						else
							e.returnValue = false;
						break;
					default:
						break;
				}
			};
		}
	};

	p2.plugins = {
		fluid: function( value ) {

			// Sets the badge for Fluid app enclosed P2. See http://fluidapp.com/
			if (window.fluid) window.fluid.dockBadge = value;
		}
	};

	p2.posts = {
		// ------------------------------------------------
		// Edit post content
		// ------------------------------------------------
		edit: function( postId, element ) {
			function defaultText(input, text) {
				function onFocus() {
					if (this.value == text) {
						this.value = '';
					}
				}
				function onBlur() {
					if (!this.value) {
						this.value = text;
					}
				}
				$( input ).focus( onFocus ).blur( onBlur );
				onBlur.call(input);
			}

			$.getJSON( ajaxUrl, { action: 'get_post', _inline_edit: nonce, post_ID: 'content-' + postId }, function( post ) {
				var jqe = $( element );
				jqe.addClass('inlineediting');
				jqe.find('.tags').css({display: 'none'});
				jqe.find('.postcontent > *').hide();
				if (post.post_type == 'page') {
					$( '#main h2' ).first().hide();
				}

				var postContent = jqe.find('.postcontent');

				var titleDiv = document.createElement('div');

				if (post.post_format == 'standard' || post.post_type == 'page') {
					var titleInput = titleDiv.appendChild(
						document.createElement('input'));
					titleInput.type = 'text';
					titleInput.className = 'title';
					defaultText(titleInput, p2txt.title);
					titleInput.value = post.title;
					postContent.append(titleDiv);
				}

				var cite = '';
				if (post.post_format == 'quote') {
					var tmpDiv = document.createElement('div');
					tmpDiv.innerHTML = post.content;
					var cite = $(tmpDiv).find('cite').remove().html();
					if (tmpDiv.childNodes.length == 1 && tmpDiv.firstChild.nodeType == 1) {
						// This _should_ be the case, else below is
						// to handle an unexpected condition.
						post.content = tmpDiv.firstChild.innerHTML;
					} else {
						post.content = tmpDiv.innerHTML;
					}
				}

				var editor = document.createElement('textarea');
				editor.className = 'posttext';
				editor.value = post.content;
				p2.utility.autoGrowTextarea($(editor));
				jqe.find('.postcontent').append(editor);

				var citationDiv = document.createElement('div');
				if (post.post_format == 'quote') {
					var citationInput = citationDiv.appendChild(
						document.createElement('input'));
					citationInput.type = 'text';
					citationInput.value = cite;
					defaultText(citationInput, p2txt.citation);
					postContent.append(citationDiv);
				}

				var bottomDiv = document.createElement('div');
				bottomDiv.className = 'row2';

				if ( post.post_type != 'page' ) {
					var tagsInput = document.createElement('input');
					tagsInput.name = 'tags';
					tagsInput.className = 'tags';
					tagsInput.type = 'text';
					tagsInput.value = post.tags.join(', ');
					defaultText(tagsInput, p2txt.tagit);
					bottomDiv.appendChild(tagsInput);
				} else {
					var tagsInput = '';
				}

				function tearDownEditor() {
					$(titleDiv).remove();
					$(bottomDiv).remove();
					$(citationDiv).remove();
					$(editor).remove();
					jqe.find('.tags').css({display: ''});
					jqe.find('.postcontent > *').show();
					jqe.removeClass('inlineediting');
					if (post.post_type == 'page') {
						$( '#main h2' ).first().show();
					}
				}
				var spinner = $( '<span class="progress spinner-post-edit"></span>' ).spin( spinSmall );
				var buttonsDiv = document.createElement('div');
				buttonsDiv.className = 'buttons';
				var saveButton = document.createElement('button');
				saveButton.innerHTML = p2txt.save;
				$( saveButton ).click( function() {
					var tags = ! tagsInput || tagsInput.value == p2txt.tagit ? '' : tagsInput.value;
					var args = {
						action:'save_post',
						_inline_edit: nonce,
						post_ID: 'content-' + postId,
						content: editor.value,
						tags: tags
					};

					if (post.post_format == 'standard' || post.post_type == 'page') {
						args.title = titleInput.value == p2txt.title ? '' : titleInput.value;
					} else if (post.post_format == 'quote') {
						args.citation = citationInput.value == p2txt.citation ? '' : citationInput.value;
					}

					$.post(
						ajaxUrl,
						args,
						function(result) {
							// Preserve existing H2 for posts
							jqe.find('.postcontent').html(
								(post.post_format == 'standard') ? jqe.find('h2').first() : ''
							);
							if (post.post_format == 'quote') {
								jqe.find('.postcontent').append('<blockquote>' + result.content + '</blockquote>');
							} else {
								jqe.find('.postcontent').append(result.content);
							}
							if (post.post_type == 'page') {
								$( '#main h2' ).first().html( result.title );
							} else {
								jqe.find('span.tags').html(result.tags);
								if (!isSingle) {
									jqe.find('h2 a').first().html(result.title);
								} else {
									jqe.find('h2').first().html(result.title);
								}
							}
							tearDownEditor();

							$( document ).trigger( 'p2_edit_post_submit', { 'post_id' : postId, 'result' : result } );
						},
						'json');

					$( this ).parent().find( '.progress' ).show();
				});
				var cancelButton = document.createElement('button');
				cancelButton.innerHTML = p2txt.cancel;
				$( cancelButton ).click( tearDownEditor );

				$( buttonsDiv )
					.append( spinner )
					.append( saveButton )
					.append( cancelButton );

				$( bottomDiv )
					.append( buttonsDiv );

				jqe.find('.postcontent').append(bottomDiv);

				// Initialize tags autocomplete
				if ( tagsInput )
					p2.autocomplete.tags( $( tagsInput ) );

				// Trigger event handlers
				editor.focus();
			});
		},

		// ------------------------------------------------
		// Check for new posts and loads them inline
		// ------------------------------------------------
		fetch: function( showNotification ) {
			if (showNotification == null) {
				showNotification = true;
			}
			p2.utility.toggleUpdates('unewposts');
			var queryString = ajaxReadUrl +'&action=get_latest_posts&load_time=' + pageLoadTime + '&frontpage=' + isFirstFrontPage + '&vp=' + postsOnPageQS;
			ajaxCheckPosts = $.getJSON(queryString, function(newPosts){
				if (newPosts != null) {
					pageLoadTime = newPosts.lastposttime;
					if (!isFirstFrontPage || (typeof newPosts.html == "undefined") ) {
						newUnseenUpdates = newUnseenUpdates+newPosts.numberofnewposts;
						message = p2txt.n_new_updates.replace('%d', newUnseenUpdates) + " <a href=\"" + wpUrl +"\">"+p2txt.goto_homepage+"</a>";
						p2.utility.notification(message);
					} else {
						var stickies = $( '#main > ul > li.sticky' ),
							newUpdatesLi = $( '#main > ul > li:first' );

						if ( 0 != stickies.length ) {
							newUpdatesLi = stickies.last();
							newUpdatesLi.after(newPosts.html);
							newUpdatesLi = newUpdatesLi.next();
						} else {
							newUpdatesLi.before(newPosts.html);
							newUpdatesLi = newUpdatesLi.prev();
						}

						newUpdatesLi.hide().slideDown(200, function() {
							$(this).addClass('newupdates');
						});

						var counter = 0;
						$('#posttext_error, #commenttext_error').hide();
						newUpdatesLi.each(function() {
							// Add post to postsOnPageQS  list
							var thisId = $(this).attr('id');
							vpostId = thisId.substring(thisId.indexOf('-')+1);
							postsOnPageQS+= "&vp[]=" + vpostId;
							if (!(thisId in postsOnPage))
								postsOnPage.unshift(thisId);
							// Bind actions to new elements
							p2.utility.bindActions(this, 'post');
							if (p2.utility.isElementVisible(this) && !showNotification) {
								$(this).delay('250').animate({backgroundColor:'transparent'}, 2500, function() {
									$(this).removeClass('newupdates');
									p2.utility.titleCount();
								});
							}
							p2.utility.localizeMicroformatDates(this);
							counter++;
						});
						if (counter >= newPosts.numberofnewposts && showNotification) {
							var updatemsg = p2.utility.isElementVisible('#main > ul >li:first') ? "" :  "<a href=\"#\"  onclick=\"p2.utility.jumpToTop();\" \">"+p2txt.jump_to_top+"</a>" ;
							p2.utility.notification(p2txt.n_new_updates.replace('%d', counter) + " " + updatemsg);
							p2.utility.titleCount();
						}
					}
					$('.newupdates > h4, .newupdates > div').hover( p2.utility.removeYellow, p2.utility.removeYellow );
				}
			});
			//Turn updates back on
			p2.utility.toggleUpdates('unewposts');
		},

		// ------------------------------------------------
		// Submits a new post via ajax
		// ------------------------------------------------
		submit: function( trigger ) {
			var thisForm = $(trigger.target);
			var thisFormElements = $('#posttext, #tags, :input',thisForm).not('input[type="hidden"]');

			var submitProgress = thisForm.find('span.progress');

			var post_subscribe = 'false';
			if ( $( '#post_subscribe' ).attr( 'checked' ) ) // WP3.1 Compat: Best to use .prop()
				post_subscribe = 'post_subscribe';

			var posttext = $.trim($('#posttext').val());

			if ( $( '.no-posts' ) )
				$( '.no-posts' ).hide();

			if ("" == posttext) {
				$("label#posttext_error").text(p2txt.required_field).show().focus();
				return false;
			}

			p2.utility.toggleUpdates('unewposts');
			if (typeof ajaxCheckPosts != "undefined")
				ajaxCheckPosts.abort();
			$("label#posttext_error").hide();

			// WP3.1 Compat: Best to use only .prop()
			if ( 'prop' in thisFormElements )
				thisFormElements.prop( 'disabled', true );
			else
				thisFormElements.attr( 'disabled', 'disabled' );

			thisFormElements.addClass('disabled');

			submitProgress.show();

			// Only continue if we have authorization and a working connection to the server.
			if ( isUserLoggedIn && 'logged_in' != p2.utility.loggedInOut() ) {
				submitProgress.hide();

				// WP3.1 Compat: Best to use only .prop()
				if ( 'prop' in thisFormElements )
					thisFormElements.prop( 'disabled', false );
				else
					thisFormElements.removeAttr( 'disabled' );

				thisFormElements.removeClass( 'disabled' );
				return false;
			}

			var tags = $('#tags').val();
			if (tags == p2txt.tagit)
				tags = '';
			var post_format = $('#post_format').val();
			var post_title = $('#posttitle').val();
			var post_citation = $('#postcitation').val();

			var args = {action: 'new_post', _ajax_post:nonce, posttext: posttext, tags: tags, post_format: post_format, post_title: post_title, post_citation: post_citation, post_subscribe: post_subscribe };
			var errorMessage = '';
			$.ajax({
				type: "POST",
				url: ajaxUrl,
				data: args,
				success: function(result) {
					if ("0" == result)
						errorMessage = p2txt.not_posted_error;

					$('#posttext').val('');
					$('#posttitle').val('');
					$('#postcitation').val('');
					$('#tags').val(p2txt.tagit);
					if(errorMessage != '')
						p2.utility.notification(errorMessage);

					if ( post_subscribe == "post_subscribe" ) {
						// WP3.1 Compat: Best to use only .prop()
						if ( 'prop' in thisFormElements )
							$( '#post_subscribe' ).prop( 'checked', false );
						else
							$( '#post_subscribe' ).removeAttr( 'checked' );
					}

					//if ($.suggest)
					//	$('ul.ac_results').css('display', 'none'); // Hide tag suggestion box if displayed

					if (isFirstFrontPage && result != "0") {
						p2.posts.fetch(false);
					} else if (!isFirstFrontPage && result != "0") {
						p2.utility.notification(p2txt.update_posted);
					}
					$('#posttext').height('auto');
					submitProgress.fadeOut();

					// WP3.1 Compat: Best to use only .prop()
					if ( 'prop' in thisFormElements )
						thisFormElements.prop( 'disabled', false );
					else
						thisFormElements.removeAttr( 'disabled' );

					thisFormElements.removeClass('disabled');

					$( document ).trigger( 'p2_new_post_submit_success', { 'post_id' : result } );
				  },
				error: function(XMLHttpRequest, textStatus, errorThrown) {
					submitProgress.fadeOut();

					// WP3.1 Compat: Best to use only .prop()
					if ( 'prop' in thisFormElements )
						thisFormElements.prop( 'disabled', false );
					else
						thisFormElements.removeAttr( 'disabled' );

					thisFormElements.removeClass('disabled');

					$( document ).trigger( 'p2_new_post_submit_error', { 'request' : XMLHttpRequest, 'status' : textStatus, 'error' : errorThrown } );
				},
				timeout: 60000
			});
			thisFormElements.blur();
			p2.utility.toggleUpdates('unewposts');
		}
	};

	p2.utility = {
		autoGrowTextarea: function( textareas ) {

			// Expand textareas vertically based on their content.
			textareas.on('focus blur keydown paste', function(e) {
				var t = this,
					scrollHeight = $(t).prop('scrollHeight');
				// For pasting, the new scrollHeight of pasted text will not be reflected unless we delay it a bit
				if ( 'paste' === e.type )
					setTimeout( function() { p2.utility.expandTextarea( t, $('#' + $(t).prop('id')).prop('scrollHeight') ); }, 30 );
				else
					p2.utility.expandTextarea( t, scrollHeight );
			});
		},
		bindActions: function( element, type ) {
			$(element).find('a.comment-reply-link').click( function() {
				$('*').removeClass('replying');
				$(this).parents("li").eq(0).addClass('replying');
				$("#respond").show();
				$("#respond").addClass('replying').show();
				$("#comment").focus();
			});

			switch (type) {
				case "comment" :
					var thisCommentEditArea;
					$(element).hover( p2.utility.removeYellow, p2.utility.removeYellow );
					if (inlineEditComments != 0 && isUserLoggedIn) {
						thisCommentEditArea = $(element).find('div.comment-edit').eq(0);
						$(element).find('a.comment-edit-link:first').click( function() {
							thisCommentEditArea.trigger('edit');
							return false;
						});

						thisCommentEditArea.editable(ajaxUrl, {event: 'edit', loadurl: ajaxUrl + '&action=get_comment&_inline_edit=' + nonce,
							id: 'comment_ID', name: 'comment_content', type    : 'autogrow', cssclass: 'textedit', rows: '3',
							indicator : commentEditSpinnerText, loadtext: p2txt.loading, cancel: p2txt.cancel,
							submit  : p2txt.save, tooltip   : '', width: '90%', onblur: 'ignore',
							submitdata: {action:'save_comment',_inline_edit: nonce}});
					}
					$(".single #postlist li > div.postcontent, .single #postlist li > h4, li[id^='prologue'] > div.postcontent, li[id^='comment'] > div.commentcontent, li[id^='prologue'] > h4, li[id^='comment'] > h4").hover(function() {
						$(this).parents("li").eq(0).addClass('selected');
					}, function() {
						$(this).parents("li").eq(0).removeClass('selected');
					});
					break;

				case "post" :
					var thisPostEditArea;
					if (inlineEditPosts != 0 && isUserLoggedIn) {
						thisPostEditArea = $(element).children('div.postcontent').eq(0);
						$( element ).find( 'a.edit-post-link:first' ).click(
							function(e) {
								var postId = this.rel;
								p2.posts.edit(postId, element);
								return false;
							});
					}
					$(".single #postlist li > div.postcontent, .single #postlist li > h4, li[id^='prologue'] > div.postcontent, li[id^='comment'] > div.commentcontent, li[id^='prologue'] > h4, li[id^='comment'] > h4").hover(function() {
						$(this).parents("li").eq(0).addClass('selected');
					}, function() {
						$(this).parents("li").eq(0).removeClass('selected');
					});
					break;
			}
		},
		expandTextarea: function( t, scrollHeight ) {
			if ( scrollHeight > t.clientHeight )
				$(t).css('height', scrollHeight + 'px');
		},
		isElementVisible: function( elem ) {
			elem = $(elem);
			if (!elem.length) {
				return false;
			}
			var docViewTop = $(window).scrollTop();
			var docViewBottom = docViewTop + $(window).height();

			var elemTop = elem.offset().top;
			var elemBottom = elemTop + elem.height();
			var isVisible = ((elemBottom >= docViewTop) && (elemTop <= docViewBottom)  && (elemBottom <= docViewBottom) &&  (elemTop >= docViewTop) );
			return isVisible;
		},
		jumpToTop: function() {
			$.scrollTo('#main', 150);
		},
		localizeMicroformatDates: function( scopeElem ) {
			(scopeElem? $('abbr', scopeElem) : $('abbr')).each(function() {
				var t     = $( this ),
					title = t.attr( 'title' );
					date  = false;

				if ( 'undefined' === typeof title )
					return;

				date = locale.parseISO8601( title );

				if ( date && date > 0 )
					t.html(p2txt.date_time_format.replace('%1$s', locale.date(p2txt.time_format, date)).replace('%2$s', locale.date(p2txt.date_format, date)));
			});
		},
		loggedInOut: function() {

			/**
			 * Check for logged-in status with an synchronous Ajax request.
			 * It's not async because the action *should* not continue if not logged in.
			 * Also used to find out if the current connection is offline,
			 * in that case the response will be an empty string.
			 *
			 * @uses P2Ajax::logged_in_out()
			 * @return boolean for logged-in status, or 'undefined' for no connection
			 */
			var maybeLoggedIn = $.ajax({
				type: 'POST',
				async: false,
				url: ajaxReadUrl +'&action=logged_in_out&_loggedin=' + nonce + "&rand=" + Math.random(),
				timeout: 60000
			}).responseText;

			return maybeLoggedIn;
		},
		notification: function( message, persist ) {

			// Creates a nice notification overlay on the screen.
			// Auto-fades if not marked as persistent.
			$("#notify").stop( true ).prepend( message + '<br />' )
				.fadeIn()
				.animate({ opacity: 0.7 }, 2000, function() {
					if ( undefined == persist && true !== persist ) {
						$( this ).fadeOut( '3000' );
						$( '#notify' ).html( '' );
					}
				}).click(function() {
					$( this ).stop( true ).fadeOut( 'fast' ).html( '' );
				});
		},
		removeYellow: function() {
			$('li.newcomment, tr.newcomment').each(function() {
				if (p2.utility.isElementVisible(this)) {
					$(this).animate({backgroundColor:'transparent'}, {duration: 2500}, function(){
						$(this).removeClass('newcomment');
					});
				}
			});
			if (isFirstFrontPage) {
				$('#main > ul > li.newupdates').each(function() {
					if (p2.utility.isElementVisible(this)) {
						$(this).animate({backgroundColor:'transparent'}, {duration: 2500});
						$(this).removeClass('newupdates');
					}
				});
			}
			p2.utility.titleCount();
		},
		titleCount: function() {
			var n = newUnseenUpdates;
			if ( isFirstFrontPage ) {
				var updates = $( 'li.newupdates' );
				if ( updates )
					n = updates.length;
			}
			if ( n <= 0 ) {
				if (document.title.match(/\([\d+]\)/)) {
					document.title = document.title.replace(/(.*)\([\d]+\)(.*)/, "$1$2");
				}
				p2.plugins.fluid('');
			} else {
				if (document.title.match(/\((\d+)\)/)) {
					document.title = document.title.replace(/\((\d+)\)/ , "(" + n + ")" );
				} else {
					document.title = '(1) ' + document.title;
				}
				p2.plugins.fluid(n);
			}
		},
		toggleUpdates: function( updater ) {
			switch (updater) {
				case "unewposts":
					if (0 == getPostsUpdate) {
						getPostsUpdate = setInterval(p2.posts.fetch, updateRate);
					}
					else {
						clearInterval(getPostsUpdate);
						getPostsUpdate = '0';
					}
					break;

				case "unewcomments":
					if (0 == getCommentsUpdate) {
						getCommentsUpdate = setInterval(p2.comment.fetch, updateRate);
					}
					else {
						clearInterval(getCommentsUpdate);
						getCommentsUpdate = '0';
					}
					break;
			}
		},
		tooltip: function( alink ) {

			// Handles tooltips for the recent-comment widget
			xOffset = 10;
			yOffset = 20;
			alink.hover(function(e){
				this.t = this.title;
				this.title = "";
				$("body").append("<div id='tooltip'></div>");
				$("#tooltip")
					.text( this.t )
					.css("top",(e.pageY - yOffset) + "px")
					.css("left",(e.pageX + xOffset) + "px")
					.fadeIn("fast");
				},
			function(){
				this.title = this.t;
				$("#tooltip").remove();
				});
			alink.mousemove(function(e){
				$("#tooltip")
					.css("top",(e.pageY - yOffset) + "px")
					.css("left",(e.pageX + xOffset) + "px");
			});
		}
	};

	// Activate inline editing plugin
	if ((inlineEditPosts || inlineEditComments ) && isUserLoggedIn) {
		$.editable.addInputType('autogrow', {
			element : function(settings, original) {
				var textarea = $('<textarea class="expand" />');
				if (settings.rows) {
					textarea.attr('rows', settings.rows);
				} else {
					textarea.attr('rows', 4);
				}
				if (settings.cols) {
					textarea.attr('cols', settings.cols);
				} else {
					textarea.attr('cols', 45);
				}
				textarea.width('95%');
				$(this).append(textarea);
				return(textarea);
			},
			plugin : function(settings, original) {
				window.p2.utility.autoGrowTextarea($('textarea', this));
			}
		});
	}

	// Kickstart everything
	$( document ).ready( function() {
		p2.initialize();
	});

	// Overrides global media function
	send_to_editor = function( media ) {
		if ( $( 'textarea#posttext' ).length ) {
			$( 'textarea#posttext' ).val( $( 'textarea#posttext' ).val() + media );
			tb_remove();
		}
	};
})( jQuery );
