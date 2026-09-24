<?php

namespace ELD;

use ELD\Support\AutoUpdater;

if (!defined('ABSPATH')) {
	exit;
}

final class Plugin
{
	/** @var self|null */
	private static $instance = null;

	/** @var AutoUpdater|null */
	private $auto_updater = null;

	public static function instance()
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init()
	{
		$this->auto_updater = new AutoUpdater(ELD_FILE);

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
		(new Ajax\CheckUpdate())->hooks();
	}

	/**
	 * @return AutoUpdater|null
	 */
	public function auto_updater()
	{
		return $this->auto_updater;
	}
}
