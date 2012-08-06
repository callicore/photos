<?php

/**
 * Image Manipulation class
 *
 */
class ImageManipulation
{

	/**
	 * holds gd resource
	 *
	 * @var gd resource
	 */
	protected $im = null;
	/**
	 * hold gd resource width
	 *
	 * @var int
	 */
	protected $width = 0;
	/**
	 * hold gd resource height
	 *
	 * @var int
	 */
	protected $height = 0;

	/**
	 * construct object from gd resource
	 *
	 * @param gd resource $im
	 */
	public function __construct($im)
	{
		if (is_resource($im))
		{
			if(imagecopy($im,$im,0,0,0,0,imageSX($im),imageSY($im)))
			{
				$this->width = imageSX($im);
				$this->height = imageSY($im);
				$this->im = imagecreatetruecolor($this->width,$this->height);
				imagecopy($this->im,$im,0,0,0,0,$this->width,$this->height);
			}else{
				throw new CC_Exception('resource creation faild');
			}
		}else{
			throw new CC_Exception('argument is not resource');
		}
	}

	public static function new_from_pixbuf(GdkPixbuf $pixbuf)
	{
		$filename = CC::$dir.'temp/'.basename(tempnam('temp','tmp'));
		$pixbuf->save($filename,'png');
		$object = self::new_from_png($filename);
		unlink($filename);
		return $object;
	}

	static public function new_from_jpeg($filename)
	{
		if (file_exists($filename)) 
		{
			return new ImageManipulation(imagecreatefromjpeg($filename));
		}
		else
		{
			throw new CC_Exception('file not present '.$filename);
		}
	}

	static public function new_from_png($filename)
	{
		if (file_exists($filename)) 
		{
			return new ImageManipulation(imagecreatefrompng($filename));
		}
		else
		{
			throw new CC_Exception('file not present '.$filename);
		}
	}

	static public function new_from_gif($filename)
	{
		if (file_exists($filename)) 
		{
			return new ImageManipulation(imagecreatefromgif($filename));
		}
		else
		{
			throw new CC_Exception('file not present '.$filename);
		}
	}

	static public function new_from_xpm($filename)
	{
		if (file_exists($filename)) 
		{
			return new ImageManipulation(imagecreatefromxpm($filename));
		}
		else
		{
			throw new CC_Exception('file not present '.$filename);
		}
	}

	/**
	 * get gd resource
	 *
	 * @return gd resource
	 */
	public function get_gd()
	{
		return $this->im;
	}

	/**
	 * get gd resource width
	 *
	 * @return int
	 */
	public function get_width(){
		return $this->width;
	}

	/**
	 * get gd resource height
	 *
	 * @return int
	 */
	public function get_height(){
		return $this->height;
	}

	/**
	 * split image into RGB color channels
	 *
	 * @return array
	 */
	public function get_rgb_channels()
	{
		$width=imageSX($im);
		$height=imageSY($im);
		$red_channel = imagecreatetruecolor($width, $height);
		imagecopy($red_channel,$im,0,0,0,0,$width,$height);
		$green_channel = imagecreatetruecolor($width, $height);
		imagecopy($green_channel,$im,0,0,0,0,$width,$height);
		$blue_channel = imagecreatetruecolor($width, $height);
		imagecopy($blue_channel,$im,0,0,0,0,$width,$height);
		for ($x = 0; $x < $width; $x++)
		{
			for ($y = 0; $y < $width; $y++)
			{
				$index = imagecolorat($this->im, $x, $y);
				$color = imagecolorsforindex($this->im, $index);
				$red = $color['red'];
				$green = $color['green'];
				$blue = $color['blue'];
				$red_index = imagecolorat($red_channel, $x, $y);
				$green_index = imagecolorat($green_channel, $x, $y);
				$blue_index = imagecolorat($blue_channel, $x, $y);
				$red_color = imagecolorallocate($red_channel, $red, 0, 0);
				$green_color = imagecolorallocate($green_channel, 0, $green, 0);
				$blue_color = imagecolorallocate($blue_channel, 0, 0, $blue);
				imagesetpixel($red_channel,$x,$y,$red_color);
				imagesetpixel($green_channel,$x,$y,$green_color);
				imagesetpixel($blue_channel,$x,$y,$blue_color);
			}
		}
		return array('red'=>new ImageManipulation($red_channel),'green'=>new ImageManipulation($green_channel),'blue'=>new ImageManipulation($blue_channel));
	}

