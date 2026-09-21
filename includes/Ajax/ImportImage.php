<?php

namespace ELD\Ajax;

use ELD\Services\ImageImporter;
use ELD\Support\Capabilities;

if (!defined('ABSPATH')) {
	exit;
}

final class ImportImage
{
	public function hooks()
	{
		add_action('wp_ajax_eld_import_image', array($this, 'handle'));
	}

	public function handle()
	{
		Capabilities::require_ajax();

		$url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		$importer = new ImageImporter();
		$local = $importer->sideload_url($url, $post_id);

		if ($local === '') {
			wp_send_json_error(array('message' => 'Không tải được ảnh'));
		}

		wp_send_json_success(array('url' => $local, 'source' => $url));
	}
}
