<?php

namespace ELD\Ajax;

use ELD\Services\HttpClient;
use ELD\Support\Capabilities;

if (!defined('ABSPATH')) {
	exit;
}

final class FetchRemote
{
	public function hooks()
	{
		add_action('wp_ajax_eld_fetch_html', array($this, 'handle'));
	}

	public function handle()
	{
		Capabilities::require_ajax();

		$url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
		$source_url = isset($_POST['source_url']) ? esc_url_raw(wp_unslash($_POST['source_url'])) : '';
		$bypass = !empty($_POST['bypass_cache']);
		$convert = !empty($_POST['convert_encoding']);

		$client = new HttpClient();
		$html = $client->fetch(
			$url,
			array(
				'bypass_cache'      => $bypass,
				'convert_encoding'  => $convert,
				'source_url'        => $source_url,
			)
		);

		if (is_wp_error($html)) {
			wp_send_json_error(array('message' => $html->get_error_message()));
		}

		wp_send_json_success(
			array(
				'html' => $html,
				'url'  => $url,
			)
		);
	}
}
