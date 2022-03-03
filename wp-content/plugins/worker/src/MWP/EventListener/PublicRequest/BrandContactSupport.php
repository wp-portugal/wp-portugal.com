<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_PublicRequest_BrandContactSupport implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    private $brand;

    private $requestStack;

    /**
     * Required WordPress capability to access the page.
     *
     * @var string
     */
    private $capability = 'read';

    public function __construct(MWP_WordPress_Context $context, MWP_Worker_Brand $brand, MWP_Worker_RequestStack $requestStack)
    {
        $this->context      = $context;
        $this->brand        = $brand;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::PUBLIC_REQUEST => 'enableContactSupport',
        );
    }

    public function enableContactSupport()
    {
        if (!$this->brand->isActive()) {
            return;
        }

        if ($this->brand->getContactType() === MWP_Worker_Brand::CONTACT_TYPE_NONE) {
            return;
        }

        $this->context->addAction('admin_init', array($this, 'enqueueSupportScripts'));
        $this->context->addAction('admin_init', array($this, 'enqueueSupportStyles'));
        $this->context->addAction('admin_menu', array($this, 'addSupportLink'));
        $this->context->addAction('admin_head', array($this, 'printSupportScript'));
        $this->context->addAction('admin_footer', array($this, 'printSupportDialog'));

        $this->context->addAction('init', array($this, 'handleSupportForm'));
    }

    /**
     * @wp_hook admin_init
     */
    public function enqueueSupportScripts()
    {
        $this->context->enqueueScript('jquery');
        $this->context->enqueueScript('jquery-ui-core');
        $this->context->enqueueScript('jquery-ui-dialog');
    }

    /**
     * @wp_hook admin_init
     */
    public function enqueueSupportStyles()
    {
        $this->context->enqueueStyle('wp-jquery-ui');
        $this->context->enqueueStyle('wp-jquery-ui-dialog');
    }

    /**
     * @wp_hook admin_menu
     */
    public function addSupportLink()
    {
        $this->context->addMenuPage('Support', 'Support', $this->capability, 'mwp-support');
    }

    /**
     * @wp_hook admin_head
     */
    public function printSupportScript()
    {
        ob_start()
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                var $dialog = $('#mwp_support_dialog');
                var $form = $('#mwp_support_form');
                var $messageContainer = $('#mwp_support_response_id');
                $form.submit(function (e) {
                    e.preventDefault();
                    var data = $(this).serialize();
                    $.ajax({
                        type: "POST",
                        url: 'index.php',
                        dataType: 'json',
                        data: data,
                        success: function (data, textStatus, jqXHR) {
                            if (data.success) {
                                $form.slideUp();
                            }
                            $messageContainer.html(data.message);
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            $messageContainer.html('An error occurred, please try again.');
                        }
                    });
                });
                $('.toplevel_page_mwp-support').click(function (e) {
                    e.preventDefault();
                    $form.show();
                    $messageContainer.empty();
                    $dialog.dialog({
                        draggable: false,
                        resizable: false,
                        modal: true,
                        width: '530px',
                        height: 'auto',
                        title: 'Contact Support',
                        dialogClass: 'mwp-support-dialog',
                        close: function () {
                            $('#mwp_support_response_id').html('');
                            $(this).dialog("destroy");
                        }
                    });
                });
            });
        </script>
        <?php

        $content = ob_get_clean();
        $this->context->output($content);
    }

    /**
     * @wp_hook admin_footer
     */
    public function printSupportDialog()
    {
        $contactText = $this->brand->getTextForClient();
        $contactType = $this->brand->getContactType();

        ob_start();
        ?>
        <div id="mwp_support_dialog" style="display: none;">
            <?php if (!empty($contactText)): ?>
                <div>
                    <p><?php echo $contactText ?></p>
                </div>
            <?php endif ?>
            <?php if ($contactType == MWP_Worker_Brand::CONTACT_TYPE_TEXT_PLUS_FORM): ?>
                <div style="margin: 19px 0 0;">
                    <form method="post" id="mwp_support_form">
                        <textarea name="support_mwp_message" id="mwp_support_message" style="width:500px;height:150px;display:block;margin-left:auto;margin-right:auto;"></textarea>
                        <button type="submit" class="button-primary" style="display:block;margin:20px auto 7px auto;border:1px solid #a1a1a1;padding:0 31px;border-radius: 4px;">Send</button>
                    </form>
                    <div id="mwp_support_response_id" style="margin-top: 14px"></div>
                    <style scoped="scoped">
                        .mwp-support-dialog.ui-dialog {
                            z-index: 300002;
                        }
                    </style>
                </div>
            <?php endif ?>
        </div>
        <?php

        $content = ob_get_clean();
        $this->context->output($content);
    }

    public function handleSupportForm()
    {
        $request = $this->requestStack->getMasterRequest();
        if (!isset($request->request['support_mwp_message']) || !is_scalar($request->request['support_mwp_message'])) {
            return;
        }

        if (!$this->context->isGranted($this->capability)) {
            return;
        }

        if ($this->brand->getContactType() !== MWP_Worker_Brand::CONTACT_TYPE_TEXT_PLUS_FORM) {
            return;
        }

        $message     = (string) $request->request['support_mwp_message'];
        $currentUser = $this->context->getCurrentUser();

        if (empty($message)) {
            // Message is set, but it's empty.
            $response = new MWP_Http_JsonResponse(array(
                'success' => false,
                'message' => "Please enter a message.",
            ));
            $response->send();
            exit;
        }
        $subject   = 'New ticket for site '.$this->context->getHomeUrl();
        $message   = <<<EOF
Hi,
User {$currentUser->user_login} has sent a support ticket:

{$message}
EOF;
        $emailSent = $this->context->sendMail($this->brand->getAdminEmail(), $subject, $message);
        $status    = array(
            'success' => $emailSent,
            'message' => $emailSent ? "Message successfully sent." : "Unable to send email. Please try again.",
        );

        $response = new MWP_Http_JsonResponse($status);
        $response->send();
        exit;
    }
}
