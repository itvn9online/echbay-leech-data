<?php

namespace ELD\Ajax;

use ELD\Plugin;
use ELD\Support\Capabilities;

if (!defined('ABSPATH')) {
	exit;
}

final class CheckUpdate
{
	public function hooks()
	{
		add_action('wp_ajax_eld_check_update', array($this, 'handle'));
	}

	public function handle()
	{
		Capabilities::require_ajax();

		$updater = Plugin::instance()->auto_updater();
		if (!$updater) {
			wp_send_json_error(array('message' => 'Auto updater chưa khởi tạo.'));
		}

		$result = $updater->manual_check_update();

		if (empty($result['success'])) {
			wp_send_json_error($result);
		}

		wp_send_json_success($result);
	}
}
