<?php

/**
 * Extendd_Dashboard_Widget
 *
 * @ver	 1.2
 */
if ( !class_exists( 'Extendd_Dashboard_Widget' ) ) :
	class Extendd_Dashboard_Widget {
		
		/* Domain */
		const domain = 'extendd';
		
		/**
		 * The Plugin.
		 * 
		 * @var string
		 */
		private $plugin;
		
		/**
		 * Additional args.
		 * 
		 * @var string
		 */
		private $args;
		
		/**
		 * Kick it
		 */
		public function __construct() {
			add_action( 'admin_init',				array( $this, 'maybe_enqueue' ) );
			add_action( 'wp_dashboard_setup',	array( $this, 'add_dashboard_widget' ) );
		}
	
		/**
		 * Set plugin.
		 * 
		 * @param string $plugin
		 * @return void
		 */
		public function set_plugin( $plugin ) {
			$this->plugin = $plugin;
		}
		
		/**
		 * Set args.
		 * 
		 * @param string $args
		 * @return void
		 */
		public function set_args( $args ) {
			$this->args = $args;
		}
		
		/**
		 * Enqueue the scripts
		 */
		function maybe_enqueue() {
			if ( isset( $this->args['enqueue'] ) ) {
				/* Scripts & Styles */
				add_action( 'admin_enqueue_scripts',		array( $this, 'enqueue_scripts' ) );
				add_action( 'admin_enqueue_scripts',		array( $this, 'inline_scripts' ) );
			}
		}
		
		/**
		 * Scripts & Styles
		 */
		function enqueue_scripts() {
			wp_enqueue_style( 'extendd-dashboard', $this->add_query_arg( 'css' ), null, null, 'screen' );
			wp_enqueue_script( 'extendd-dashboard', $this->add_query_arg( 'js' ), array( 'jquery' ), null, true );
		}
		
		/**
		 * Add Dashboard widget
		 */
		function add_dashboard_widget() {
			wp_add_dashboard_widget( 'extendd-dashboard', __( 'Extendd.com <em>A WordPress plugin marketplace</em>', self::domain ), array( $this, 'widget' ) );
		}
		
		/**
		 * Dashboard widget
		 */
		function widget() {
			$defaults = array(
				'items' => 4,
				'feed' 	=> 'http://extendd.com/feed/?post_type=download',
			);
			
			$args = wp_parse_args( $this->args, $defaults );
						
			$rss_items = $this->fetch_rss_items( $args['items'], $args['feed'] );
			
			$content = '<ul>';
			
			if ( !$rss_items ) {
				$content .= '<li>' . __( 'Error fetching feed', self::domain ) . '</li>';
			} else {
				foreach ( $rss_items as $item ) {
					$url = preg_replace( '/#.*/', '', esc_url( $item->get_permalink(), null, 'display' ) );
					$content .= '<li>';
					$content .= '<a class="rsswidget" href="' . $url . 'utm_medium=wpadmin_dashboard&utm_term=newsitem&utm_campaign=' . $this->plugin . '">' .
						esc_html( $item->get_title() ) . '</a> ';
					$content .= '</li>';
				}
			}
			
			$content .= '</ul>';
			$content .= '<ul class="social">';
				$content .= sprintf( 
					'<li>%s <span class="genericon genericon-facebook"></span><a href="https://www.facebook.com/WPExtendd">%s</a> | ' .
					'%s <span class="genericon genericon-twitter"></span><a href="https://twitter.com/WPExtendd">@WPExtendd</a></li>',
						__( 'Like Extendd on', self::domain ),
						__( 'Facebook', self::domain ),
						__( 'Follow', self::domain )
				);
				
				$content .= sprintf(
					'<li><span class="genericon genericon-twitter"></span> %s <a href="https://twitter.com/TheFrosty">@TheFrosty</a></li>',
					__( 'Follow', self::domain )
				);	
			$content .= '</ul>';
			
			$this->postbox( 'extenddlatest', __( 'Latest plugins from Extendd.com', self::domain ), $content );
		}
		
		/**
		 * Create a potbox widget.
		 *
		 * @param 	string $id      ID of the postbox.
		 * @param 	string $title   Title of the postbox.
		 * @param 	string $content Content of the postbox.
		 */
		private function postbox( $id, $title, $content, $group = false ) {
			echo $content;
		}
		
		/**
		 * Generate the custom CSS/JS.
		 *
		 */
		public function inline_scripts() {
			
			if ( isset( $_GET['extendd-dashboard'] ) && intval( $_GET['extendd-dashboard'] ) === 1 ) {
				
				if ( isset( $_GET['type'] ) && $_GET['type'] === 'css' ) {
				
					header("content-type:text/css");
					ob_start();
					str_replace( ob_end_clean(), '', ob_end_clean() );
					$this->CSS();
					echo ob_get_clean();
					die;
				}
				elseif ( isset( $_GET['type'] ) && $_GET['type'] === 'js' ) {
				
					header("content-type:application/x-javascript");
					ob_start();
					str_replace( ob_end_clean(), '', ob_end_clean() );
					$this->jQuery();
					echo ob_get_clean();
					die;
				}
			}
		}
		
		/**
		 * Helper function to return the proper query arg.
		 */
		private function add_query_arg( $type = 'js' ) {
			$url = add_query_arg(
				array(
					'extendd-dashboard' => '1',
					'type' 				=> $type
				),
				trailingslashit( admin_url() )
			);
			return esc_url( $url );
		}
		
		/**
		 * Create the CSS.
		 *
		 * @param 	bool $remove_wrapper
		 */
		private function CSS( $remove_wrapper = true ) {
if ( !$remove_wrapper ) { ?>
<style>
<?php } ?>

<?php if ( !$remove_wrapper ) { ?>
</style>
<?php }
		}		
		
		/**
		 * Create the jQuery.
		 *
		 * @param 	bool $remove_wrapper
		 */
		private function jQuery( $remove_wrapper = true ) {
if ( !$remove_wrapper ) { ?>
<script>
<?php } ?>

<?php if ( !$remove_wrapper ) { ?>
</script>
<?php }
		}
		
		/**
		 * Fetch RSS items from the feed.
		 *
		 * @param 	int    $num  Number of items to fetch.
		 * @param 	string $feed The feed to fetch.
		 * @return 	array|bool False on error, array of RSS items on success.
		 */
		private function fetch_rss_items( $num, $feed ) {
			if ( !function_exists( 'fetch_feed' ) )
				include_once( ABSPATH . WPINC . '/feed.php' );
			
			add_filter( 'wp_feed_cache_transient_lifetime', create_function( '', 'return WEEK_IN_SECONDS;' ) );
			
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
		
	}
endif;