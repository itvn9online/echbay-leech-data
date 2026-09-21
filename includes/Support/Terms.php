<?php

namespace ELD\Support;

if (!defined('ABSPATH')) {
	exit;
}

final class Terms
{
	/**
	 * @param string $post_type
	 * @return string
	 */
	public static function taxonomy_for_post_type($post_type)
	{
		return $post_type === 'product' ? 'product_cat' : 'category';
	}

	/**
	 * Top-level terms for JS (max 100 each).
	 *
	 * @return array<string, array<int, array{id:int,name:string}>>
	 */
	public static function for_js()
	{
		$out = array(
			'category' => self::top_level('category'),
		);
		if (taxonomy_exists('product_cat')) {
			$out['product_cat'] = self::top_level('product_cat');
		}
		return $out;
	}

	/**
	 * @param string $taxonomy
	 * @param int    $limit
	 * @return array<int, array{id:int,name:string}>
	 */
	public static function top_level($taxonomy, $limit = 100)
	{
		if (!taxonomy_exists($taxonomy)) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'parent'     => 0,
				'hide_empty' => false,
				'number'     => (int) $limit,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if (is_wp_error($terms) || !is_array($terms)) {
			return array();
		}

		$list = array();
		foreach ($terms as $term) {
			$list[] = array(
				'id'   => (int) $term->term_id,
				'name' => $term->name,
			);
		}
		return $list;
	}
}
