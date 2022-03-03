<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_WordPress_Query_Post extends MWP_WordPress_Query_Abstract
{

    public function query(array $options = array())
    {
        $options += array(
            'query'               => null,
            'prefixName'          => '{{prefix}}',
            'deserialize'         => array(),
            'tryDeserialize'      => array(),
            'featuredImage'       => 'thumbnail',
            'featuredImageHeight' => null,
            'featuredImageWidth'  => null,
            'featuredImageCrop'   => false,
        );

        $query = str_replace($options['prefixName'], $this->getDb()->prefix, $options['query']);
        $posts = $this->getDb()->get_results($query, ARRAY_A);
        $this->deserialize($posts, $options['deserialize'], $options['tryDeserialize']);
        $this->fillFeaturedImages($posts, $options['featuredImage'], $options['featuredImageHeight'], $options['featuredImageWidth'], $options['featuredImageCrop']);

        return $posts;
    }

    private function fillFeaturedImages(&$posts, $imageStyle, $imageHeight, $imageWidth, $crop)
    {
        if (count($posts) === 0 || !$imageStyle) {
            return;
        }

        if ($imageHeight || $imageWidth) {
            $this->context->addImageStyle($imageStyle, $imageWidth, $imageHeight, $crop);
        }

        foreach ($posts as &$post) {
            if (empty($post['featuredImageId'])) {
                continue;
            }
            $info = $this->context->getImageInfo($post['featuredImageId'], $imageStyle);
            if ($info === null) {
                continue;
            }
            $post['featuredImage']         = $info['url'];
            $post['featuredImageHeight']   = $info['height'];
            $post['featuredImageWidth']    = $info['width'];
            $post['featuredImageOriginal'] = $info['original'];
        }
    }
}
