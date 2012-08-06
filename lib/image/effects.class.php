<?php
/*
Effects written by Jace Ferguson
*/
/**
 * Image effects class
 *
 */
class ImageEffects extends ImageManipulation 
{
	
	public function __construct($im)
	{
		parent::__construct($im);
	}
	
	static public function new_from_jpeg($filename)
	{
		if (file_exists($filename)) 
		{
			return new ImageEffects(imagecreatefromjpeg($filename));
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
			return new ImageEffects(imagecreatefrompng($filename));
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
			return new ImageEffects(imagecreatefromgif($filename));
		}
		else
		{
			throw new CC_Exception('file not present '.$filename);
		}
	}


	/**
	 * add grayscale effect to image
	 *
	 * @return gd resource $im
	 */
	public function grayscale()
	{
		//Protects the original
		$im = $this->im;
		
		if(imagefilter($im, IMG_FILTER_GRAYSCALE))
		{
			return new ImageEffects($im);
		}
		return false;
	}	
	/**
	 * negate the image colors
	 *
	 * @return gd resource $im
	 */
	public function negative()
	{
		$im = $this->im;
		if(imagefilter($im, IMG_FILTER_NEGATE))
		{
			return new ImageEffects($im);
		}
		return false;
	}
	/**
	 * increase or decrease brightness of an image
	 *
	 * @param float $level
	 * @return gd resource $im
	 */
	public function brightness($level)
	{
		$im = $this->im;
		if(imagefilter($im, IMG_FILTER_BRIGHTNESS, $level))
		{
			return new ImageEffects($im);
		}
		return false;
	}
	/**
	 * add a gaussian blur to an image
	 *
	 * @return gd resource $im
	 */
	public function gaussian_blur()
	{
		$im = $this->im;
		if(imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR))
		{
			return new ImageEffects($im);
		}
		return false;
	}
//	public function selective_blur()
//	{
//		$im = $this->im;
//		if(imagefilter($im, IMG_FILTER_SELECTIVE_BLUR))
//		{
//			return new ImageEffects($im);
//		}
//		return false;
//	}
	/**
	 * emboss an image
	 *
	 * @return gd resource $im
	 */
	public function emboss()
	{
		$im = $this->im;
		if(imagefilter($im, IMG_FILTER_EMBOSS))
		{
			return new ImageEffects($im);
		}
		return false;	
	}
	/**
	 * smoothen an image by a certain level
	 *
	 * @param float $level
	 * @return gd resource $im
	 */
	public function smooth($level)
	{
		$im = $this->im;
		if(imagefilter($im, IMG_FILTER_SMOOTH, $level))
		{
			return new ImageEffects($im);
		}
		return false;
	}
	/**
	 * make an image look like a sketch
	 *
	 * @return gd resource $im
	 */
	public function sketch()
	{
		$im = $this->im;
		if(imagefilter($im, IMG_FILTER_MEAN_REMOVAL))
		{
			return new ImageEffects($im);
		}
		return false;
	}
	/**
	 * increase or decrease the contrast of an image
	 *
	 * @param float $level
	 * @return gd resource $im
	 */
	public function contrast($level)
	{
		$im = $this->im;
		if(imagefilter($im, IMG_FILTER_CONTRAST, $level))
		{
			return new ImageEffects($im);
		}
		return false;
	}
	/**
	 * colorize an image with a specific color
	 *
	 * @param float $red
	 * @param float $blue
	 * @param float $green
	 * @return gd resource $im
	 */
	public function colorize($red, $blue, $green)
	{
		if($red > 255)
		{
			$red = 255;
		}
		if($blue > 255)
		{
			$blue = 255;
		}
		if($green > 255)
		{
			$green = 255;
		}
		$im = $this->im;
		if(imagefilter($im, IMG_FILTER_COLORIZE, $red, $blue, $green))
		{
			return new ImageEffects($im);
		}
		return false;
	}
}
?>