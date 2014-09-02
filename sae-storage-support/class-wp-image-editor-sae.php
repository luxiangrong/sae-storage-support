<?php
require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';

/**
 * WordPress Image Editor Class for Image Manipulation through SAE Image
 *
 * @since 3.5.0
 * @package WordPress
 * @subpackage Image_Editor
 * @uses WP_Image_Editor Extends class
 */
class WP_Image_Editor_SAEImage extends WP_Image_Editor {
	protected $saeImage;
	protected $image;
	
	function __construct($file) {
		parent::__construct(sae_covert_to_wrapper($file));
		$this->saeImage = new SaeImage();
	}
	
	function __destruct() {
		if ( $this->image ) {
			$this->saeImage->clean();
		}
	}
	
	/**
	 * 检测当前环境是否支持SAE Image API
	 *
	 * @access public
	 *
	 * @return boolean
	 */
	public static function test( $args = array() ) {
		if(class_exists('SaeImage'))
			return true;
		return false;
	}
	
	/**
	 * 检测当前环境是否支持的mime_type类型
	 *
	 * @access public
	 *
	 * @param string $mime_type
	 * @return boolean
	 */
	public static function supports_mime_type( $mime_type ) {
		switch( $mime_type ) {
			case 'image/jpeg':
				return true;
			case 'image/png':
				return true;
			case 'image/gif':
				return true;
		}
		return false;
	}
	
	/**
	 * 从$this->file载入图片数据
	 * 
	 * @access public
	 *
	 * @return boolean|WP_Error True if loaded successfully; WP_Error on failure.
	 */
	public function load() {
		if ( $this->image )
			return true;
		
		if ( ! is_file( $this->file ) && ! preg_match( '|^https?://|', $this->file ) )
			return new WP_Error( 'error_loading_image', __('File doesn&#8217;t exist?'), $this->file );
	
		/**
		 * Filter the memory limit allocated for image manipulation.
		 *
		 * @since 3.5.0
		 *
		 * @param int|string $limit Maximum memory limit to allocate for images. Default WP_MAX_MEMORY_LIMIT.
		 *                          Accepts an integer (bytes), or a shorthand string notation, such as '256M'.
		*/
		// Set artificially high because GD uses uncompressed images in memory
		@ini_set( 'memory_limit', apply_filters( 'image_memory_limit', WP_MAX_MEMORY_LIMIT ) );

		$this->image = file_get_contents( $this->file );
		$this->saeImage->setData($this->image);
	
		$imgAttr = $this->saeImage->getImageAttr();
		$size = array (
			'width' => $imgAttr [0],
			'height' => $imgAttr [1]
		);
		if ( ! $size )
			return new WP_Error( 'invalid_image', __('Could not read image size.'), $this->file );
	
		$this->update_size( $size[0], $size[1] );
		$this->mime_type = $imgAttr['mime'];
	
		return $this->set_quality( $this->quality );
	}
	
	/**
	 * 设置或者更新当前图片的尺寸
	 *
	 * @since 3.5.0
	 * @access protected
	 *
	 * @param int $width
	 * @param int $height
	 */
	protected function update_size( $width = false, $height = false ) {
		$this->saeImage->clean();
		$this->saeImage->setData($this->image);
		$imgAttr = $this->saeImage->getImageAttr();
		$size = array (
			'width' => $imgAttr [0],
			'height' => $imgAttr [1]
		);
		if ( ! $width )
			$width = $size['width'];
	
		if ( ! $height )
			$height = $size['height'];
	
		return parent::update_size( $width, $height );
	}
	
	/**
	 * Resizes current image.
	 * Wraps _resize, since _resize returns a GD Resource.
	 *
	 * At minimum, either a height or width must be provided.
	 * If one of the two is set to null, the resize will
	 * maintain aspect ratio according to the provided dimension.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param  int|null $max_w Image width.
	 * @param  int|null $max_h Image height.
	 * @param  boolean  $crop
	 * @return boolean|WP_Error
	 */
	public function resize( $max_w, $max_h, $crop = false ) {
		if ( ( $this->size['width'] == $max_w ) && ( $this->size['height'] == $max_h ) )
			return true;
	
		$resized = $this->_resize( $max_w, $max_h, $crop );
	
		if($resized != false) {
			$this->saeImage->clean();
			$this->image = $resized;
			return true;
		} elseif ( is_wp_error( $resized ) ) {
			return $resized;
		}
		
		return new WP_Error( 'image_resize_error', __('Image resize failed.'), $this->file );
	}
	
