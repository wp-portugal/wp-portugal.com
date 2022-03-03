<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_WordPress_Provider_Theme implements MWP_WordPress_Provider_Interface
{
    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    const STATUS_INHERITED = 'inherited';

    private $context;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    public function fetch(array $options = array())
    {
        $rawThemes       = $this->context->getThemes();
        $currentTheme    = $this->context->getCurrentTheme();
        $activeThemeSlug = $currentTheme['Stylesheet'];
        $themes          = array();

        $themeInfo = array(
            'name'    => 'Name',
            // Absolute path to theme directory.
            'root'    => 'Theme Root',
            // Absolute URL to theme directory.
            'rootUri' => 'Theme Root URI',

            'version'     => 'Version',
            'description' => 'Description',
            'author'      => 'Author',
            'authorUri'   => 'Author URI',
            'status'      => 'Status',
            'parent'      => 'Parent Theme',
        );

        if (empty($options['fetchDescription'])) {
            unset($themeInfo['description']);
        }

        foreach ($rawThemes as $rawTheme) {
            $theme = array(
                // Theme directory, followed by slash and slug, to keep it consistent with plugin info; ie. "twentytwelve/twentytwelve".
                'basename' => $rawTheme['Template'].'/'.$rawTheme['Stylesheet'],
                // A.k.a. "stylesheet", for some reason. This is the theme identifier; ie. "twentytwelve".
                'slug'     => $rawTheme['Stylesheet'],
                'children' => array(),
            );

            foreach ($themeInfo as $property => $info) {
                if (empty($rawTheme[$info])) {
                    $theme[$property] = null;
                    continue;
                }

                $theme[$property] = $this->context->seemsUtf8($rawTheme[$info]) ? $rawTheme[$info] : utf8_encode($rawTheme[$info]);
            }

            // Check if this is the active theme
            $theme['active'] = ($theme['slug'] === $activeThemeSlug);

            $themes[$theme['name']] = $theme;
        }

        foreach ($themes as &$theme) {
            if (empty($theme['parent']) || empty($themes[$theme['parent']])) {
                continue;
            }

            $themes[$theme['parent']]['children'][] = $theme['slug'];
            $theme['parent']                        = $themes[$theme['parent']]['slug'];
        }

        return array_values($themes);
    }
}

