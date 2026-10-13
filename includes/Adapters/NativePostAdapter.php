<?php

namespace ELD\Adapters;

use ELD\Services\Deduper;
use ELD\Services\HtmlSanitizer;
use ELD\Services\ImageImporter;
use ELD\Support\DateParser;

if (!defined('ABSPATH')) {
	exit;
}

final class NativePostAdapter implements AdapterInterface
{
	/** @var Deduper */
	private $deduper;

	/** @var HtmlSanitizer */
	private $sanitizer;

	/** @var ImageImporter */
	private $images;

	public function __construct()
	{
		$this->deduper = new Deduper();
		$this->sanitizer = new HtmlSanitizer();
		$this->images = new ImageImporter();
	}

	public function upsert(array $item)
	{
		$post_type = !empty($item['post_type']) ? sanitize_key($item['post_type']) : 'post';
		$taxonomy  = !empty($item['taxonomy']) ? sanitize_key($item['taxonomy']) : 'category';
		$title     = isset($item['title']) ? sanitize_text_field($item['title']) : '';
		$slug      = isset($item['slug']) ? sanitize_title($item['slug']) : '';
		$sku       = isset($item['sku']) ? sanitize_text_field($item['sku']) : '';
		$source    = isset($item['source_url']) ? esc_url_raw($item['source_url']) : '';
		$content   = isset($item['content']) ? $this->sanitizer->sanitize_content($item['content']) : '';
		$excerpt   = isset($item['excerpt']) ? wp_kses_post($item['excerpt']) : '';
		$term_id   = isset($item['term_id']) ? absint($item['term_id']) : 0;
		$status    = !empty($item['post_status']) ? sanitize_key($item['post_status']) : 'publish';

		if ($title === '') {
			return $this->fail('Thiếu tiêu đề');
		}
		if ($sku === '') {
			return $this->fail('Thiếu SKU leech');
		}
		if ($source === '') {
			return $this->fail('Thiếu URL nguồn');
		}

		$existing_id = $this->deduper->find_by_sku($sku, $post_type);
		$is_update = $existing_id > 0;
		$message = $is_update ? 'UPDATE' : 'NEW';

		$postarr = array(
			'post_type'    => $post_type,
			'post_title'   => $title,
			'post_name'    => $slug !== '' ? $slug : sanitize_title($title),
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => $status,
			'post_author'  => get_current_user_id(),
		);

		if (!empty($item['post_date'])) {
			$post_date = DateParser::normalize($item['post_date']);
			if ($post_date !== '' && $post_date <= current_time('mysql')) {
				$postarr['post_date'] = $post_date;
				$postarr['post_date_gmt'] = get_gmt_from_date($post_date);
			}
		}

		if (!empty($item['menu_order'])) {
			$postarr['menu_order'] = (int) $item['menu_order'];
		}

		if ($is_update) {
			$postarr['ID'] = $existing_id;
			$post_id = wp_update_post($postarr, true);
		} else {
			if (!empty($item['force_id']) && absint($item['force_id']) > 0) {
				$force = absint($item['force_id']);
				if (!get_post($force)) {
					$postarr['import_id'] = $force;
				}
			}
			$post_id = wp_insert_post($postarr, true);
		}

		if (is_wp_error($post_id)) {
			return $this->fail($post_id->get_error_message());
		}

		$post_id = (int) $post_id;

		update_post_meta($post_id, Deduper::META_SKU, $sku);
		update_post_meta($post_id, Deduper::META_URL, $source);
		update_post_meta($post_id, Deduper::META_AT, time());

		if (isset($item['price']) && $item['price'] !== '') {
			update_post_meta($post_id, '_eld_price', sanitize_text_field((string) $item['price']));
		}
		if (isset($item['sale_price']) && $item['sale_price'] !== '') {
			update_post_meta($post_id, '_eld_sale_price', sanitize_text_field((string) $item['sale_price']));
		}
		if (!empty($item['product_sku'])) {
			update_post_meta($post_id, '_eld_product_sku', sanitize_text_field($item['product_sku']));
		}
		if (!empty($item['gallery'])) {
			update_post_meta($post_id, '_eld_gallery', wp_kses_post($item['gallery']));
		}
		if (!empty($item['highlights'])) {
			update_post_meta($post_id, '_eld_highlights', wp_kses_post($item['highlights']));
		}
		if (!empty($item['youtube_url'])) {
			update_post_meta($post_id, '_eld_youtube_url', esc_url_raw($item['youtube_url']));
		}

		if ($term_id > 0 && taxonomy_exists($taxonomy)) {
			wp_set_object_terms($post_id, array($term_id), $taxonomy, false);
		}

		// Auto-create categories by name
		if ($term_id === 0 && !empty($item['new_category'])) {
			$created = $this->ensure_term($item['new_category'], $taxonomy, 0);
			$parent = $created;
			if (!empty($item['new_category_2'])) {
				$child = $this->ensure_term($item['new_category_2'], $taxonomy, $created);
				if ($child > 0) {
					$parent = $child;
				}
			}
			if ($parent > 0) {
				wp_set_object_terms($post_id, array($parent), $taxonomy, false);
			}
		}

		if (!empty($item['download_image']) && !empty($item['image_url'])) {
			$att = $this->images->sideload($item['image_url'], $post_id, $title);
			if (!is_wp_error($att)) {
				set_post_thumbnail($post_id, $att);
			}
		} elseif (!empty($item['image_url'])) {
			update_post_meta($post_id, '_eld_remote_image', esc_url_raw($item['image_url']));
		}

		if (!empty($item['download_content_images']) && $content !== '') {
			$rewritten = $this->images->rewrite_content_images($content, $post_id, $title);
			if ($rewritten !== $content) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $rewritten,
					)
				);
			}
		}

		$link = get_permalink($post_id);
		if (!$link) {
			$link = admin_url('post.php?post=' . $post_id . '&action=edit');
		}

		return array(
			'id'      => $post_id,
			'status'  => $is_update ? 'update' : 'new',
			'link'    => $link,
			'message' => $message,
		);
	}

	private function ensure_term($name, $taxonomy, $parent = 0)
	{
		$name = sanitize_text_field($name);
		if ($name === '' || !taxonomy_exists($taxonomy)) {
			return 0;
		}

		$slug = sanitize_title($name);
		$exists = term_exists($slug, $taxonomy);
		if ($exists && !is_wp_error($exists)) {
			return (int) (is_array($exists) ? $exists['term_id'] : $exists);
		}

		$created = wp_insert_term($name, $taxonomy, array(
			'slug'   => $slug,
			'parent' => (int) $parent,
		));

		if (is_wp_error($created)) {
			return 0;
		}

		return (int) $created['term_id'];
	}

	private function fail($message)
	{
		return array(
			'id'      => 0,
			'status'  => 'error',
			'link'    => '',
			'message' => $message,
		);
	}
}
