<?php

defined('_JEXEC') or die();

if (!defined('USAGEDATA_INCLUDED'))
{
    define('USAGEDATA_INCLUDED', '##VERSION##');

	// Register the autoloader
    require_once __DIR__ . '/autoloader/usagedata.php';
	UsagedataAutoloader::init();
}