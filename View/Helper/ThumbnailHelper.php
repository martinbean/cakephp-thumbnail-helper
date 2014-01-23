<?php

/**
 * Thumbnail helper for CakePHP.
 *
 * @author Martin Bean <martin@martinbean.co.uk>
 * @author Jamie Hurst <jamie@jamiehurst.co.uk>
 * @version 1.1
 */

// Use the AppHelper as the parent class.
App::uses('AppHelper', 'View/Helper');

/**
 * The thumbnail helper class.
 */
class ThumbnailHelper extends AppHelper
{
    /**
     * Store configuration options for use within the helper.
     * @var array
     */
    public $config = array(
        'default_background' => array(255, 255, 255),
        'default_height' => 75,
        'default_image' => 'http://placehold.it/%1$dx%2$d', // Placeholders for width/height
        'default_width' => 100,
        'jpeg_only' => false,
    );

    /**
     * Path for saved thumbnail images.
     * @var string
     */
    private $destination = '';

    /**
     * Height of thumbnail image.
     * @var integer
     */
    private $height;

    /**
     * Helpers used within this helper.
     * @var array
     */
    public $helpers = array('Html');

    /**
     * Store the path to the source images.
     * @var string
     */
    public $source = '';

    /**
     * Width of thumbnail image.
     * @var integer
     */
    private $width;

    /**
     * Return the default image, if it couldn't render our choice.
     *
     * @param array $options
     * @param array $img_options
     * @return string
     */
    private function defaultImage($options = array(), $img_options = array())
    {
        if (isset($options['default_image'])) {
            return $this->Html->image($options['default_image'], $img_options);
        }

        // Handle the default image
        $image = sprintf($this->config['default_image'], $this->width, $this->height);

        return $this->Html->image($image, $img_options);
    }

    /**
     * Make sure the path is prepended correctly.
     *
     * @param $path
     * @return string
     */
    private function preparePathForUrl($path)
    {
        try {
            $path = str_replace('//', '/', str_replace(Configure::read('Assets.base_path'), '', $path));
            $path = Configure::read('Assets.base_url') . DS . $path;
        } catch (ConfigureException $e) {}

        // Change the DS
        return str_replace(DS, '/', $path);
    }

    /**
     * A helper function for changing the source path.
     *
     * @param $source_path
     * @return string
     */
    private function prepareSourcePath($source_path)
    {
        try {
            $source_path = Configure::read('Assets.base_path') . DS . $source_path;
        } catch (ConfigureException $e) {}

        // Append the DS
        if (substr($source_path, -1) != DS) {
            $source_path .= DS;
        }

        return str_replace(DS . DS, DS, $source_path);
    }