	/**
	 * resize current gd resource
	 *
	 * @param int $width
	 * @param int $height
	 * @return ImageManipulation
	 */
	public function resize($width,$height)
	{
		$im = imagecreatetruecolor($width, $height);
		imagecopyresized($im, $this->im, 0, 0, 0, 0, $width, $height, $this->width, $this->height);
		$this->im = $im;
		$this->width = $width;
		$this->height=$height;
		return new ImageManipulation($im);
	}
	
	public function resize_percent($width_percent,$height_percent)
	{
		$width = round(($this->width / 100)*$width_percent);
		$height = round(($this->height / 100)*$height_percent);
		$im = imagecreatetruecolor($width, $height);
		imagecopyresized($im, $this->im, 0, 0, 0, 0, $width, $height, $this->width, $this->height);
		$this->im = $im;
		$this->width = $width;
		$this->height = $height;
		return new ImageManipulation($im);
	}
	/**
	 * Rotates a gd resource
	 *
	 * @param double $angle
	 * @return ImageManipulation
	 */
	public function rotate($angle)
	{
		$this->im = imagerotate($this->im, $angle, 0);
		$this->width = imagesx($this->im);
		$this->height = imagesy($this->im);
		return new ImageManipulation($this->im);
	}

	/**
	 * crops current gd resource
	 *
	 * @param int $left
	 * @param int $right
	 * @param int $top
	 * @param int $bottom
	 * @return ImageManipulation
	 */
	public function crop($left, $right, $top, $bottom)
	{
		$width = $this->width - $left - $right;
		$height = $this->height - $top - $bottom;
		$temp = imagecreatetruecolor($width,$height);
		imagecopy( $temp, $this->im, $right, $bottom, $left, $top, $width, $height );
		$this->im = $temp;
		$this->width=$width;
		$this->height=$height;
		return new ImageManipulation($temp);
	}

	/**
	 * modifys gdresource rgb color levels
	 *
	 * @param int $red_percent
	 * @param int $green_percent
	 * @param int $blue_percent
	 * @return ImageManipulation
	 */
	function modify_rgb($red_percent = 100, $green_percent = 100, $blue_percent = 100){
		for ($x = 0; $x < $this->width; $x++)
		{
			for ($y = 0; $y < $this->height; $y++)
			{
				$index = imagecolorat($this->im, $x, $y);
				$color = imagecolorsforindex($this->im, $index);
				$red = round(($color['red'] / 100) * $red_percent);
				$green = round(($color['green'] / 100) * $green_percent);
				$blue = round(($color['blue'] / 100) * $blue_percent);
				$newcolor = imagecolorallocate($this->im, $red, $green, $blue);
				imagesetpixel($this->im,$x,$y,$newcolor);
			}
		}
		return new ImageManipulation($im);
	}

	/**
	 * get a thumbnail of current gd resource
	 *
	 * @param int $max_width
	 * @param int $max_height
	 * @return ImageManipulation
	 */
	public function get_thumb($aspect = 70)
	{
		//list($width,$height) = getimagesize($path);
		if($this->width == $this->height) {
			$wh = array($aspect,$aspect);
		}
		if($this->width > $this->height) {
			$h = (($aspect * $this->height) / $this->width);
			$wh = array($aspect,$h);
		}
		if($this->width < $this->height) {
			$w = (($aspect * $this->width) / $this->height);
			$wh = array($w,$aspect);
		}
		$im = imagecreatetruecolor($wh[0], $wh[1]);
		imagecopyresized($im, $this->im, 0, 0, 0, 0, $wh[0], $wh[1], $this->width, $this->height);
		return new ImageManipulation($im);
	}

	/**
	 * get a GdkPixbuf of gd resource
	 *
	 * @return GdkPixbuf
	 */
	public function get_pixbuf(){
		$filename = CC::$dir.'temp/'.basename(tempnam('temp','tmp'));
		imagepng($this->im,$filename);
		$pixbuf = GdkPixbuf::new_from_file($filename);
		unlink($filename);
		return $pixbuf;
	}
	
	public function __destruct(){
		//imagedestroy($this->im);
	}

}

?> 