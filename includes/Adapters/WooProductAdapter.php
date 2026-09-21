<?php

namespace ELD\Adapters;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * WooCommerce product adapter (optional).
 * Falls back to NativePostAdapter fields if Woo is inactive.
 */
final class WooProductAdapter implements AdapterInterface
{
	/** @var NativePostAdapter */
	private $native;

	public function __construct()
	{
		$this->native = new NativePostAdapter();
	}

	public function upsert(array $item)
	{
		if (!class_exists('WooCommerce')) {
			$item['post_type'] = 'post';
			return $this->native->upsert($item);
		}

		$item['post_type'] = 'product';
		$item['taxonomy'] = !empty($item['taxonomy']) ? $item['taxonomy'] : 'product_cat';

		$result = $this->native->upsert($item);

		if (empty($result['id']) || $result['status'] === 'error') {
			return $result;
		}

		$post_id = (int) $result['id'];

		if (isset($item['price']) && $item['price'] !== '') {
			update_post_meta($post_id, '_regular_price', (string) $item['price']);
		}
		if (isset($item['sale_price']) && $item['sale_price'] !== '') {
			update_post_meta($post_id, '_sale_price', (string) $item['sale_price']);
			update_post_meta($post_id, '_price', (string) $item['sale_price']);
		} elseif (isset($item['price']) && $item['price'] !== '') {
			update_post_meta($post_id, '_price', (string) $item['price']);
		}
		if (!empty($item['product_sku'])) {
			update_post_meta($post_id, '_sku', sanitize_text_field($item['product_sku']));
		}

		wp_set_object_terms($post_id, 'simple', 'product_type', false);

		return $result;
	}
}