    /**
     * Renders a thumbnail image.
     *
     * @param string $source_path
     * @param string $filename
     * @param array $options
     * @param array $img_options
     * @return string
     */
    public function render($source_path, $filename, $options = array(), $img_options = array())
    {
        // Set the width and height if provided
        $this->width = isset($options['width']) ? intval($options['width']) : $this->config['default_width'];
        $this->height = isset($options['height']) ? intval($options['height']) : $this->config['default_height'];

        // Prepare the source path
        if (empty($source_path)) {
            $source_path = $this->source;
        }
        $source_path = $this->prepareSourcePath($source_path);
        $thumbs_path = $this->destination;
        if (empty($thumbs_path)) {
            $thumbs_path = $source_path;
        }

        // Grab the extension
        if (is_null($filename) || empty($filename)) {
            return $this->defaultImage($options, $img_options);
        }

        // Check the file exists
        if (!file_exists($source_path . $filename) || !is_file($source_path . $filename)) {
            return $this->defaultImage($options, $img_options);
        }

        // Automatic widths/heights
        list($width, $height) = getimagesize($source_path . $filename);
        $ratio = $width / $height;
        if ($this->width == 'auto') {
            $this->width = floor($this->height * $ratio);
        } elseif ($this->height == 'auto') {
            $this->height = floor($this->width / $ratio);
        }
        $thumbnail_ratio = $this->width / $this->height;

        // Create thumbnail if unavailable
        if (!is_file($thumbs_path . $this->width . 'x' . $this->height . DS . $filename)) {

            // Check widths/heights against original (don't create thumbnail if unnecessary)
            if ($width == $this->width && $height == $this->height) {
                $img_options['src'] = $this->preparePathForUrl($source_path) . DS . $filename;

                return $this->Html->tag('img', null, $img_options);
            }

            // Create new canvas (using configurable background)
            $canvas = imagecreatetruecolor($this->width, $this->height);
            imagealphablending($canvas, true);
            imagefill($canvas, 0, 0, call_user_func_array('imagecolorallocatealpha', array_merge(array($canvas), $this->config['default_background'], array(0))));

            // Get path info
            $pathinfo = pathinfo($source_path . $filename);

            // Open the file correctly
            if (strtolower($pathinfo['extension']) == 'jpg' || strtolower($pathinfo['extension']) == 'jpeg') {
                $image = imagecreatefromjpeg($source_path . $filename);
            } elseif (strtolower($pathinfo['extension']) == 'png') {
                $image = imagecreatefrompng($source_path . $filename);
            } elseif (strtolower($pathinfo['extension']) == 'gif') {
                $image = imagecreatefromgif($source_path . $filename);
            } else {
                // What are we dealing with? Use the default
                return $this->defaultImage($options, $img_options);
            }

            // Make the image smaller, taking into account the ratio (option to preserve or use correct ratio)
            if ((isset($options['preserve_ratio']) && !$options['preserve_ratio']) || $ratio == $thumbnail_ratio) {

                imagecopyresampled($canvas, $image, 0, 0, 0, 0, $this->width, $this->height, $width, $height);

            } else {

                // Check for larger image
                if ($this->width > $width || $this->height > $height) {

                    imagecopyresampled($canvas, $image, (($this->width) - $width) / 2, (($this->height) - $height) / 2, 0, 0, $width, $height, $width, $height);

                } elseif ($thumbnail_ratio != $ratio) {
                    // Larger ratio means its higher, shorter means its wider
                    if ($thumbnail_ratio < $ratio) {
                        // Shorten by height
                        $correct_height = $this->width / $ratio;
                        imagecopyresampled($canvas, $image, 0, floor(($this->height - $correct_height) / 2), 0, 0, $this->width, $correct_height, $width, $height);
                    } else {
                        // Shorten by width
                        $correct_width = $this->height * $ratio;
                        imagecopyresampled($canvas, $image, floor(($this->width - $correct_width) / 2), 0, 0, 0, $correct_width, $this->height, $width, $height);
                    }
                }
            }

            // Handle directories
            if (!is_dir($thumbs_path . $this->width . 'x' . $this->height)) {
                mkdir($thumbs_path . $this->width . 'x' . $this->height, 0777, true);
            }
            if (isset($pathinfo['dirname']) && $pathinfo['dirname']) {
                if (!is_dir($thumbs_path . $this->width . 'x' . $this->height . DS . $pathinfo['dirname'])) {
                    mkdir($thumbs_path . $this->width . 'x' . $this->height . DS . $pathinfo['dirname'], 0777, true);
                }
            }

            // Handle the extensions again
            if ($this->config['jpeg_only'] || strtolower($pathinfo['extension']) == 'jpg' || strtolower($pathinfo['extension']) == 'jpeg') {
                imagejpeg($canvas, $thumbs_path . $this->width . 'x' . $this->height . '/' . $filename, 100);
            } elseif (strtolower($pathinfo['extension']) == 'png') {
                imagepng($canvas, $thumbs_path . $this->width . 'x' . $this->height . '/' . $filename);
            } elseif (strtolower($pathinfo['extension']) == 'gif') {
                imagegif($canvas, $thumbs_path . $this->width . 'x' . $this->height . '/' . $filename);
            }
        }

        // Formulate a URL and return
        $img_options['src'] = $this->preparePathForUrl($thumbs_path) . $this->width . 'x' . $this->height . DS . $filename;

        return $this->Html->tag('img', null, $img_options);
    }
}
