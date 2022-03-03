<?php
/*  Copyright 2010 Daniele Futtorovic (cosifantutti [at] laposte [dot] net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
Plugin Name: Asynchronous Widgets
Description: Asynchronous Widgets allows you to have any registered widget be loaded asynchronously via an AJAX call.
Author: Daniele Futtorovic
Version: 1.1.1
License: GPLv2
*/

/******************************************
 *
 * REGISTRATION AND DE-REGISTRATION
 *
 *****************************************/

register_activation_hook(__FILE__, "async_widgets_activate");
register_deactivation_hook(__FILE__, "async_widgets_deactivate");

function async_widgets_activate(){
    $aw = new AsyncWidget();
    $aw->restore_all();
}

function async_widgets_deactivate(){
    $aw = new AsyncWidget();
    $aw->deactivate_all();
}

/*****************************************
 *
 * SESSION INIT
 *
 ****************************************/

/*
 * We store the widget parameters in $_SESSION,
 * rather than in the database, since the
 * parameter may be different from user to
 * user (or may they not?).
 */

add_action('template_redirect', 'aw_setup_session');

function aw_setup_session(){
    if( ! session_id() ){
        $succ = session_start();
    }
}

/*********************************************
 *
 * MAIN HOOK
 *
 ********************************************/

/*
 * Priority probably needs to be as high as possible -- but I'm not sure.
 * For now prio set to be higher than at least prio used by WP Widget Cache plugin.
 */
if( ! defined('AW_HOOK_PRIORITY') ){
    define('AW_HOOK_PRIORITY', 1 << 17);
}

$aw = new AsyncWidget();
/*
 * Register the action that'll proxy all necessary widget callbacks.
 */
add_action("wp_head", array(&$aw, 'setup_widget_callback_intercept'), AW_HOOK_PRIORITY);


/*********************************************
 *
 * AJAX AND JAVASCRIPT GOODIES
 *
 *********************************************/

add_action('template_redirect', 'aw_enqueue_jquery');
/*
 * Ensure the jQuery library is available on public pages.
 */
function aw_enqueue_jquery(){
    wp_enqueue_script(array('jquery'));
}

add_action('wp_head', 'aw_cond_output_js_loader');
/**
 * Conditionally insert the JavaScript that'll perform
 * the asynchronous loading.
 * "Conditionally", for if we aren't set up to async any widget,
 * we don't output the code.
 *
 * @return void
 */
function aw_cond_output_js_loader(){
    $aw = new AsyncWidget();
    if( $aw->get_async_widget_count() == 0 ){
        /*
         * nothing to do: don't pollute the page with yet more script.
         */
        return;
    }

    /*
     * See AsyncWidget::async_widget_proxy_callback for
     * an explanation of the identifiers used here.
     */
    ?>
<script type="text/javascript">
//<![CDATA[
    jQuery(document).ready( function(){
        jQuery.each(
            jQuery(".async_widget_placeholder"),
            function(index, element){
                var wid = jQuery(element).children(".async_widget_id").text();
                var sid = jQuery(element).children(".aw_session_id").text();
                jQuery.ajax({
                    type: "POST",
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    data: "action=aw_get_widget&widget_id=" + wid + "&session_id=" + sid,
                    dataType: "html",
                    success: function(data, status){
                        jQuery(element).replaceWith(data);
                    }
                });
            }
        );
    });
//]]>
</script>
<?php
}

add_action('wp_ajax_aw_get_widget', 'async_widgets_get_callback');
add_action('wp_ajax_nopriv_aw_get_widget', 'async_widgets_get_callback');
/**
 * This is the function called from the AJAX query.
 *
 * Serves the real widget content.
 */
function async_widgets_get_callback(){
    if( ! isset($_POST['widget_id']) ){
        header("HTTP/1.1 500 Internal Server Error", true, 500);
        echo "Error: widget_id not found\n";
        die(0xff);
    }
    if( ! isset($_POST['session_id']) ){
        header("HTTP/1.1 500 Internal Server Error", true, 500);
        echo "Error: session_id not found\n";
        die(0xff);
    }

    $widgetid = $_POST['widget_id'];

    $sid = $_POST['session_id'];
    session_id($sid);
    session_start();

    $aw = new AsyncWidget();
    $aw->get_widget_content($widgetid);

    die();
}

/***********************************************
 *
 * UTILITY CLASS
 *
 ***********************************************/

/**
 * Helper class. Hardly modelling anything (don't let the name mislead you).
 */
class AsyncWidget {

    protected $widgetstore = array();

