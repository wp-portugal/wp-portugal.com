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
 * We're putting this in the footer, since we're too late to catch
 * the head by the time we get here.
 */
add_action("admin_footer", "aw_admin_js");

function aw_admin_js(){
    ?>
<script type="text/javascript">
 //<![CDATA[
    jQuery(document).ready(
        function(){
            if( jQuery("#aw_show_active_only").attr("checked") ){
                jQuery(".aw_inactive_row").hide();
            }
        }
    );
    function aw_toggle_show_inactive_widgets() {
        jQuery(".aw_inactive_row").toggle();

        return true;
    }

    function aw_toggle_async_widget(widget_id) {
        jQuery.ajax({
            type: "POST",
            url: ajaxurl,
            data: "action=aw_toggle_async_widget&widget_id=" + widget_id + "&_ajax_nonce=<?php echo wp_create_nonce("aw_toggle_async_widget"); ?>",
            dataType: "text",
            beforeSend: function(XMLHttpRequest){
                jQuery("#aw_actionbtn_" + widget_id).removeClass("aw_actionbtn_enabled").removeClass("aw_actionbtn_disabled").removeClass("aw_actionbtn_error");
                jQuery("#aw_actionbtn_"+widget_id).attr("value", "Saving...");
            },
            error: function(XMLHttpRequest, textStatus, errorThrown){
                var el = jQuery("#aw_actionbtn_"+widget_id);
                el.addClass("aw_actionbtn_error");
                el.attr("value", "Error!");
            },
            success: function(data, status){
                jQuery("#aw_actionbtn_ajax_feedback_"+widget_id).attr("value", "OK");

                aw_update_actionbtn(widget_id, "1" == data);
            }
        });
    }

    function aw_update_actionbtn(widgetid, active) {
        var el = jQuery("#aw_actionbtn_" + widgetid);

        if( active ){
            el.attr("value", "ON");
            el.addClass("aw_actionbtn_enabled");
        }
        else {
            el.attr("value", "OFF");
            el.addClass("aw_actionbtn_disabled");
        }
    }
//]]>
</script>
<?php
}
?>

<div class="wrap">

    <h2>Asynchronous Widgets Configuration</h2>

    <p>The list of registered widgets is displayed below.<br />
        A widget is marked as "active" when it is currently assigned to a sidebar.
    </p>
    <p>Enable or disable asynchronous loading of a given widget by toggling
        the button in the first column of the corresponding row.<br />
        Note that enabling this mechanism for an inactive widget has no effect.
    </p>

    <table class="aw_admin_widget_table form-table">
        <caption>
            <input type="checkbox" id="aw_show_active_only" 
               checked="checked" onclick="javascript:aw_toggle_show_inactive_widgets();"
            />
            <label for="aw_show_active_only">
                <?php echo _e("Show only active widgets"); ?>
            </label>
        </caption>
            
        <thead>
            <tr>
                <th>Async</th>
                <th>Name</th>
                <th>ID</th>
                <th>Active?</th>
                <th class="aw_admit_widget_table_col_description">Description</th>
            </tr>
        </thead>

        <colgroup>
            <col width="10%">
            <col width="15%">
            <col width="15%">
            <col width="15%">
            <col width="45%">
        </colgroup>

        <?php
            global $wp_registered_widgets;

            $async_widget = new AsyncWidget();

            //copy for sorting
            $widgets = $wp_registered_widgets;
            ksort($widgets);

            foreach($widgets as $key => $value){
                $callback = $widgets[$key]['callback'];
                $id = $widgets[$key]['id'];

                $active = is_active_widget($callback, $id);
                $style = $active ? "aw_admin_widget_table_widget_active" : "aw_admin_widget_table_widget_inactive";
                $enabled = $async_widget->is_async_enabled($id);
            ?>
        <tr class="<?php echo $active ? "aw_active_row" : "aw_inactive_row"; ?>"
        >
            <td>
                <input type="button" id="aw_actionbtn_<?php echo $id; ?>"
                      class="button <?php echo $enabled ? "aw_actionbtn_enabled" : "aw_actionbtn_disabled"; ?>"
                      onClick="javascript:<?php echo "aw_toggle_async_widget('$id')"; ?>;" 
                      value="<?php echo $enabled ? "ON" : "OFF"; ?>"
                />
            </td>
            <td><?php echo $value['name']; ?></td>
            <td><?php echo $value['id']; ?></td>

            <td class="<?php echo $style; ?>"><?php echo $active ? _e("YES") : _e("NO") ?></td>

            <td class="aw_admit_widget_table_col_description"><?php echo $value['description']; ?></td>
        </tr>
        <?php
            }
        ?>
    </table>
</div>