	protected function _resize( $max_w, $max_h, $crop = false ) {
		$orig_size = $this->size;
		$dims = image_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );
		if ( ! $dims ) {
			return new WP_Error( 'error_getting_dimensions', __('Could not calculate resized image dimensions'), $this->file );
		}
		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;
		
		$lx = $src_x / $orig_size['width'];
		$rx = ($src_x + $src_w) / $orig_size['width'];
		$ty = $src_y / $orig_size['height'];
		$by = ($src_y + $src_h) / $orig_size['height'];
		 
		$this->saeImage->setData($this->image);
		$this->saeImage->crop($lx, $rx, $ty, $by);
		$this->saeImage->resize($dst_w, $dst_h);
		$resized = $this->saeImage->exec();
		
		if($resized != false) {
			$this->update_size( $dst_w, $dst_h );
			return $resized;
		}
		
		return new WP_Error( 'image_resize_error', __('Image resize failed.'), $this->file );
	
	}
	
	/**
	 * Resize multiple images from a single source.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param array $sizes {
	 *     An array of image size arrays. Default sizes are 'small', 'medium', 'large'.
	 *
	 *     Either a height or width must be provided.
	 *     If one of the two is set to null, the resize will
	 *     maintain aspect ratio according to the provided dimension.
	 *
	 *     @type array $size {
	 *         @type int  ['width']  Optional. Image width.
	 *         @type int  ['height'] Optional. Image height.
	 *         @type bool ['crop']   Optional. Whether to crop the image. Default false.
	 *     }
	 * }
	 * @return array An array of resized images' metadata by size.
	 */
	public function multi_resize( $sizes ) {
		$metadata = array();
		$orig_size = $this->size;
	
		foreach ( $sizes as $size => $size_data ) {
			if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
				continue;
			}
	
			if ( ! isset( $size_data['width'] ) ) {
				$size_data['width'] = null;
			}
			if ( ! isset( $size_data['height'] ) ) {
				$size_data['height'] = null;
			}
	
			if ( ! isset( $size_data['crop'] ) ) {
				$size_data['crop'] = false;
			}

			$image = $this->_resize( $size_data['width'], $size_data['height'], $size_data['crop'] );
	
			if( ! is_wp_error( $image ) ) {
				$resized = $this->_save( $image );
	
				$this->saeImage->clean();
	
				if ( ! is_wp_error( $resized ) && $resized ) {
					unset( $resized['path'] );
					$metadata[$size] = $resized;
				}
			}
	
			$this->size = $orig_size;
		}
	
		return $metadata;
	}
	
	/**
	 * Crops Image.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param string|int $src The source file or Attachment ID.
	 * @param int $src_x The start x position to crop from.
	 * @param int $src_y The start y position to crop from.
	 * @param int $src_w The width to crop.
	 * @param int $src_h The height to crop.
	 * @param int $dst_w Optional. The destination width.
	 * @param int $dst_h Optional. The destination height.
	 * @param boolean $src_abs Optional. If the source crop points are absolute.
	 * @return boolean|WP_Error
	 */
	public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
	    $orig_size = $this->size;
	     
	    // If destination width/height isn't specified, use same as
	    // width/height from source.
	    if ( ! $dst_w )
	        $dst_w = $src_w;
	    if ( ! $dst_h )
	        $dst_h = $src_h;
	     
	    if ( $src_abs ) {
	        $src_w -= $src_x;
	        $src_h -= $src_y;
	    }
	     
	    $lx = $src_x / $orig_size['width'];
	    $rx = ($src_x + $src_w) / $orig_size['width'];
	    $ty = $src_y / $orig_size['height'];
	    $by = ($src_y + $src_h) / $orig_size['height'];
	     
	    $this->saeImage->setData($this->image);
	    $this->saeImage->crop($lx, $rx, $ty, $by);
	    $this->saeImage->resize($dst_w, $dst_h);
	
	    $resized = $this->saeImage->exec();
	     
	    $this->size = array (
            'width' => $dst_w,
            'height' => $dst_h
	    );
	    
	    if($resized == false) {
	        return new WP_Error( 'image_crop_error', __('Image crop failed.'), $this->file );
	    } else {
	        $this->saeImage->clean();
	        $this->image = $resized;
	        $this->update_size();
	         
	        return true;
	    }
	}
	
	/**
	 * Rotates current image counter-clockwise by $angle.
	 * Ported from image-edit.php
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param float $angle
	 * @return boolean|WP_Error
	 */
	public function rotate( $angle ) {
	    $this->saeImage->setData($this->image);
	    $this->saeImage->rotate (-$angle);
	    
	    $rotated = $this->saeImage->exec();
	    
	    if($rotated == false ) {
	        return new WP_Error( 'image_rotate_error', __('Image rotate failed.'), $this->file );
	    } else {
	        $this->saeImage->clean();
	        $this->image = $rotated;
	        $this->update_size();
	        return true;
	    }
	}
	
	/**
	 * Flips current image.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param boolean $horz Flip along Horizontal Axis
	 * @param boolean $vert Flip along Vertical Axis
	 * @returns boolean|WP_Error
	 */
	public function flip( $horz, $vert ) {
	    $this->saeImage->setData($this->image);
	    if($horz) {
	        $this->saeImage->flipV();
	    }
	    if($vert) {
	        $this->saeImage->flipH();
	    }
	    
	    $fliped = $this->saeImage->exec();
	    
	    if($fliped == false ) {
	        return new WP_Error( 'image_flip_error', __('Image flip failed.'), $this->file );
	    } else {
	        $this->saeImage->clean();
	        $this->image = $fliped;
	        $this->update_size();
	        return true;
	    }
	}
	
	/**
	 * Saves current in-memory image to file.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param string $destfilename
	 * @param string $mime_type
	 * @return array|WP_Error {'path'=>string, 'file'=>string, 'width'=>int, 'height'=>int, 'mime-type'=>string}
	 */
	public function save( $filename = null, $mime_type = null ) {
		$saved = $this->_save( $this->image, $filename, $mime_type );
	
		if ( ! is_wp_error( $saved ) ) {
			$this->file = $saved['path'];
			$this->mime_type = $saved['mime-type'];
		}
	
		return $saved;
	}
	
	protected function _save( $image, $filename = null, $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );
	
		if ( ! $filename )
			$filename = $this->generate_filename( null, null, $extension );

		$domain = sae_storage_domain_name();
		
		if(strpos($filename, "saestor://$domain/", 0) === 0) {
		    $result = file_put_contents($filename, $image);
		} else {
		    $result = file_put_contents("saestor://$domain/". $filename, $image);
		}

		if($result === FALSE) {
		    return new WP_Error( 'image_save_error', __('Image Editor Save Failed') );
		} else {
		    return array(
				'path'      => "saestor://$domain/". $filename,
				'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
				'width'     => $this->size['width'],
				'height'    => $this->size['height'],
				'mime-type' => $mime_type,
		    ); 
		}
	}
    
    /**
     * Returns stream of current image.
     *
     * @since 3.5.0
     * @access public
     *
     * @param string $mime_type
     */
    public function stream( $mime_type = null ) {
    	if(is_resource($this->image)) {
    		parent::stream($mime_type);
    		return;
    	}
    	list( $filename, $extension, $mime_type ) = $this->get_output_format( null, $mime_type );
    	$this->saeImage->setData($this->image);
    	switch ( $mime_type ) {
    		case 'image/png':
    			$this->saeImage->exec('png', true);
    		case 'image/gif':
    			$this->saeImage->exec('gif', true);
    		default:
    			$this->saeImage->exec('jpg', true);
    	}
    }

}