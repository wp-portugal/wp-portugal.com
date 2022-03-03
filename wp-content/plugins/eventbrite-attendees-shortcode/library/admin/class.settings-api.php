<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Extendd Plugin Settings API wrapper class
 *
 * @original author:	Tareq Hasan <tareq@weDevs.com>
 */
if ( !class_exists( 'Eventbrite_Attendees_Shortcode_Settings_API' ) ):
    class Eventbrite_Attendees_Shortcode_Settings_API {
		
	/**
	 * Version
	 */
	var $api_version = '1.0.1';

    /**
     * settings sections array
     *
     * @var array
     */
    private $settings_sections = array();
	
	/**
     * Settings sections array
     *
     * @var array
     */
    private $settings_sidebars = array();

    /**
     * Settings fields array
     *
     * @var array
     */
    private $settings_fields = array();
	
	/**
	 * The Plugin prefix
	 * 
	 * @var string
	 */
	private $prefix;
	
	/**
	 * The Plugin domain
	 * 
	 * @var string
	 */
	private $domain;
	
	/**
	 * The Parent Plugin version
	 * 
	 * @var string
	 */
	private $version;

    /**
     * Singleton instance
     *
     * @var object
     */
    private static $_instance;
	
	/**
	 * Fire
	 *
	 */
    public function __construct() {
        add_action( 'admin_enqueue_scripts',	array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'init',						array( $this, 'late_init' ), 89 );
    }
	
	/**
	 * Fire any actions needed a little late
	 *
	 * @return void
	 */
	function late_init() {
		if ( function_exists( 'EXTENDD_settings_init' ) ) {
			$extendd_settings_api = EXTENDD_settings_init();
			add_action( $this->prefix . '_settings_sidebars',	array( $extendd_settings_api, 'extendd_plugins_sidebar' ), 11 );
		} else {
			add_action( $this->prefix . '_settings_sidebars',	array( $this, 'extendd_plugins_sidebar' ), 11 );
		}
	}
	
	/**
	 * Set parent prefix
	 * 
	 * @param string $prefix
	 * @return void
	 */
	public function set_prefix( $prefix ) {
		$this->prefix = $prefix;
	}
	
	/**
	 * Set parent domain
	 * 
	 * @param string $domain
	 * @return void
	 */
	public function set_domain( $domain ) {
		$this->domain = $domain;
	}
	
	/**
	 * Set parent version
	 * 
	 * @param string $version
	 * @return void
	 */
	public function set_version( $version ) {
		$this->version = $version;
	}

    /**
     * Enqueue scripts and styles
     */
    function admin_enqueue_scripts( $hook ) {
		if ( 'settings_page_' . $this->domain !== $hook )
			return;
				
		/* Admin */
		wp_enqueue_style( $this->domain, plugins_url( 'library/css/admin.css', EVENTBRITE_ATTENDEES_FILE ), false, $this->version, 'screen' );
		
		/* Genericons */
		wp_enqueue_style( 'genericons', plugins_url( 'library/css/genericons.css', EVENTBRITE_ATTENDEES_FILE ), false, '3.0.3', 'screen' );
    }

    /**
     * Set settings sections
     *
     * @param array   $sections setting sections array
     */
    function set_sections( $sections ) {
		$sections = apply_filters( $this->prefix . '_add_settings_sections', $sections );				
        $this->settings_sections = $sections;

        return $this;
    }

    /**
     * Add a single section
     *
     * @param array   $section
     */
    function add_section( $section ) {
        $this->settings_sections[] = $section;

        return $this;
    }

    /**
     * Set settings fields
     *
     * @param array   $fields settings fields array
     */
    function set_fields( $fields ) {
		$fields = apply_filters( $this->prefix . '_add_settings_fields', $fields );
        $this->settings_fields = $fields;

        return $this;
    }

    function add_field( $section, $field ) {
        $defaults = array(
            'name'	=> '',
            'label' => '',
            'desc'	=> '',
            'type'	=> 'text'
        );

        $args = wp_parse_args( $field, $defaults );
        $this->settings_fields[$section][] = $args;

        return $this;
    }

    /**
     * Add a single section
     *
     * @param array   $section
     */
    function add_sidebar( $sidebar = array() ) {
		$sidebar = apply_filters( $this->prefix . '_add_settings_sidebar', $sidebar );
		if ( !empty( $sidebar ) ) {
        	$this->settings_sidebars[] = $sidebar;
		}
    }

    /**
     * Initialize and registers the settings sections and fileds to WordPress
     *
     * Usually this should be called at `admin_init` hook.
     *
     * This function gets the initiated settings sections and fields. Then
     * registers them to WordPress and ready for use.
     */
    function admin_init() {
        //register settings sections
        foreach ( $this->settings_sections as $section ) {
            if ( false == get_option( $section['id'] ) ) {
                add_option( $section['id'] );
            }

            add_settings_section( $section['id'], $section['title'], '__return_false', $section['id'] );
        }

        //register settings fields
        foreach ( $this->settings_fields as $section => $field ) {
            foreach ( $field as $option ) {

                $type = isset( $option['type'] ) ? $option['type'] : 'text';

                $args = array(
                    'id'				=> $option['name'],
                    'desc' 				=> isset( $option['desc'] ) ? $option['desc'] : '',
                    'name' 				=> $option['label'],
                    'section' 			=> $section,
                    'size' 				=> isset( $option['size'] ) ? $option['size'] : null,
                    'options' 			=> isset( $option['options'] ) ? $option['options'] : '',
                    'std' 				=> isset( $option['default'] ) ? $option['default'] : '',
                    'sanitize_callback' => isset( $option['sanitize_callback'] ) ? $option['sanitize_callback'] : '',
                );
				$args = wp_parse_args( $args, $option );
                add_settings_field( $section . '[' . $option['name'] . ']', $option['label'], array( $this, 'callback_' . $type ), $section, $section, $args );
            }
        }

        // creates our settings in the options table
        foreach ( $this->settings_sections as $section ) {
            register_setting( $section['id'], $section['id'], array( $this, 'sanitize_options' ) );
        }
    }

    /**
     * Displays a text field for a settings field
     *
     * @param array   $args settings field args
     */
    function callback_text( $args ) {

        $value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
        $size  = isset( $args['size'] ) && !is_null( $args['size'] ) ? $args['size'] : 'regular';

        $html  = sprintf( '<input type="text" class="%1$s-text" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s"/>', $size, $args['section'], $args['id'], $value );		
        $html .= sprintf( '<span class="description"> %s</span>', $args['desc'] );

        echo $html;
    }

    /**
     * Displays a checkbox for a settings field
     *
     * @param array   $args settings field args
     */
    function callback_checkbox( $args ) {

        $value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );

        $html  = '<div class="checkbox-wrap">';
        $html .= sprintf( '<input type="hidden" name="%1$s[%2$s]" value="off" />', $args['section'], $args['id'] );
        $html .= sprintf( '<input type="checkbox" class="checkbox" id="%1$s[%2$s]" name="%1$s[%2$s]" value="on"%4$s />', $args['section'], $args['id'], $value, checked( $value, 'on', false ) );
        $html .= sprintf( '<label for="%1$s[%2$s]"></label>', $args['section'], $args['id'] );
        $html .= '</div>';
        $html .= sprintf( '<span class="description"> %s</label>', $args['desc'] );

        echo $html;
    }

    /**
     * Displays a multicheckbox a settings field
     *
     * @param array   $args settings field args
     */
    function callback_multicheck( $args ) {

        $value = $this->get_option( $args['id'], $args['section'], $args['std'] );

        $html  = '<div class="checkbox-wrap">';
        $html .= '<ul>';
        foreach ( $args['options'] as $key => $label ) {
            $checked = isset( $value[$key] ) ? $value[$key] : '0';
        	$html .= '<li>';
            $html .= sprintf( '<input type="checkbox" class="checkbox" id="%1$s[%2$s][%3$s]" name="%1$s[%2$s][%3$s]" value="%3$s"%4$s />', $args['section'], $args['id'], $key, checked( $checked, $key, false ) );
            $html .= sprintf( '<label for="%1$s[%2$s][%4$s]" title="%3$s"> %3$s</label>', $args['section'], $args['id'], $label, $key );
        	$html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
        $html .= sprintf( '<span class="description"> %s</label>', $args['desc'] );

        echo $html;
    }

    /**
     * Displays a multicheckbox a settings field
     *
     * @param array   $args settings field args
     */
    function callback_radio( $args ) {

        $value = $this->get_option( $args['id'], $args['section'], $args['std'] );

        $html  = '<div class="radio-wrap">';
        $html .= '<ul>';
        foreach ( $args['options'] as $key => $label ) {
        	$html .= '<li>';
            $html .= sprintf( '<input type="radio" class="radio" id="%1$s[%2$s][%3$s]" name="%1$s[%2$s]" value="%3$s"%4$s />', $args['section'], $args['id'], $key, checked( $value, $key, false ) );
            $html .= sprintf( '<label for="%1$s[%2$s][%4$s]" title="%3$s"> %3$s</label><br>', $args['section'], $args['id'], $label, $key );
        	$html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';


        $html .= sprintf( '<span class="description"> %s</label>', $args['desc'] );

        echo $html;
    }

    /**
     * Displays a selectbox for a settings field
     *
     * @param array   $args settings field args
     */
    function callback_select( $args ) {

        $value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
        $size = isset( $args['size'] ) && !is_null( $args['size'] ) ? $args['size'] : 'regular';

        $html = sprintf( '<select class="%1$s" name="%2$s[%3$s]" id="%2$s[%3$s]">', $size, $args['section'], $args['id'] );
        foreach ( $args['options'] as $key => $label ) {
            $html .= sprintf( '<option value="%s"%s>%s</option>', $key, selected( $value, $key, false ), $label );
        }
        $html .= sprintf( '</select>' );
		
		ob_start(); ?>
        <script>
		jQuery(document).ready(function($) {
		    $('select[name="<?php echo $args['section'] . '[' . $args['id'] . ']'; ?>"]').chosen();
		});
		</script><?php
		
		$html .= ob_get_clean();
        $html .= sprintf( '<span class="description"> %s</span>', $args['desc'] );

        echo $html;
    }

    /**
     * Displays a textarea for a settings field
     *
     * @param array   $args settings field args
     */
    function callback_textarea( $args ) {

        $value = esc_textarea( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		//$value = wp_specialchars_decode( stripslashes( $this->get_option( $args['id'], $args['section'], $args['std'] ) ), 1, 0, 1 );
        $size = isset( $args['size'] ) && !is_null( $args['size'] ) ? $args['size'] : 'regular';

        $html = sprintf( '<textarea rows="5" cols="55" class="%1$s-text" id="%2$s[%3$s]" name="%2$s[%3$s]">%4$s</textarea>', $size, $args['section'], $args['id'], stripslashes( $value ) );
        $html .= sprintf( '<br><span class="description"> %s</span>', $args['desc'] );

        echo $html;
    }

    /**
     * Displays a textarea for a settings field
     *
     * @param array   $args settings field args
     */
    function callback_html( $args ) {
		static $counter = 0;
		
		$html  = '<div class="html-wrap">';
        $html .= $args['desc'];
		
		$counter++;		
		if ( 1 === $counter ) {
			ob_start(); ?>
<script>
jQuery(document).ready(function($) {
	setTimeout(function() {
		$('.html-wrap').each(function(index, element) {
			$(this).parent().parent().children('th').hide();
			$(this).parent().attr('colspan','2');
		});
	}, 200 );
});
</script><?php
			
			$html .= ob_get_clean();
		}
		$html .= '</div>';
		
        echo $html;
    }

    /**
     * Displays a rich text textarea for a settings field
     *
     * @param array   $args settings field args
     */
    function callback_wysiwyg( $args ) {

        $value	= wpautop( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
        $size	= isset( $args['size'] ) && !is_null( $args['size'] ) ? $args['size'] : '500px';

        echo '<div style="width: ' . $size . ';">';

        wp_editor( $value, $args['section'] . '[' . $args['id'] . ']', array( 'teeny' => true, 'textarea_rows' => 10 ) );

        echo '</div>';

        echo sprintf( '<br><span class="description"> %s</span>', $args['desc'] );
    }

    /**
     * Sanitize callback for Settings API
     */ 
    function sanitize_options( $options ) {
		
        foreach( $options as $option_slug => $option_value ) {
            $sanitize_callback = $this->get_sanitize_callback( $option_slug );

            // If callback is set, call it
            if ( $sanitize_callback ) {
                $options[ $option_slug ] = call_user_func( $sanitize_callback, $option_value );
                continue;
            }

            // Treat everything that's not an array as a string
            if ( !is_array( $option_value ) ) {
                $options[ $option_slug ] = sanitize_text_field( $option_value );
                continue;
            }
        }
        return $options;
    }
        
    /**
     * Get sanitization callback for given option slug
     * 
     * @param string $slug option slug
     * 
     * @return mixed string or bool false
     */ 
    function get_sanitize_callback( $slug = '' ) {
        if ( empty( $slug ) )
            return false;
        // Iterate over registered fields and see if we can find proper callback
        foreach( $this->settings_fields as $section => $options ) {
            foreach ( $options as $option ) {
                if ( $option['name'] != $slug )
                    continue;
                // Return the callback name 
                return isset( $option['sanitize_callback'] ) && is_callable( $option['sanitize_callback'] ) ? $option['sanitize_callback'] : false;
            }
        }
        return false; 
    }

    /**
     * Get the value of a settings field
     *
     * @param string  $option  settings field name
     * @param string  $section the section name this field belongs to
     * @param string  $default default text if it's not found
     * @return string
     */
    function get_option( $option, $section, $default = '' ) {

        $options = get_option( $section );

        if ( isset( $options[$option] ) ) {
            return $options[$option];
        }

        return $default;
    }

    /**
     * Show navigations as tab
     *
     * Shows all the settings section labels as tab
     */
    function show_navigation() {
        $html = '<h2 class="nav-tab-wrapper">';

        foreach ( $this->settings_sections as $tab ) {
            $html .= sprintf( '<a href="#%1$s" class="nav-tab" id="%1$s-tab">%2$s</a>', $tab['id'], $tab['title'] );
        }

        $html .= '</h2>';

        echo $html;
    }

    /**
     * Show the section settings forms
     *
     * This function displays every sections in a different form
     */
    function show_forms() { ?>
        <div class="section col-group">
            <div class="postbox col span_2_of_3">
                <?php foreach ( $this->settings_sections as $form ) { ?>
                    <div id="<?php echo $form['id']; ?>" class="group">
                        <form method="post" action="options.php">

                            <?php do_action( $this->prefix . '_form_top_' . $form['id'], $form ); ?>
                            <?php settings_fields( $form['id'] ); ?>
                            <div class="inside"><?php do_settings_sections( $form['id'] ); ?></div>
                            <?php do_action( $this->prefix . '_form_bottom_' . $form['id'], $form ); ?>

                            <div style="padding-left: 10px">
                                <?php submit_button(); ?>
                            </div>
                        </form>
                    </div>
                <?php } ?>
            </div>
        <div class="col span_1_of_3" style="margin-top:0">
        	<?php do_action( $this->prefix . '_settings_sidebars', $this->settings_sidebars ); ?>
        </div>
        
        </div>
        <br class="clear">
        <?php
    }

    /**
     * Tabbable JavaScript codes
     *
     * This code uses localstorage for displaying active tabs
     */
    function inline_jquery() { ?>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Switches option sections
		$('.group').hide();
		var activetab = '';
		if (typeof(localStorage) != 'undefined' ) {
			activetab = localStorage.getItem("activetab");
		}
		if (activetab != '' && $(activetab).length ) {
			$(activetab).fadeIn();
			$(activetab + '_sidebar').fadeIn();
		} else {
			$('.group:first').fadeIn();
			$('.metabox-holder.group:first').fadeIn();
		}
		$('.group .collapsed').each(function(){
			$(this).find('input:checked').parent().parent().parent().nextAll().each(
			function(){
				if ($(this).hasClass('last')) {
					$(this).removeClass('hidden');
					return false;
				}
				$(this).filter('.hidden').removeClass('hidden');
			});
		});

		if (activetab != '' && $(activetab + '-tab').length ) {
			$(activetab + '-tab').addClass('nav-tab-active');
		}
		else {
			$('.nav-tab-wrapper a:first').addClass('nav-tab-active');
		}
		$('.nav-tab-wrapper a').on('click',function(e) {
			$('.nav-tab-wrapper a').removeClass('nav-tab-active');
			$(this).addClass('nav-tab-active').blur();
			var clicked_group = $(this).attr('href');
			if (typeof(localStorage) != 'undefined' ) {
				localStorage.setItem("activetab", $(this).attr('href'));
			}
			$('.group').hide();
			$(clicked_group).fadeIn();
			$(clicked_group + '_sidebar').fadeIn();
			e.preventDefault();
		});
	<?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) { ?>
		
		setTimeout( function() {
			$('#setting-error-settings_updated, #setting-error-transitent_deleted').fadeOut('slow');
		}, 4000 );
	<?php } ?>
	});
</script><?php
    }

	/**
	 * Create a potbox widget.
	 *
	 * @param 	string $id      ID of the postbox.
	 * @param 	string $title   Title of the postbox.
	 * @param 	string $content Content of the postbox.
	 */
	public function postbox( $id, $title, $content, $group = false ) {
		?>
        <div class="metabox-holder<?php if ( $group ) echo ' group'; ?>" id="<?php echo $id; ?>">
            <div class="postbox">
            <h3><?php echo $title; ?></h3>
            <div class="inside"><?php echo $content; ?></div>
            </div>
        </div>
        <?php
	}
	
	/**
	 * Fetch RSS items from the feed.
	 *
	 * @param 	int    $num  Number of items to fetch.
	 * @param 	string $feed The feed to fetch.
	 * @return 	array|bool False on error, array of RSS items on success.
	 */
	public function fetch_rss_items( $num, $feed ) {
		if ( !function_exists( 'fetch_feed' ) )
			include_once( ABSPATH . WPINC . '/feed.php' );
			
		add_filter( 'wp_feed_cache_transient_lifetime', function() {
			return WEEK_IN_SECONDS;
		});
		
		$rss = fetch_feed( $feed );
		
		remove_all_filters( 'wp_feed_cache_transient_lifetime' );

		// Bail if feed doesn't work
		if ( !$rss || is_wp_error( $rss ) )
			return false;

		$rss_items = $rss->get_items( 0, $rss->get_item_quantity( $num ) );

		// If the feed was erroneous 
		if ( !$rss_items ) {
			$md5 = md5( $feed );
			delete_transient( 'feed_' . $md5 );
			delete_transient( 'feed_mod_' . $md5 );
			$rss       = fetch_feed( $feed );
			$rss_items = $rss->get_items( 0, $rss->get_item_quantity( $num ) );
		}

		return $rss_items;
	}

	/**
	 * Box with latest plugins from Extendd.com for sidebar
	 */
	function extendd_plugins_sidebar( $args ) {
		
		$defaults = array(
			'items' => 6,
			'feed' 	=> 'http://extendd.com/feed/?post_type=download',
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$rss_items = $this->fetch_rss_items( $args['items'], $args['feed'] );
		
		$content = '<ul>';
		if ( !$rss_items ) {
			$content .= '<li>' . __( 'Error fetching feed', $this->domain ) . '</li>';
		} else {
			foreach ( $rss_items as $item ) {
				$url = preg_replace( '/#.*/', '', esc_url( $item->get_permalink(), null, 'display' ) );
				$content .= '<li>';
				$content .= '<a class="rsswidget" href="' . $url . 'utm_medium=sidebarwidget&utm_term=newsitem&utm_campaign=' . $this->prefix . 'settingsapi">' . esc_html( $item->get_title() ) . '</a> ';
				$content .= '</li>';
			}
		}
		$content .= '</ul>';
		$content .= '<ul class="social">';
		$content .= '<li class="facebook"><span class="genericon genericon-facebook"></span><a href="https://www.facebook.com/WPExtendd">' . __( 'Like Extendd on Facebook', $this->domain ) . '</a></li>';
		$content .= '<li class="twitter"><span class="genericon genericon-twitter"></span><a href="https://twitter.com/WPExtendd">' . __( 'Follow Extendd on Twitter', $this->domain ) . '</a></li>';
		$content .= '<li class="twitter"><span class="genericon genericon-twitter"></span><a href="https://twitter.com/TheFrosty">' . __( 'Follow Austin on Twitter', $this->domain ) . '</a></li>';
		$content .= '<li class="googleplus"><span class="genericon genericon-googleplus"></span><a href="https://plus.google.com/113609352601311785002/">' . __( 'Circle Extendd on Google+', $this->domain ) . '</a></li>';
		$content .= '<li class="email"><span class="genericons genericons-mail"></span><a href="http://eepurl.com/vi0bz">' . __( 'Subscribe via email', $this->domain ) . '</a></li>';

		$content .= '</ul>';
		$this->postbox( 'extenddlatest', __( 'Latest plugins from Extendd.com', $this->domain ), $content );
	}

}
endif;