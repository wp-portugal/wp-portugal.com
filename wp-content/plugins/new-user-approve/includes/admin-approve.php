<?php

/**
 * Class pw_new_user_approve_admin_approve
 * Admin must approve all new users
 */

class pw_new_user_approve_admin_approve
{

    public $_admin_page = 'new-user-approve-admin';
    public $_admin_upgrade_page = 'new-user-approve-upgrade';

    /**
     * The only instance of pw_new_user_approve_admin_approve.
     *
     * @var pw_new_user_approve_admin_approve
     */
    private static $instance;

    /**
     * Returns the main instance.
     *
     * @return pw_new_user_approve_admin_approve
     */
    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new pw_new_user_approve_admin_approve();
        }
        return self::$instance;
    }

    /**
     * @since 1.0
     * @since 2.1 `admin_post_nua-save-api-key` added for zapier
     */
    private function __construct()
    {
        // Actions
        add_action('admin_menu', array($this, 'admin_menu_link'));
        add_action('admin_init', array($this, 'process_input'));
        add_action('admin_notices', array($this, 'admin_notice'));
        add_action('admin_init', array($this, 'notice_ignore'));
        add_action('admin_init', array($this, '_add_meta_boxes'));
        add_action('admin_post_nua-save-api-key', array($this, 'save_api_key'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_script'));
    }

    public function admin_enqueue_script()
    {
        wp_enqueue_script('hide-seek-search', pw_new_user_approve()->get_plugin_url() . '/assets/js/jquery.hideseek.min.js', array('jquery'), '3.1');
    }

    /**
     * Add the new menu item to the users portion of the admin menu
     *
     * @uses admin_menu
     */
    public function admin_menu_link()
    {
        $show_admin_page = apply_filters('new_user_approve_show_admin_page', true);

        if ($show_admin_page) {
            $cap = apply_filters('new_user_approve_minimum_cap', 'edit_users');
            $hook = add_menu_page(__('Approve New Users', 'new-user-approve'), __('Approve New Users', 'new-user-approve'), $cap, $this->_admin_page, array($this, 'approve_admin'));
            $hook_upgrade = add_submenu_page($this->_admin_page, __('Upgrade', 'new-user-approve'), __('Upgrade', 'new-user-approve'), $cap, $this->_admin_upgrade_page, array($this, 'new_user_approve_upgrade'));
            add_action('load-' . $hook, array($this, 'admin_enqueue_scripts'));
            
            add_action('load-' . $hook_upgrade, array($this, 'admin_enqueue_upgrade_scripts'));
            
        }
       
    }

    public function admin_enqueue_upgrade_scripts() {
       
        wp_enqueue_script('upgrade-script', pw_new_user_approve()->get_plugin_url() . '/assets/js/modernizr.min.js', array('jquery'), NUA_VERSION);
        wp_enqueue_script('upgrade-freemius-script', pw_new_user_approve()->get_plugin_url() . '/assets/js/checkout.min.js',array('jquery'), NUA_VERSION);
       
    }

    /**
     * Create the upgrade to premium page
     */
    public function new_user_approve_upgrade()
    {
        require_once pw_new_user_approve()->get_plugin_dir() . '/admin/templates/upgrade.php';
    }

    /**
     * Create the view for the admin interface
     */
    public function approve_admin()
    {
        require_once pw_new_user_approve()->get_plugin_dir() . '/admin/templates/approve.php';
    }

    /**
     * Output the table that shows the registered users grouped by status
     *
     * @param string $status the filter to use for which the users will be queried. Possible values are pending, approved, or denied.
     */
    public function user_table($status)
    {
        global $current_user;

        $approve = ('denied' == $status || 'pending' == $status);
        $deny = ('approved' == $status || 'pending' == $status);

        $user_status = pw_new_user_approve()->get_user_statuses();
        $users = $user_status[$status];

        if (count($users) > 0) {
            if ('denied' == $status) {
                ?>
				<p class="status_heading"><?php esc_html_e('Denied Users', 'new-user-approve');?></p>
				<?php
} else if ('approved' == $status) {
                ?>
				<p class="status_heading"><?php esc_html_e('Approved Users', 'new-user-approve');?></p>
				<?php
} else if ('pending' == $status) {
                ?>
				<p class="status_heading"><?php esc_html_e('Pending Users', 'new-user-approve');?></p>
				<?php
}
            ?>
			<table class="widefat">
				<thead>
				<tr class="thead">
					<th><?php esc_html_e('Username', 'new-user-approve');?></th>
					<th><?php esc_html_e('Name', 'new-user-approve');?></th>
					<th><?php esc_html_e('E-mail', 'new-user-approve');?></th>
					<?php if ('pending' == $status) {?>
						<th colspan="2"><?php esc_html_e('Action', 'new-user-approve');?></th>
					<?php } else {?>
						<th><?php esc_html_e('Action', 'new-user-approve');?></th>
					<?php }?>
				</tr>
				</thead>
				<tbody class="nua-user-list">
				<?php
// show each of the users
            $row = 1;
            foreach ($users as $user) {
                $class = ($row % 2) ? '' : ' class="alternate"';
                $avatar = get_avatar($user->user_email, 32);

                if ($approve) {
                    $approve_link = get_option('siteurl') . '/wp-admin/admin.php?page=' . $this->_admin_page . '&user=' . $user->ID . '&status=approve';
                    if (isset($_REQUEST['tab'])) {
                        $approve_link = add_query_arg(array('tab' => sanitize_text_field(wp_unslash($_REQUEST['tab']))), $approve_link);
                    }

                    $approve_link = wp_nonce_url($approve_link, 'pw_new_user_approve_action_' . get_class($this));

                }
                if ($deny) {
                    $deny_link = get_option('siteurl') . '/wp-admin/admin.php?page=' . $this->_admin_page . '&user=' . $user->ID . '&status=deny';
                    if (isset($_REQUEST['tab'])) {
                        $deny_link = add_query_arg('tab', sanitize_text_field(wp_unslash($_REQUEST['tab'])), $deny_link);
                    }

                    $deny_link = wp_nonce_url($deny_link, 'pw_new_user_approve_action_' . get_class($this));
                }

                if (current_user_can('edit_user', $user->ID)) {
                    if ($current_user->ID == $user->ID) {
                        $edit_link = 'profile.php';
                    } else {
                        $SERVER_URI  = get_admin_url();
                        if (isset($_SERVER['REQUEST_URI'])) {  $SERVER_URI = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])); }
                        $edit_link = add_query_arg('wp_http_referer', urlencode(esc_url($SERVER_URI)), "user-edit.php?user_id=$user->ID");
                    }
                    $edit = ($avatar == true) ? ('<strong style="position: relative; top: -17px; left: 6px;"><a href="' . esc_url($edit_link) . '">' . esc_html($user->user_login) . '</a></strong>') : ('<strong style="top: -17px; left: 6px;"><a href="' . esc_url($edit_link) . '">' . esc_html($user->user_login) . '</a></strong>');

                } else {
                    $edit = ($avatar == true) ? ('<strong style="position: relative; top: -17px; left: 6px;">' . esc_html($user->user_login) . '</strong>') : ('<strong style="top: -17px; left: 6px;">' . esc_html($user->user_login) . '</strong>');

                }

                ?>
					<tr <?php echo esc_attr($class); ?>>
					<td><?php echo wp_kses_post($avatar . ' ' . $edit); ?></td>
					<td><?php echo (esc_attr(get_user_meta($user->ID, 'first_name', true)) . ' ' . esc_attr(get_user_meta($user->ID, 'last_name', true))); ?></td>
					<td><a href="mailto:<?php esc_attr_e($user->user_email);?>"
						   title="<?php esc_attr_e('email:', 'new-user-approve')?> <?php esc_attr_e($user->user_email);?>"><?php esc_html_e($user->user_email);?></a>
					</td>
					<?php if ($approve && $user->ID != get_current_user_id()) {?>
							<td><a class="button" href= "<?php echo esc_url($approve_link) ?>" title="<?php esc_attr_e('Approve', 'new-user-approve');?> <?php esc_attr_e($user->user_login);?>"><?php esc_html_e('Approve', 'new-user-approve');?></a>
						</td>
					<?php }?>
					<?php if ($deny && $user->ID != get_current_user_id()) {?>
						<td><a class="button" href="<?php echo esc_url($deny_link); ?>" title="<?php esc_attr_e('Deny', 'new-user-approve');?> <?php esc_attr_e($user->user_login);?>"><?php echo esc_html('Deny', 'new-user-approve'); ?></a>
						</td>
					<?php }?>
					<?php if ($user->ID == get_current_user_id()): ?>
						<td colspan="2">&nbsp;</td>
					<?php endif;?>
					</tr><?php
$row++;
            }
            ?>
				</tbody>
			</table>
		<?php
} else {
            $status_i18n = $status;
            if ($status == 'approved') {
                $status_i18n = __('approved', 'new-user-approve');
            } else if ($status == 'denied') {
                $status_i18n = __('denied', 'new-user-approve');
            } else if ($status == 'pending') {
                $status_i18n = __('pending', 'new-user-approve');
            }

            ?>

			<p>  <?php sprintf(__('There are no users with a status of %s', 'new-user-approve'), $status_i18n);?> </p>
	<?php
}
    }

    /**
     * Accept input from admin to modify a user
     *
     * @uses init
     */
    public function process_input()
    {
        if ((isset($_GET['page']) && $_GET['page'] == $this->_admin_page) && isset($_GET['status'])&& isset($_GET['user'])) {
            $valid_request = check_admin_referer('pw_new_user_approve_action_' . get_class($this));

            if ($valid_request) {
                $status = sanitize_key($_GET['status']);
                $user_id = absint(sanitize_user(wp_unslash($_GET['user'])));

                pw_new_user_approve()->update_user_status($user_id, $status);
            }
        }
    }

    /**
     * Display a notice on the legacy page that notifies the user of the new interface.
     *
     * @uses admin_notices
     */
    public function admin_notice()
    {
        $screen = get_current_screen();

        if ($screen->id == 'users_page_new-user-approve-admin') {
            $user_id = get_current_user_id();

            // Check that the user hasn't already clicked to ignore the message
            if (!get_user_meta($user_id, 'pw_new_user_approve_ignore_notice')) {
                ?>
				<div class="updated"><p>
				<?php printf(wp_kses_post(__('You can now update user status on the <a href="%1$s">users admin page</a>. | <a href="%2$s">Hide Notice</a>', 'new-user-approve'), admin_url('users.php'), esc_url( add_query_arg(array('new-user-approve-ignore-notice' => 1)))));?>
				 </p></div>
		<?php
}
        }
    }

    /**
     * If user clicks to ignore the notice, add that to their user meta
     *
     * @uses admin_init
     */
    public function notice_ignore()
    {
        if (isset($_GET['new-user-approve-ignore-notice']) && '1' == $_GET['new-user-approve-ignore-notice']) {
            $user_id = get_current_user_id();
            add_user_meta($user_id, 'pw_new_user_approve_ignore_notice', '1', true);
        }
    }

    public function admin_enqueue_scripts()
    {
        wp_enqueue_script('post');
    }

    public function _add_meta_boxes()
    {
        add_meta_box('nua-approve-admin', __('Approve Users', 'new-user-approve'), array($this, 'metabox_main'), 'users_page_new-user-approve-admin', 'main', 'high');
        add_meta_box('nua-support', __('Support', 'new-user-approve'), array($this, 'metabox_support'), 'users_page_new-user-approve-admin', 'side', 'default');
        add_meta_box('nua-feedback', __('Feedback', 'new-user-approve'), array($this, 'metabox_feedback'), 'users_page_new-user-approve-admin', 'side', 'default');
    }

    public function metabox_main()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'pending_users';
        ?>
		<h3 class="nav-tab-wrapper" style="padding-bottom: 0; border-bottom: none;">
			<script>
				jQuery(document).ready(function() {
					jQuery('input[name="nua_search"]').hideseek({
						highlight: true
					});
				});
			</script>
			<input type="search" name="nua_search" placeholder="Search" style="float: right;" data-list=".nua-user-list">
			<a href="<?php echo esc_url(admin_url('admin.php?page=new-user-approve-admin&tab=pending_users')); ?>"
				class="nav-tab<?php echo $active_tab == 'pending_users' ? ' nav-tab-active' : ''; ?>"><span><?php esc_html_e('Pending Users', 'new-user-approve');?></span></a>
			<a href="<?php echo esc_url(admin_url('admin.php?page=new-user-approve-admin&tab=approved_users')); ?>"
			   class="nav-tab<?php echo $active_tab == 'approved_users' ? ' nav-tab-active' : ''; ?>"><span><?php esc_html_e('Approved Users', 'new-user-approve');?></span></a>
			<a href="<?php echo esc_url(admin_url('admin.php?page=new-user-approve-admin&tab=denied_users')); ?>"
			   class="nav-tab<?php echo $active_tab == 'denied_users' ? ' nav-tab-active' : ''; ?>"><span><?php esc_html_e('Denied Users', 'new-user-approve');?></span></a>
			<a href="<?php echo esc_url(admin_url('admin.php?page=new-user-approve-admin&tab=zapier')); ?>"
			   class="nav-tab<?php echo $active_tab == 'zapier' ? ' nav-tab-active' : ''; ?>"><span><?php esc_html_e('Zapier', 'new-user-approve');?></span></a>
			<a href="<?php echo esc_url(admin_url('admin.php?page=new-user-approve-admin&tab=pro_features')); ?>"
 			   class="nav-tab<?php echo $active_tab == 'pro_features' ? ' nav-tab-active' : ''; ?>"><span><?php esc_html_e('Pro Features', 'new-user-approve');?></span></a>
 		</h3>

