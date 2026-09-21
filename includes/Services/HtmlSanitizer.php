<?php

namespace ELD\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class HtmlSanitizer
{
	public function sanitize_content($html)
	{
		$allowed = wp_kses_allowed_html('post');
		$allowed['iframe'] = array(
			'src'             => true,
			'width'           => true,
			'height'          => true,
			'frameborder'     => true,
			'allowfullscreen' => true,
			'allow'           => true,
		);
		return wp_kses($html, $allowed);
	}
}
