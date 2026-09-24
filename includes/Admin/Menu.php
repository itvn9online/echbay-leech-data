<?php

namespace ELD\Admin;

use ELD\Support\Capabilities;
use ELD\Support\Options;
use ELD\Support\Terms;

if (!defined('ABSPATH')) {
	exit;
}

final class Menu
{
	public function hooks()
	{
		add_action('admin_menu', array($this, 'register'));
		add_filter('plugin_action_links_' . ELD_BASENAME, array($this, 'add_settings_link'));
	}

	public function register()
	{
		add_menu_page(
			'EchBay Leech Data',
			'Leech Data',
			Capabilities::CAP,
			'echbay-leech-data',
			array($this, 'render'),
			'dashicons-download',
			58
		);
	}

	/**
	 * Thêm link Settings trên trang Plugins.
	 *
	 * @param string[] $links
	 * @return string[]
	 */
	public function add_settings_link($links)
	{
		$url = admin_url('admin.php?page=echbay-leech-data');
		$settings_link = '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'echbay-leech-data') . '</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	public function render()
	{
		if (!Capabilities::can_manage()) {
			wp_die(esc_html__('Bạn không có quyền truy cập.', 'echbay-leech-data'));
		}

		$settings = Options::get_settings();
		$profiles = Options::get_profiles();
		$saved_list = Options::get_saved_list();
		$post_types = array();
		$post = get_post_type_object('post');
		if ($post) {
			$post_types['post'] = $post;
		}
		$product = get_post_type_object('product');
		if ($product) {
			$post_types['product'] = $product;
		}

		$default_pt = !empty($settings['default_post_type']) ? $settings['default_post_type'] : 'post';
		if (!isset($post_types[$default_pt])) {
			$keys = array_keys($post_types);
			$default_pt = $keys ? $keys[0] : 'post';
		}
		$taxonomy_value = Terms::taxonomy_for_post_type($default_pt);
		$initial_terms = Terms::top_level($taxonomy_value);

		include ELD_PATH . 'views/admin-page.php';
	}
}
