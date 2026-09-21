<?php

namespace ELD\Support;

if (!defined('ABSPATH')) {
	exit;
}

final class Capabilities
{
	const CAP = 'manage_options';

	public static function can_manage()
	{
		return current_user_can(self::CAP);
	}

	public static function require_ajax()
	{
		if (!self::can_manage()) {
			wp_send_json_error(array('message' => 'Forbidden'), 403);
		}

		check_ajax_referer('eld_admin', 'nonce');
	}
}
