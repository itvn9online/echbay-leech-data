<?php

namespace ELD\Ajax;

use ELD\Support\Capabilities;

if (!defined('ABSPATH')) {
	exit;
}

final class CreateTerm
{
	public function hooks()
	{
		add_action('wp_ajax_eld_create_term', array($this, 'handle'));
	}

	public function handle()
	{
		Capabilities::require_ajax();

		$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		$slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : sanitize_title($name);
		$taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : 'category';
		$parent = isset($_POST['parent']) ? absint($_POST['parent']) : 0;

		if ($name === '' || !taxonomy_exists($taxonomy)) {
			wp_send_json_error(array('message' => 'Tên hoặc taxonomy không hợp lệ'));
		}

		$exists = term_exists($slug, $taxonomy);
		if ($exists && !is_wp_error($exists)) {
			$term_id = (int) (is_array($exists) ? $exists['term_id'] : $exists);
			wp_send_json_success(array('term_id' => $term_id, 'existed' => true));
		}

		$created = wp_insert_term(
			$name,
			$taxonomy,
			array(
				'slug'   => $slug,
				'parent' => $parent,
			)
		);

		if (is_wp_error($created)) {
			wp_send_json_error(array('message' => $created->get_error_message()));
		}

		wp_send_json_success(array('term_id' => (int) $created['term_id'], 'existed' => false));
	}
}
