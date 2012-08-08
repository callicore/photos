<?php
/**
 * photos.php - include all file for photos, use instead of phar for development
 *
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth M Smith <auroraeosrose@gmail.com>
 * @copyright    Elizabeth M Smith (c) 2009-2012
 * @link         http://callicore.net
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @since        Php 5.4.0 GTK 2.24.0
 * @package      callicore
 * @subpackage   photos
 * @filesource
 */

/**
 * Figure out our app location
 */
defined('CALLICORE_PHOTOS') || define('CALLICORE_PHOTOS', (getenv('CALLICORE_PHOTOS') ? getenv('CALLICORE_PHOTOS') : __DIR__ . DIRECTORY_SEPARATOR));

/**
 * Include all photos items
 */
include CALLICORE_PHOTOS . 'app' . DIRECTORY_SEPARATOR . 'Application.php';