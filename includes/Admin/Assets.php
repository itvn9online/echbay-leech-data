<?php

namespace ELD\Admin;

use ELD\Support\Terms;

if (!defined('ABSPATH')) {
	exit;
}

final class Assets
{
	public function hooks()
	{
		add_action('admin_enqueue_scripts', array($this, 'enqueue'));
	}

	public function enqueue($hook)
	{
		if ($hook !== 'toplevel_page_echbay-leech-data') {
			return;
		}

		wp_enqueue_style(
			'eld-admin',
			ELD_URL . 'assets/css/admin.css',
			array(),
			filemtime(ELD_PATH . 'assets/css/admin.css')
		);

		wp_enqueue_script(
			'eld-admin',
			ELD_URL . 'assets/js/admin.js',
			array('jquery'),
			filemtime(ELD_PATH . 'assets/js/admin.js'),
			true
		);

		wp_localize_script(
			'eld-admin',
			'ELD_ADMIN',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('eld_admin'),
				'homeUrl' => home_url('/'),
				'terms'   => Terms::for_js(),
				'i18n'    => array(
					'fetching'  => 'Đang tải HTML…',
					'importing' => 'Đang import…',
					'done'      => 'Hoàn thành',
					'error'     => 'Lỗi',
				),
			)
		);
	}
}
