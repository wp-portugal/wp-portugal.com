<?php
class LoginWithAjaxWidget extends WP_Widget {
    public $defaults;
    
    /** constructor */
    function __construct() {
    	$this->defaults = array(
    		'title' => __('Log In','login-with-ajax'),
    		'title_loggedin' => __( 'Hi', 'login-with-ajax' ).' %username%',
    		'template' => 'default',
    		'profile_link' => 1,
    		'registration' => 1,
    		'remember' => 1
    	);
    	$widget_ops = array('description' => __( "Login widget with AJAX capabilities.", 'login-with-ajax') );
        parent::__construct(false, $name = 'Login With Ajax', $widget_ops);	
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {
    	$instance = array_merge($this->defaults, $instance);
        echo $args['before_widget'];
    	if( !is_user_logged_in() && !empty($instance['title']) ){
		    echo $args['before_title'];
		    echo '<span class="lwa-title">';
		    echo apply_filters('widget_title',$instance['title'], $instance, $this->id_base);
		    echo '</span>';
		    echo $args['after_title'];
    	}elseif( is_user_logged_in() && !empty($instance['title_loggedin']) ) {
		    echo $args['before_title'];
		    echo '<span class="lwa-title">';
		    echo str_replace('%username%', LoginWithAjax::$current_user->display_name, $instance['title_loggedin']);
		    echo '</span>';
		    echo $args['after_title'];
    	}
    	LoginWithAjax::widget($instance);
	    echo $args['after_widget'];
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {
    	foreach($this->defaults as $key => $value){
    		if( !isset($new_instance[$key]) ){
    			$new_instance[$key] = false;
    		}
    	}
    	return $new_instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {
    	$instance = array_merge($this->defaults, $instance);
        ?>
			<?php if( count(LoginWithAjax::$templates) > 1 ): ?>
			<p>
            	<label for="<?php echo $this->get_field_id('template'); ?>"><?php esc_html_e('Template', 'login-with-ajax'); ?>:</label>
            	<select class="widefat" id="<?php echo $this->get_field_id('template'); ?>" name="<?php echo $this->get_field_name('template'); ?>" >
            		<?php foreach( array_keys(LoginWithAjax::$templates) as $template ): ?>
            		<option <?php echo ($instance['template'] == $template) ? 'selected="selected"':""; ?>><?php echo esc_html($template); ?></option>
            		<?php endforeach; ?>
            	</select>
			</p>
			<?php endif; ?>
		    <p><strong><?php esc_html_e('Logged Out','login-with-ajax'); ?></strong></p>
		    <p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php echo sprintf(esc_html__('Title (%s)', 'login-with-ajax'),esc_html__('Logged In','login-with-ajax')); ?>: </label>
				<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo esc_attr($instance['title']); ?>" />
			</p>
            <p>
                <label for="<?php echo $this->get_field_id('remember'); ?>"><?php esc_html_e('Show Recover Password?', 'login-with-ajax'); ?> </label>
            	<select class="widefat" id="<?php echo $this->get_field_id('remember'); ?>" name="<?php echo $this->get_field_name('remember'); ?>" >
            		<option value="0" <?php echo ($instance['remember'] == 0) ? 'selected="selected"':""; ?>><?php esc_html_e('No Link','login-with-ajax'); ?></option>
            		<option value="1" <?php echo ($instance['remember'] == 1) ? 'selected="selected"':""; ?>><?php esc_html_e('Show link with AJAX form','login-with-ajax'); ?></option>
            		<option value="2" <?php echo ($instance['remember'] == 2) ? 'selected="selected"':""; ?>><?php esc_html_e('Show direct link','login-with-ajax'); ?></option>
            	</select>
    		</p>
    		<p>
                <label for="<?php echo $this->get_field_id('registration'); ?>"><?php esc_html_e('AJAX Registration?', 'login-with-ajax'); ?> </label>
            	<select class="widefat" id="<?php echo $this->get_field_id('registration'); ?>" name="<?php echo $this->get_field_name('registration'); ?>" >
            		<option value="0" <?php echo ($instance['registration'] == 0) ? 'selected="selected"':""; ?>><?php esc_html_e('No Link','login-with-ajax'); ?></option>
            		<option value="1" <?php echo ($instance['registration'] == 1) ? 'selected="selected"':""; ?>><?php esc_html_e('Show link with AJAX form','login-with-ajax'); ?></option>
            		<option value="2" <?php echo ($instance['registration'] == 2) ? 'selected="selected"':""; ?>><?php esc_html_e('Show direct link','login-with-ajax'); ?></option>
            	</select>
			</p>
	        <p><strong><?php esc_html_e('Logged In','login-with-ajax'); ?></strong></p>
			<p>
				<label for="<?php echo $this->get_field_id('title_loggedin'); ?>"><?php echo sprintf(esc_html__('Title (%s)', 'login-with-ajax'),esc_html__('Logged Out','login-with-ajax')); ?>: </label>
				<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title_loggedin'); ?>" value="<?php echo esc_attr($instance['title_loggedin']); ?>" />
			</p>
            <p>
                <input id="<?php echo $this->get_field_id('profile_link'); ?>" name="<?php echo $this->get_field_name('profile_link'); ?>" type="checkbox" value="1" <?php echo !empty($instance['profile_link']) ? 'checked="checked"':""; ?> />
                <label for="<?php echo $this->get_field_id('profile_link'); ?>"><?php esc_html_e('Show profile link?', 'login-with-ajax'); ?> </label>
			</p>
        <?php
    }

}
?>