    public function  __construct() {
        /*
         * restore from persistence.
         */

        $stored = get_option("async_widgets_store");

        if( $stored ){
            $this->widgetstore = &$stored;
        }
    }

    /**
     * Returns the number of widgets we are currently enabled for.
     *
     * @return int the number of widgets we are currently enabled for.
     */
    public function get_async_widget_count(){
        return count($this->widgetstore);
    }

    /**
     * Returns a boolean indicating whether or not the async mechanism is
     * enabled for the widget with the given ID.
     *
     * @param string $widget_id
     * @return boolean
     */
    public function is_async_enabled($widget_id){
        return isset($this->widgetstore[$widget_id]);
    }

    /**
     * Persistence
     */
    protected function save_widget_store(){
        update_option("async_widgets_store", $this->widgetstore);
    }

    /**
     * Enable the async mechanism for the widget with the given ID.
     *
     * @global <type> $wp_registered_widgets
     * @param string $widget_id
     * @return true, if the async mechanism could be enabled, false otherwise.
     */
    public function enable_async($widget_id){
        global $wp_registered_widgets;

        if( ! isset($wp_registered_widgets[$widget_id]) ){
            return false;
        }

        $this->widgetstore[$widget_id] = array();
        $this->save_widget_store();

        return true;
    }

    /**
     * Disable the async mechanism for the widget with the given ID.
     *
     * @global <type> $wp_registered_widgets
     * @param string $widget_id
     * @return false, if the async mechanism wasn't enabled for whe widget with
     * the given ID, true otherwise.
     */
    public function disable_async($widget_id){
        global $wp_registered_widgets;

        if( ! isset($this->widgetstore[$widget_id]) ){
            return false;
        }

        unset($this->widgetstore[$widget_id]);
        $this->save_widget_store();

        return true;
    }

    /**
     * This is called when the plugin is deactivated.
     */
    public function deactivate_all(){
        //nothing for now. Maybe later.
    }

    /**
     * This is called when the plugin is activated.
     */
    public function restore_all(){
        //nothing for now. Maybe later.
    }

    private function check_async_widget_enabled_or_throw($widgetid){
        if( ! $this->is_async_enabled($widgetid) ){
            throw new Exception("Async loading not enabled for widget: $widget_id");
        }
    }

    /**
     * Serve the real widget content. This is called from the AJAX query.
     * <p>
     * If the async mechanism isn't enabled for whe widget
     * with the given ID, an Exception is thrown.
     *
     * @param string $widget_id
     */
    public function get_widget_content($widget_id){
        $this->check_async_widget_enabled_or_throw($widget_id);

        $real_callback = $this->widgetstore[$widget_id]['callback'];

//        $params = $this->widgetstore[$widget_id]['params'];
        $params = $_SESSION['aw_'.$widget_id]['params'];

        //see function doc
        $this->restore_widget_cache_redirected_callback($widget_id);

        call_user_func_array($real_callback, $params);

        //won't be needed anymore
        unset($_SESSION['aw_'.$widget_id]);
    }

    /**
     * This is an interoperability hack for the WP Widget Cache plugin -- we need to make the changes
     * it opers persistent.
     *
     * @global <type> $wp_registered_widgets
     * @param string $widgetid
     */
    private function restore_widget_cache_redirected_callback($widgetid){
        global $wp_registered_widgets;

        if( isset($this->widgetstore[$widgetid]['callback_wc_redirect']) ){
            $wp_registered_widgets[$widgetid]['callback_wc_redirect'] = $this->widgetstore[$widgetid]['callback_wc_redirect'];
        }
    }

    /**
     * Called on each GET of a normal page (wp_head).
     *
     * Here we set up our proxy callbacks for all the widgets we are configured to.
     */
    public function setup_widget_callback_intercept(){
        global $wp_registered_widgets;

        $widgetids = array_keys($this->widgetstore);

        $need_save = false;
        foreach($widgetids as $id){
            if( ! isset($wp_registered_widgets[$id]) ){
                //WTF!? 
                continue;
            }

            $callback = $wp_registered_widgets[$id]['callback'];

            if( ! is_callable($callback) ){
                //Just skip.
                continue;
            }

            //replace the callback with our own
            $this->widgetstore[$id]['callback'] = $callback;
            $wp_registered_widgets[$id]['callback']=array(&$this, 'async_widget_proxy_callback');
            $need_save = true;
            
            /*
             * WP Widget Cache interoperability hack
             */
            if( isset($wp_registered_widgets[$id]['callback_wc_redirect']) ){
                //store the WidgetCache redirect callback -- it's transient.
                $wc_callback = $wp_registered_widgets[$id]['callback_wc_redirect'];
                $this->widgetstore[$id]['callback_wc_redirect'] = $wc_callback;

                $need_save = true;
            }
        }

        if( $need_save ){
            $this->save_widget_store();
        }
    }

