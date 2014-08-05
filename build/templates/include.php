<?php

defined('_JEXEC') or die();

if (!defined('USAGE_INCLUDED'))
{
    define('USAGE_INCLUDED', '##VERSION##');

	// Register the autoloader
    require_once __DIR__ . '/autoloader/usage.php';
	UsageAutoloader::init();
}