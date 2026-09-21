<?php

namespace ELD;

if (!defined('ABSPATH')) {
	exit;
}

final class Plugin
{
	/** @var self|null */
	private static $instance = null;

	public static function instance()
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init()
	{
		if (is_admin()) {
			(new Admin\Menu())->hooks();
			(new Admin\Assets())->hooks();
		}

		(new Ajax\FetchRemote())->hooks();
		(new Ajax\SavePost())->hooks();
		(new Ajax\SaveProfile())->hooks();
		(new Ajax\CreateTerm())->hooks();
		(new Ajax\ImportImage())->hooks();
		(new Ajax\ClearCache())->hooks();
	}
}