<?php if ($active_tab == 'pending_users'): ?>
	<div id="pw_pending_users">
		<?php $this->user_table('pending');?>
	</div>
<?php elseif ($active_tab == 'approved_users'): ?>
	<div id="pw_approved_users">
		<?php $this->user_table('approved');?>
	</div>
<?php
elseif ($active_tab == 'denied_users'): ?>
	<div id="pw_denied_users">
		<?php $this->user_table('denied');?>
	</div>
	<?php
elseif ($active_tab == 'zapier'): ?>
	<div id="pw_denied_users">
		<?php $this->zapier();?>
	</div>
	<?php
elseif ($active_tab == 'pro_features'): ?>
	<div id="pw_pro_features">
		<?php $this->pro_features();?>
	</div>
<?php endif;
    }

    public function pro_features()
    {
        ?>
		<h3>Premium Features</h3>
		<ul style="padding-left: 30px; list-style-type: disc;">
			<li>Provides Ability To remove plugin stats from admin dashboard.</li>
			<li>Remove the admin panel specifically added to update a user's status, from wordpress dashboard.</li>
			<li>Customize the welcome message displayed above wordpress login form.</li>
			<li>Customize the 'Pending error message' displayed when user tries to login but his account is still pending approval.</li>
			<li>Customize the 'Denied error message' displayed when user tries to login but his account is denied approval.</li>
			<li>Customize the welcome message displayed above wordpress Registration form.</li>
			<li>Customize the Registration complete message displayed after user submits Registration form for approval.</li>
			<li>Provide Ability To Send Approval notification emails to all admins</li>
			<li>Notify admins when a user's status is updated</li>
			<li>Disable notification emails to current site admin</li>
			<li>Customize the email sent to admin when a user registers for the site</li>
			<li>Customize the email sent to user when his profile is approved.</li>
			<li>Customize the email sent to user when his profile is denied.</li>
			<li>Suppress denial email notification</li>
			<li>Provides option to send all email notifications as html.</li>
			<li>It Provides you Different template tags which can be used in Notification Emails and Other messages on site.</li>
		</ul>

		<p>Please Visit this link For <a class="button" href="https://newuserapprove.com/options-addon" target="_blank" >Premium Plugin </a> </p>
		<?php
}

    /**
     * Renders Zapier Tab
     * @since 2.0
     * @version 1.0
     */
    public function zapier()
    {

        $triggers = array(
            __('Triggers when a user is Approved.', 'new-user-approve'),
            __('Triggers when a user is Denied.', 'new-user-approve'),
        );

        $api_key = (\NewUserApproveZapier\RestRoutes::api_key()) ? "value='" . \NewUserApproveZapier\RestRoutes::api_key() . "'" : '';

        ?>
       <?php $get_api_key = get_option('nua_api_key', $api_key);?>
		<html>

		<p class="status_heading"> <?php esc_html_e('Zapier Settings', 'new-user-approve');?>  </p>

		<table cellpadding='10'>
			<tr>
				<td> <?php esc_html_e('Website URL: ', 'new-user-approve')?> </td>
				<td>  <?php echo esc_url(get_site_url()); ?> </td>
			</tr>
			<tr>
				<td> <?php esc_html_e('API Key: ', 'new-user-approve');?> </td>
				<td>
					<form action=<?php echo esc_url(admin_url('admin-post.php')); ?> method="POST">
						<?php $nonce = wp_create_nonce('api-generate-nonce');?>
						<input type='text' name='nua_api_key' id='nua-api' value = "<?php $get_api_key ? esc_attr_e($get_api_key) : ''?>"  />
						<button id='nua-generate-api' class='button'>Generate API Key</button>
						<input type='hidden' name='wp-api-generate-nonce' value='<?php esc_attr_e($nonce)?>' />
						<input type='hidden' name='action' value='nua-save-api-key' />
						<input type='submit' value='Save' name='nua_save_api'  class='button'/>
					</form>
				</td>
			</tr>
		</table>

		<p class="status_heading"> <?php esc_html_e('Triggers', 'new-user-approve')?> </p>
		<ul style='padding-left: 30px; list-style-type: disc;'>
	      <?php foreach ($triggers as $trigger): ?>

			      <li> <?php esc_html_e($trigger);?> </li>

	      <?php endforeach;?>
	   </ul>
		</html>
	<?php
}

    public function save_api_key()
    {

        

        if (isset($_REQUEST['wp-api-generate-nonce']) && isset($_POST['action']) && $_POST['action'] == 'nua-save-api-key') {
             $nonce = sanitize_text_field(wp_unslash($_REQUEST['wp-api-generate-nonce']));
            if (wp_verify_nonce($nonce, 'api-generate-nonce') && isset($_POST['nua_api_key'])) {

                $api_key =  sanitize_text_field(wp_unslash($_POST['nua_api_key']));

                update_option('nua_api_key', $api_key);

                wp_redirect(admin_url('admin.php?page=new-user-approve-admin&tab=zapier'));
            }
        }
    }

    public function metabox_support()
    {
        ?>
		<p>If you haven't already, check out the <a href="https://wordpress.org/plugins/new-user-approve/faq/" target="_blank">Frequently Asked Questions</a>.</p>
		<p>Still not fixed? Please <a href="https://wordpress.org/support/plugin/new-user-approve" target="_blank">start a support topic</a> and I or someone from the community will be able to assist you.</p>
<?php
}

    public function metabox_feedback()
    {
        ?>
		<p>Please show your appreciation for New User Approve by giving it a positive <a href="https://wordpress.org/support/view/plugin-reviews/new-user-approve#postform" target="_blank">review</a> in the plugin repository!</p>
<?php
}

}

function pw_new_user_approve_admin_approve()
{
    return pw_new_user_approve_admin_approve::instance();
}

pw_new_user_approve_admin_approve();
