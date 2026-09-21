<?php

namespace ELD\Ajax;

use ELD\Services\HtmlCache;
use ELD\Support\Capabilities;

if (!defined('ABSPATH')) {
	exit;
}

final class ClearCache
{
	public function hooks()
	{
		add_action('wp_ajax_eld_clear_cache', array($this, 'handle'));
	}

	public function handle()
	{
		Capabilities::require_ajax();
		$count = (new HtmlCache())->clear();
		wp_send_json_success(array('cleared' => $count));
	}
}
