<?php
/**
 * Thumbnail helper for CakePHP.
 *
 * @author  Martin Bean <martin@martinbean.co.uk>
 * @version 1.0
 */

App::uses('AppHelper', 'View/Helper');

/**
 * The thumbnail helper class.
 */
class ThumbnailHelper extends AppHelper
{
    /**
     * Helpers used within this helper.
     */
    public $helpers = array('Html');
    
    /**
     * Path to source images.
     *
     * @var string
     */
    private $files_dir = '';
    
    /**
     * Path for saved thumbnail images.
     *
     * @var string
     */
    private $thumbs_dir = '';
    
    /**
     * Width of thumbnail image.
     *
     * @var integer
     */
    private $width;
    
    /**
     * Height of thumbnail image.
     *
     * @var integer
     */
    private $height;
    
    /**
     * Renders a thumbnail image.
     *
     * @param  string $filename
     * @param  array  $options
     * @param  array  $imgOptions
     * @return string
     */
    public function render($source_path, $filename, $options = array(), $imgOptions = array())
    {
        $this->width = isset($options['width']) ? intval($options['width']) : 100;
        $this->height = isset($options['height']) ? intval($options['height']) : 75;
        
        if (!is_file(Configure::read('Assets.base_path') . DS . $source_path . DS . $this->width . 'x' . $this->height . DS . $filename)) {
            list($width, $height) = getimagesize(Configure::read('Assets.base_path').$source_path . DS . $filename);
            $canvas = imagecreatetruecolor($this->width, $this->height);
            imagealphablending($canvas, true);
            $trans = imagecolorallocatealpha($canvas, 255, 255, 255, 0);
            imagefill($canvas, 0, 0, $trans);
            $extension = strtolower(strrchr($filename, '.'));
            switch ($extension) {
                case '.jpg':
                    $image = imagecreatefromjpeg(Configure::read('Assets.base_path') . $source_path.  DS . $filename);
                    break;
                case '.gif':
                    $image = imagecreatefromgif(Configure::read('Assets.base_path') . $source_path . DS . $filename);
                    $transparent_index = imagecolortransparent($image);
                    break;
                case '.png':
                    $image = imagecreatefrompng(Configure::read('Assets.base_path') . $source_path . DS . $filename);
                    break;
                default:
                    $image = false;
                    // no break
            }

            imagealphablending($canvas, true);
            imagecopyresized($canvas, $image, 0, 0, 0, 0, $this->width, $this->height, $width, $height);
            
            if (!is_dir(Configure::read('Assets.base_path') . DS . $source_path . DS . $this->width . 'x' . $this->height)) {
                mkdir(Configure::read('Assets.base_path') . DS . $source_path . DS . $this->width . 'x' . $this->height);
            }
            
            imagejpeg($canvas, Configure::read('Assets.base_path') . DS . $source_path . DS . $this->width . 'x' . $this->height . '/' . $filename, 100);
        }
        
        return str_replace(DS, '/', $this->Html->image(
            Configure::read('Assets.base_url') . DS . $source_path . DS . $this->width . 'x' . $this->height . DS . $filename,
            $imgOptions)
        );
    }
}
