<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_GetComponentsStats extends MWP_Action_Abstract
{
    /** @var wpdb */
    private $wpdb;

    public function execute(array $params = array())
    {
        $this->wpdb = $this->container->getWordPressContext()->getDb();

        if ($this->container->getWordPressContext()->isMultisite()) {
            return $this->getNetworkStats($params);
        }

        $options = $this->getOptions($params);

        $stateAction = new MWP_Action_GetState();
        $stateAction->setContainer($this->container);
        $stateData = $stateAction->execute($options);
        $siteUrl   = $this->container->getWordPressContext()->getSiteUrl();

        return array(
            'multisite' => false,
            'data'      => array(
                $siteUrl => $stateData
            )
        );
    }

    private function getNetworkStats(array $params = array())
    {
        $network_blogs = $this->wpdb->get_results("select `blog_id`, `site_id`, `domain`, `path` from `{$this->wpdb->blogs}`");
        if (empty($network_blogs)) {
            return array(
                'multisite' => true,
                'data'      => array()
            );
        }

        $data            = array();
        $options         = $this->getOptions($params);
        /** @handled function */
        $current_blog_id = get_current_blog_id();

        foreach ($network_blogs as $network_blog) {
            /** @handled function */
            switch_to_blog($network_blog->blog_id);

            $stateAction = new MWP_Action_GetState();
            $stateAction->setContainer($this->container);

            $network_blog_url = rtrim($network_blog->domain, '/').'/'.trim($network_blog->path, '/');

            $data[$network_blog_url] = $stateAction->execute($options);
        }

        /** @handled function */
        switch_to_blog($current_blog_id);

        return array(
            'multisite' => true,
            'data'      => $data
        );
    }

    private function getOptions(array $params = array())
    {
        $options = array();

        if (isset($params['getPluginStats']) && $params['getPluginStats'] === true) {
            $options['plugins'] = array(
                'type'    => 'plugins',
                'options' => array()
            );
        }

        if (isset($params['getThemeStats']) && $params['getThemeStats'] === true) {
            $options['themes'] = array(
                'type'    => 'themes',
                'options' => array()
            );
        }

        return $options;
    }
}
