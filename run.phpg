<?php
/**
 * run.php - callicore bootstrap file
 *
 * This is a simplistic bootstrap file for callicore
 *
 * On windows the easiest way to use it is to create a shortcut
 * C:\WINDOWS\system32\cmd.exe /k "C:\path\to\php.exe"  run.php name_of_module
 * "C:\path\to\php-win.exe" run.php name_of_module
 *
 * You do not have to use this script to run a callicore program, you can write
 * your own bootstrap, just use CC::run('name of program'); to start callicore
 *
 * This is released under the GPL, see docs/gpl.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2006
 * @link         http://callicore.net
 * @license      http://www.opensource.org/licenses/gpl-license.php GPL
 * @version      $Id: run.php 150 2007-06-13 02:07:50Z emsmith $
 * @since        Php 5.2.0
 * @package      callicore
 * @subpackage   core
 * @category     bin
 * @filesource
 */

error_reporting(E_ALL | E_STRICT);

include (dirname(__FILE__) . '/../lib/cc.class.php');

// This particular script checks argv for a program name
if (isset($argv) && isset($argv[1]))
{
	$program = (string) $argv[1];
}

CC::run($program);
Gtk::main();
?>