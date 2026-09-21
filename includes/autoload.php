<?php

if (!defined('ABSPATH')) {
	exit;
}

spl_autoload_register(static function ($class) {
	if (strpos($class, 'ELD\\') !== 0) {
		return;
	}

	$relative = str_replace('\\', '/', substr($class, 4));
	$file = ELD_PATH . 'includes/' . $relative . '.php';

	if (is_file($file)) {
		require_once $file;
	}
});
