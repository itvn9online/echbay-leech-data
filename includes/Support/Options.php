<?php

namespace ELD\Support;

if (!defined('ABSPATH')) {
	exit;
}

final class Options
{
	const PROFILES = 'eld_profiles';
	const SETTINGS = 'eld_settings';
	const LIST_URLS = 'eld_saved_list_urls';

	public static function get_settings()
	{
		$defaults = array(
			'default_post_type' => 'post',
			'default_taxonomy'  => 'category',
			'user_agent'        => 'Mozilla/5.0 (compatible; EchBayLeechData/0.1; +https://echbay.com/)',
			'cache_ttl'         => DAY_IN_SECONDS,
			'request_timeout'   => 30,
		);

		$saved = get_option(self::SETTINGS, array());
		if (!is_array($saved)) {
			$saved = array();
		}

		return array_merge($defaults, $saved);
	}

	public static function update_settings(array $settings)
	{
		$current = self::get_settings();
		return update_option(self::SETTINGS, array_merge($current, $settings), false);
	}

	public static function get_profiles()
	{
		$profiles = get_option(self::PROFILES, array());
		return is_array($profiles) ? $profiles : array();
	}

	public static function save_profiles(array $profiles)
	{
		return update_option(self::PROFILES, $profiles, false);
	}

	public static function get_saved_list()
	{
		$value = get_option(self::LIST_URLS, '');
		return is_string($value) ? $value : '';
	}

	public static function save_list($text)
	{
		return update_option(self::LIST_URLS, (string) $text, false);
	}
}
