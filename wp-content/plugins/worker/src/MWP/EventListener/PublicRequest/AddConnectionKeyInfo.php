<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_PublicRequest_AddConnectionKeyInfo implements Symfony_EventDispatcher_EventSubscriberInterface
{
    private $context;

    private $slug = 'worker/init.php';

    function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::PUBLIC_REQUEST => 'onPublicRequest',
        );
    }

    public function onPublicRequest()
    {
        $this->context->addAction('admin_init', array($this, 'enqueueConnectionModalOpenScripts'));
        $this->context->addAction('admin_init', array($this, 'enqueueConnectionModalOpenStyles'));
        $this->context->addFilter('plugin_row_meta', array($this, 'addConnectionKeyLink'), 10, 2);
        $this->context->addAction('admin_head', array($this, 'printConnectionModalOpenScript'));
        $this->context->addAction('admin_footer', array($this, 'printConnectionModalDialog'));
    }

    public function enqueueConnectionModalOpenScripts()
    {
        $this->context->enqueueScript('jquery');
        $this->context->enqueueScript('jquery-ui-core');
        $this->context->enqueueScript('jquery-ui-dialog');

        /** @handled function */
        load_plugin_textdomain('worker');
    }

    public function enqueueConnectionModalOpenStyles()
    {
        $this->context->enqueueStyle('wp-jquery-ui');
        $this->context->enqueueStyle('wp-jquery-ui-dialog');
    }

    protected function checkForKeyRefresh()
    {
        if (empty($_GET['mwp_force_key_refresh'])) {
            return false;
        }

        return mwp_refresh_live_public_keys(array());
    }

    protected function checkAndPurgeData()
    {
        if (!isset($_GET['mwp_nonce']) || !wp_verify_nonce($_GET['mwp_nonce'], 'mwp_clear_data')) {
            return false;
        }
        if (!isset($_GET['action']) || $_GET['action'] !== 'mwp_clear_data') {
            return false;
        }
        global $wpdb;
        $sql = "DELETE FROM `". $wpdb->prefix ."options` WHERE `option_name` LIKE 'mwp_%';";
        $wpdb->query($wpdb->prepare($sql));

        return mwp_refresh_live_public_keys(array());
    }

    protected function checkForDeletedConnectionKey()
    {
        if (!isset($_GET['mwp_nonce']) || !wp_verify_nonce($_GET['mwp_nonce'], 'mwp_deactivation_key')) {
            return false;
        }

        if (!isset($_GET['action']) || $_GET['action'] !== 'mwp_deactivate_key' || empty($_GET['connection_id'])) {
            return false;
        }

        mwp_remove_communication_key($_GET['connection_id']);

        return true;
    }

    public function printConnectionModalOpenScript()
    {
        if (!$this->userCanViewConnectionKey()) {
            return;
        }

        $deletedKey = $this->checkForDeletedConnectionKey();
        $purgeData  = $this->checkAndPurgeData();

        ob_start()
        ?>
        <style type="text/css" media="screen">
            .mwp-dialog > .ui-dialog-content {
                font-family: Helvetica, serif;
                font-size: 16px;
                padding: 40px;
                color: #52565C;
                letter-spacing: 0;
                line-height: 23px;
            }

            .mwp-dialog > .ui-dialog-content p {
                font-family: Helvetica, serif;
                font-size: 16px;
                color: #52565C;
            }

            .mwp-dialog > .ui-dialog-content h2 {
                color: #52565C;
                margin-bottom: 0;
            }

            .mwp-dialog > .ui-dialog-titlebar {
                background-color: #00A0D2;
                padding: 18px 32px;
                color: white;
            }

            .mwp-dialog > .ui-dialog-titlebar > .ui-dialog-titlebar-close {
                position: relative;
                float: right;
                left: 10px;
                top: 1px;
                color: #0989B1;
            }

            .mwp-dialog > .ui-dialog-titlebar > .ui-dialog-titlebar-close:hover {
                color: white;
            }

            .mwp-dialog > .ui-dialog-titlebar > .ui-dialog-titlebar-close:before {
                font-size: 30px;
            }

            .key-block {
                color: #757575;
                background: #FFFFFF !important;
                border: 1px solid #D6D6D6;
                border-radius: 5px;
                padding: 13px;
                width: 420px;
                margin-right: 18px;
            }

            .mwp-dialog .btn {
                background: #00A0D2;
                box-shadow: inset 0 -2px 0 0 rgba(0, 0, 0, 0.20);
                border-radius: 4px;
                font-family: Helvetica, serif;
                font-size: 16px;
                color: #FFFFFF;
                text-align: center;
                cursor: pointer;
            }

            .mwp-dialog table {
                background: #F5F7F8;
                border: 1px solid #D6D6D6;
                border-radius: 5px;
                border-collapse: collapse;
            }

            .mwp-dialog th, .mwp-dialog td {
                padding: 12px 20px 10px;
                text-align: left;
                font-weight: normal;
            }

            .mwp-dialog th {
                border-bottom: 1px solid #D6D6D6;
            }

            .mwp-dialog a {
                color: #0073AA;
                text-decoration: none;
            }

            .mwp-dialog a:hover, .mwp-dialog a:focus {
                color: #009FDA;
            }
        </style>

        <script type="text/javascript">
            <?php if ($deletedKey || $purgeData) { ?>
            window.location.replace(<?php echo json_encode($this->context->getAdminUrl('plugins.php?worker_connections=1')); ?>);
            <?php } ?>

            jQuery(document).ready(function ($) {
                $(document).on('click', '#mwp-view-connection-key', function (e) {
                    e.preventDefault();
                    $(document).trigger('mwp-connection-dialog');
                });

                $(document).on('click', 'button.copy-key-button', function () {
                    $('#connection-key').select();
                    document.execCommand('copy');
                });

                $(document).on('mwp-connection-dialog', function () {
                    $('#mwp_connection_key_dialog').dialog({
                        dialogClass: "mwp-dialog",
                        draggable: false,
                        resizable: false,
                        modal: true,
                        width: '600px',
                        height: 'auto',
                        title: <?php echo json_encode(esc_html__('Connection Management', 'worker')); ?>,
                        close: function () {
                            $(this).dialog("destroy");
                        }
                    });
                    $('#connection-key').select();
                });

                if (window.location.search.toLowerCase().indexOf('worker_connections=1') !== -1) {
                    $(document).trigger('mwp-connection-dialog');
                }
            });
        </script>
        <?php

        $content = ob_get_clean();
        $this->context->output($content);
    }

    public function printConnectionModalDialog()
    {
        if ($this->context->isMultisite() && !$this->context->isNetworkAdmin()) {
            return;
        }

        if (!$this->userCanViewConnectionKey()) {
            return;
        }

        $refreshedKeys = $this->checkForKeyRefresh();

        ob_start();
        ?>
        <div id="mwp_connection_key_dialog" style="display: none;">
            <?php
            $communicationKeys = mwp_get_communication_keys();
            $currentKey        = mwp_get_communication_key();

            if (!empty($currentKey)) {
                $communicationKeys['any'] = array(
                    'added' => null,
                );
            }

            if (empty($communicationKeys)) { ?>
                <p style="margin-top: 0"><?php
                    /** @handled function */
                    echo esc_html__('There are two ways to connect your website to the management dashboard:', 'worker'); ?>
                </p>

                <h2><?php
                    /** @handled function */
                    echo esc_html__('Automatic', 'worker'); ?>
                </h2>
                <ol>
                    <li>
                        <?php
                        /** @handled function */
                        echo esc_html__('Log into your account.', 'worker');

                        ?>
                    </li>
                    <li><?php
                        /** @handled function */
                        echo esc_html__('Click the Add site button.', 'worker'); ?>
                    </li>
                    <li>
                        <?php
                        /** @handled function */
                        echo esc_html__('Enter this website\'s URL, admin username and password, and the system will take care of everything.', 'worker'); ?>
                    </li>
                </ol>

                <h2><?php
                    /** @handled function */
                    echo esc_html__('Manual', 'worker'); ?>
                </h2>
                <ol>
                    <li><?php
                        /** @handled function */
                        echo wp_kses(__('Install and activate the <b>Worker</b> plugin.', 'worker'), array('b' => array())); ?>
                    </li>
                    <li><?php
                        /** @handled function */
                        echo esc_html__('Copy the connection key below.', 'worker'); ?>
                    </li>
                    <li>
                        <?php
                        /** @handled function */
                        echo esc_html__('Log into your account.', 'worker');
                        ?>
                    </li>
                    <li><?php
                        /** @handled function */
                        echo esc_html__('Click the Add site button.', 'worker'); ?>
                    </li>
                    <li><?php
                        /** @handled function */
                        echo esc_html__('Enter this website\'s URL. When prompted, paste the connection key.', 'worker'); ?>
                    </li>
                </ol>
            <?php } else {
                ?>
                <p style="margin-top: 0"><?php
                    /** @handled function */
                    echo esc_html__('Here is the list of currently active connections to this plugin:', 'worker'); ?>
                </p>

                <table style="width: 100%;">
                    <tr>
                        <th><?php
                            /** @handled function */
                            echo esc_html__('ID', 'worker'); ?>
                        </th>
                        <th><?php
                            /** @handled function */
                            echo esc_html__('Connected', 'worker'); ?>
                        </th>
                        <th><?php
                            /** @handled function */
                            echo esc_html__('Last Used', 'worker'); ?>
                        </th>
                        <th></th>
                    </tr>
                    <?php
                    $time = time();
                    foreach ($communicationKeys as $siteId => $communicationKey) { ?>
                        <tr>
                            <td><?php echo $siteId !== 'any' ? $siteId : '*'; ?></td>
                            <td><?php
                                if ($communicationKey['added'] != null) {
                                    /** @handled function */
                                    /* translators: the variable is going to contain a human time difference string like "2 days" */
                                    echo sprintf(esc_html__('%s ago', 'worker'), human_time_diff($communicationKey['added'], $time));
                                } else {
                                    /** @handled function */
                                    echo esc_html__('N/A', 'worker');
                                } ?>
                            </td>
                            <td>
                                <?php
                                $used = $this->context->optionGet('mwp_key_last_used_'.$siteId, null);
                                if (!empty($used)) {
                                    /** @handled function */
                                    /* translators: the variable is going to contain a human time difference string like "2 days" */
                                    echo sprintf(esc_html__('%s ago', 'worker'), human_time_diff($used, $time));
                                } else {
                                    /** @handled function */
                                    echo esc_html__('N/A', 'worker');
                                } ?>
                            </td>
                            <td style="text-align: right">
                                <a href="<?php echo $this->context->wpNonceUrl($this->context->getAdminUrl('plugins.php?worker_connections=1&action=mwp_deactivate_key&connection_id='.$siteId), 'mwp_deactivation_key', 'mwp_nonce'); ?>">
                                    <?php
                                    /** @handled function */
                                    echo esc_html__('Disconnect', 'worker'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                    <tr>
                        <td colspan="4" style="text-align: right">
                            <a href="<?php echo $this->context->wpNonceUrl($this->context->getAdminUrl('plugins.php?worker_connections=1&action=mwp_clear_data'), 'mwp_clear_data', 'mwp_nonce'); ?>">
                                <?php
                                /** @handled function */
                                echo esc_html__('Disconnect all', 'worker'); ?>
                            </a>
                        </td>
                    </tr>
                </table>
                <?php
            } ?>

            <p style="margin-bottom: 7px; margin-top: 27px;"><?php
                /** @handled function */
                echo esc_html__('Connection key:', 'worker'); ?>
            </p>
            <input id="connection-key" rows="1" class="key-block" onclick="this.focus();this.select()"
                   readonly="readonly" value="<?php echo mwp_get_potential_key(); ?>">
            <button class="copy-key-button btn" style="width: 76px; height: 44px;"
                    data-clipboard-target="#connection-key">
                <?php
                /** @handled function */
                echo esc_html__('Copy', 'worker'); ?>
            </button>

            <?php if ($refreshedKeys !== false) { ?>
                <p>
                    <?php
                    if ($refreshedKeys['success'] === true) {
                        echo 'Keys successfully refreshed!';
                    } else {
                        echo 'Keys were not successfully refreshed. Error: '.$refreshedKeys['message'];
                    } ?>
                </p>
                <p>
                    <?php echo 'Last communication error: '.$this->context->optionGet('mwp_last_communication_error', '') ?>
                </p>
                <p><?php
                    /** @handled function */
                    echo esc_html__('Currently loaded keys:', 'worker'); ?>
                </p>
                <pre><?php
                    if (version_compare(PHP_VERSION, '5.4', '>=') && defined('JSON_PRETTY_PRINT')) {
                        echo trim(json_encode($this->context->optionGet('mwp_public_keys', null), JSON_PRETTY_PRINT));
                    } else {
                        echo trim(json_encode($this->context->optionGet('mwp_public_keys', null)));
                    }
                    ?></pre>
                <?php
            }
            ?>
        </div>
        <?php

        $content = ob_get_clean();
        $this->context->output($content);
    }

    /**
     * @wp_filter
     */
    public function addConnectionKeyLink($meta, $slug)
    {
        if ($this->context->isMultisite() && !$this->context->isNetworkAdmin()) {
            return $meta;
        }

        if ($slug !== $this->slug) {
            return $meta;
        }

        if (!$this->userCanViewConnectionKey()) {
            return $meta;
        }

        /** @handled function */
        $meta[] = '<a href="#" id="mwp-view-connection-key" mwp-key="'.mwp_get_potential_key().'">'.esc_html__('Connection Management', 'worker').'</a>';

        return $meta;
    }

    private function userCanViewConnectionKey()
    {
        return $this->context->isGranted('activate_plugins');
    }
}
