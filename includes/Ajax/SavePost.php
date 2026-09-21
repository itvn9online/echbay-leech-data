<?php

namespace ELD\Ajax;

use ELD\Adapters\NativePostAdapter;
use ELD\Adapters\WooProductAdapter;
use ELD\Support\Capabilities;

if (!defined('ABSPATH')) {
	exit;
}

final class SavePost
{
	public function hooks()
	{
		add_action('wp_ajax_eld_import_item', array($this, 'handle'));
	}

	public function handle()
	{
		Capabilities::require_ajax();

		$raw = isset($_POST['item']) ? wp_unslash($_POST['item']) : '';
		if (is_string($raw)) {
			$item = json_decode($raw, true);
		} elseif (is_array($raw)) {
			$item = $raw;
		} else {
			$item = array();
		}

		if (!is_array($item) || empty($item)) {
			wp_send_json_error(array('message' => 'Dữ liệu item rỗng'));
		}

		$post_type = !empty($item['post_type']) ? sanitize_key($item['post_type']) : 'post';
		if ($post_type === 'product') {
			$adapter = new WooProductAdapter();
		} else {
			$adapter = new NativePostAdapter();
		}

		$result = $adapter->upsert($item);

		if ($result['status'] === 'error') {
			wp_send_json_error($result);
		}

		wp_send_json_success($result);
	}
}
