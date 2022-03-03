<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Class MWP_Configuration_Conf
 *
 * @package src\MWP\Configuration
 */
class MWP_Configuration_Conf
{
    /**
     * @var string
     */
    protected $deactivate_text;

    /**
     * @var string
     */
    protected $master_url;

    /**
     * @var string
     */
    protected $master_cron_url;

    /**
     * @var int
     */
    protected $noti_cache_life_time = 0;

    /**
     * @var int
     */
    protected $noti_treshold_spam_comments = 0;

    /**
     * @var int
     */
    protected $noti_treshold_pending_comments = 0;

    /**
     * @var int
     */
    protected $noti_treshold_approved_comments = 0;

    /**
     * @var int
     */
    protected $noti_treshold_posts = 0;

    /**
     * @var int
     */
    protected $noti_treshold_drafts = 0;
    /**
     * @var string
     */
    protected $key_name;

    /**
     * @param array $data
     */
    public function __construct($data = array())
    {
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * Convert object to array. Much more convenient for wordpress serialization
     *
     * @return array
     */
    public function toArray()
    {
        $vars   = get_class_vars(get_class($this));
        $return = array();
        foreach ($vars as $key => $value) {
            $return[$key] = $this->$key;
        }

        return $return;
    }

    /**
     * We will use this function to notify server which fields and order to use in diff calculation
     * @return array
     */
    public function getVariables()
    {
        $vars = get_class_vars(get_class($this));

        return $vars;
    }

    /**
     * @param string $key_name
     */
    public function setKeyName($key_name)
    {
        $this->key_name = $key_name;
    }

    /**
     * @return string
     */
    public function getKeyName()
    {
        return $this->key_name;
    }

    /**
     * @param string $deactivate_text
     */
    public function setDeactivateText($deactivate_text)
    {
        $this->deactivate_text = $deactivate_text;
    }

    /**
     * @return string
     */
    public function getDeactivateText()
    {
        return $this->deactivate_text;
    }

    /**
     * @return string
     */
    public function getNetworkNotice()
    {
        return $this->getNoticeHtml('Add this website to <a href="https://managewp.com" target="_blank">ManageWP</a> or <a href="https://godaddy.com/pro" target="_blank">GoDaddy Pro</a> dashboard to enable backups, uptime monitoring, website cleanup and a lot more! Use a <strong>network administrator</strong> account when adding the website.<br>If prompted, provide the <strong>connection key</strong> (found on the Plugins page, in the Worker description under Connection Management) to verify that you are the website administrator.');
    }

    /**
     * @param mixed $master_cron_url
     */
    public function setMasterCronUrl($master_cron_url)
    {
        $this->master_cron_url = $master_cron_url;
    }

    /**
     * @return mixed
     */
    public function getMasterCronUrl()
    {
        return $this->master_cron_url;
    }

    /**
     * @param mixed $master_url
     */
    public function setMasterUrl($master_url)
    {
        $this->master_url = $master_url;
    }

    /**
     * @return mixed
     */
    public function getMasterUrl()
    {
        return $this->master_url;
    }

    /**
     * @param mixed $noti_cache_life_time
     */
    public function setNotiCacheLifeTime($noti_cache_life_time)
    {
        $this->noti_cache_life_time = $noti_cache_life_time;
    }

    /**
     * @return mixed
     */
    public function getNotiCacheLifeTime()
    {
        return $this->noti_cache_life_time;
    }

    /**
     * @param mixed $noti_treshold_approved_comments
     */
    public function setNotiTresholdApprovedComments($noti_treshold_approved_comments)
    {
        $this->noti_treshold_approved_comments = $noti_treshold_approved_comments;
    }

    /**
     * @return mixed
     */
    public function getNotiTresholdApprovedComments()
    {
        return $this->noti_treshold_approved_comments;
    }

    /**
     * @param mixed $noti_treshold_drafts
     */
    public function setNotiTresholdDrafts($noti_treshold_drafts)
    {
        $this->noti_treshold_drafts = $noti_treshold_drafts;
    }

    /**
     * @return mixed
     */
    public function getNotiTresholdDrafts()
    {
        return $this->noti_treshold_drafts;
    }

    /**
     * @param mixed $noti_treshold_pending_comments
     */
    public function setNotiTresholdPendingComments($noti_treshold_pending_comments)
    {
        $this->noti_treshold_pending_comments = $noti_treshold_pending_comments;
    }

    /**
     * @return mixed
     */
    public function getNotiTresholdPendingComments()
    {
        return $this->noti_treshold_pending_comments;
    }

    /**
     * @param mixed $noti_treshold_posts
     */
    public function setNotiTresholdPosts($noti_treshold_posts)
    {
        $this->noti_treshold_posts = $noti_treshold_posts;
    }

    /**
     * @return mixed
     */
    public function getNotiTresholdPosts()
    {
        return $this->noti_treshold_posts;
    }

    /**
     * @param mixed $noti_treshold_spam_comments
     */
    public function setNotiTresholdSpamComments($noti_treshold_spam_comments)
    {
        $this->noti_treshold_spam_comments = $noti_treshold_spam_comments;
    }

    /**
     * @return mixed
     */
    public function getNotiTresholdSpamComments()
    {
        return $this->noti_treshold_spam_comments;
    }

    public function getNotice()
    {
        return $this->getNoticeHtml($this->getDefaultNoticeText());
    }

    private function getDefaultNoticeText()
    {
        return 'Add this website to <a href="https://managewp.com" target="_blank">ManageWP</a> or <a href="https://godaddy.com/pro" target="_blank">GoDaddy Pro</a> dashboard to enable backups, uptime monitoring, website cleanup and a lot more!<br>If prompted, provide the <strong>connection key</strong> (found on the Plugins page, in the Worker description under Connection Management) to verify that you are the website administrator.';
    }

    private function getNoticeHtml($message)
    {
        return <<<HTML
<div class="updated" style="padding: 0; margin: 0; border: none; background: none;">
    <style scoped type="text/css">
        .mwp-notice-container {
            box-shadow: 1px 1px 1px #d9d9d9;
            max-width: 100%;
            border-radius: 4px;
        }

        .mwp-notice {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            min-height: 116px;
            margin: 15px 0;
        }

        .mwp-notice-left {
            width: 132px;
            border-radius: 4px 0 0 4px;
            background: url('data:image/png;base64,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') #ffffff no-repeat center center;
            background-size: 80px 80px;
        }

        .mwp-notice-middle {
            padding: 21px 5px 19px;
            background: #fff;
        }

        .mwp-notice-middle h3 {
            font-size: 22px;
            line-height: 22px;
            font-weight: bold;
            margin: 0 0 10px;
        }

        .mwp-notice-middle p {
            margin: 10px 0 0;
            font-size: 16px;
            line-height: 24px;
        }

        .mwp-notice-right {
            background: #fff;
            width: 178px;
            padding: 26px;
            vertical-align: middle;
            border-radius: 0 4px 4px 0;
        }

        .mwp-notice-button {
            display: block;
            text-decoration: none !important;
            background: #75B214;
            text-align: center;
            vertical-align: bottom;
            white-space: nowrap;
            outline: 0 none !important;
            overflow: visible;
            box-sizing: border-box;
            color: #FFFFFF !important;
            border: 1px solid transparent;
            cursor: pointer;
            border-radius: 4px;
            margin: 0;
            -webkit-transition: background-color 0.1s, border-color 0.2s, color 0.1s;
            transition: background-color 0.1s, border-color 0.2s, color 0.1s;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            font-size: 16px;
            padding: 8px 16px 10px !important;
            line-height: 22px;
            font-family: "Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            box-shadow: 0 -2px 0 0 rgba(50, 53, 57, 0.25) inset;
            border-bottom-color: rgba(50, 53, 57, 0.25);
        }
        
        .mwp-notice-button:hover {
            background-color: #609905;
        }

    </style>

    <div class="mwp-notice-container">
        <table class="mwp-notice">
            <tr>
                <td class="mwp-notice-left">
                </td>
                <td class="mwp-notice-middle">
                    <h3>You&#8217;re almost there&hellip;</h3>

                    <p>{$message}</p>
                </td>
                <td class="mwp-notice-right">
                    <a class="mwp-notice-button" target="_blank" href="https://managewp.com/features">Learn more</a>
                </td>
            </tr>
        </table>
    </div>
</div>
HTML;
    }
}
