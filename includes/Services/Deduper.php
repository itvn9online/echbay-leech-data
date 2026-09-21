<?php

namespace ELD\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class Deduper
{
	const META_SKU = '_eld_source_sku';
	const META_URL = '_eld_source_url';
	const META_AT  = '_eld_imported_at';

	/**
	 * @param string $sku
	 * @param string $post_type
	 * @return int Post ID or 0
	 */
	public function find_by_sku($sku, $post_type = 'post')
	{
		$sku = trim((string) $sku);
		if ($sku === '') {
			return 0;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => array('publish', 'draft', 'pending', 'private', 'future'),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => self::META_SKU,
						'value' => $sku,
					),
				),
			)
		);

		if (!empty($query->posts[0])) {
			return (int) $query->posts[0];
		}

		return 0;
	}

	/**
	 * Fallback: find by post_name (slug).
	 */
	public function find_by_slug($slug, $post_type = 'post')
	{
		$slug = sanitize_title($slug);
		if ($slug === '') {
			return 0;
		}

		$post = get_page_by_path($slug, OBJECT, $post_type);
		return $post ? (int) $post->ID : 0;
	}
}