    /**
     * This is the proxy callback we've replaced the
     * registered widget callbacks with.
     *
     * onload, client-side JS will parse widget_id and replace
     * the placeholder element (see below).
     *
     * @todo I18N of noscript message.
     * @uses aw_cond_output_js_loader
     */
    function async_widget_proxy_callback(){
        global $wp_registered_widgets;

        $args = func_get_args();

        //there's a bit of guesswork involved
        //here, but it appears to work.
        $widgetsettings = $args[0];

        $id = $widgetsettings['widget_id'];

        /*
         * Allow other plugins to hook on this action
         */
        do_action('async_widgets_output_proxy_html', $widgetsettings);

        $this->async_widgets_output_proxy_html($widgetsettings);

        //store things
        $_SESSION['aw_'.$id]['params'] = $args;
    }

    function async_widgets_output_proxy_html($widgetsettings){
        /*
         * Allow other plugins to filter on this action
         */
        $widgetsettings = apply_filters(
                'async_widgets_output_proxy_html_filter',
                $widgetsettings
        );

        if( ! is_array($widgetsettings) || ! isset($widgetsettings['id']) ){
            return;
        }

        $id = $widgetsettings['widget_id'];
        $name = $widgetsettings['widget_name'];

        $sessionid = session_id();

        ?>
<div class="widget async_widget_placeholder">
<span class="async_widget_id" style="visibility: hidden;"><?php echo $id;?></span>
<span class="aw_session_id" style="visibility: hidden;"><?php echo $sessionid;?></span>
<center><noscript>You need to have JavaScript enabled to view the widget "<?php echo $name; ?>"</noscript></center>
</div>
        <?php
    }

    /**
     * Filter out de-registered widgets.
     */
    function clean_store(){
        $count_pre = count($this->widgetstore);

        global $wp_registered_widgets;

        $this->widgetstore = array_intersect_key($this->widgetstore, $wp_registered_widgets);

        if( count($this->widgetstore) != $count_pre ){
            echo "<span>Cleaned ".($count_pre - count($this->widgetstore))." stale key(s)</span>\n";
            $this->save_widget_store();
        }
    }
}


/*************************************************
 *
 * ADMIN INTEGRATION
 *
 ************************************************/

add_action("admin_init", "aw_admin_init");
add_action("admin_menu", "aw_admin_plugin_menu");

function aw_admin_init(){
    add_action('wp_ajax_aw_toggle_async_widget', 'aw_toggle_async_widget_callback');
    
    wp_register_style('aw_admin_styles', plugin_dir_url(__FILE__)."async-widgets-admin.css", false);
}

function aw_admin_plugin_menu(){
    $page = add_options_page(
            "Async Widgets",
            "Async Widgets",
            "administrator",
            "aw_plugin_config",
            "init_aw_plugin_config_page"
    );

    /* Using registered $page handle to hook stylesheet loading */
    //going per codex sample. Man, is this convoluted!
    add_action('admin_print_styles-' . $page, 'aw_enqueue_admin_styles');
}

function aw_enqueue_admin_styles(){
    wp_enqueue_style("aw_admin_styles");
}

function init_aw_plugin_config_page(){
    $aw = new AsyncWidget();
    $aw->clean_store();
    
    include 'aw_plugin_config.php';
}

function aw_toggle_async_widget_callback(){
    $aw = new AsyncWidget();

    $nonce = $_POST['_ajax_nonce'];

    if( ! wp_verify_nonce($nonce, 'aw_toggle_async_widget') ){
        header("HTTP/1.1 401 Authorization Required", true, 401);
        die(0xff);
    }

    $widgetid = $_POST['widget_id'];

    /*
     * Here be dragons!
     *
     * The client-side acts on the returned data;
     * specifically, it must be "1" (and only that)
     * to indicate that the async for that widget
     * is now ENabled. Anything else will be interpreted
     * as that it's DISabled.
     * (see JavaScript in aw_plugin_config.php (aw_toggle_async_widget)).
     */

    try {
        if( $aw->is_async_enabled($widgetid) ){
            $aw->disable_async($widgetid);
            echo "0";
        }
        else {
            $aw->enable_async($widgetid);
            echo "1";
        }
    }
    catch (Exception $ex){
        header("HTTP/1.1 500 Internal Error", true, 500);
        echo $ex->getTraceAsString();
    }

    die();
}

?>
