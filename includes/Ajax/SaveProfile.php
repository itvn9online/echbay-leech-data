<?php

namespace ELD\Ajax;

use ELD\Support\Capabilities;
use ELD\Support\Options;

if (!defined('ABSPATH')) {
	exit;
}

final class SaveProfile
{
	public function hooks()
	{
		add_action('wp_ajax_eld_save_profile', array($this, 'save_profile'));
		add_action('wp_ajax_eld_list_profiles', array($this, 'list_profiles'));
		add_action('wp_ajax_eld_delete_profile', array($this, 'delete_profile'));
		add_action('wp_ajax_eld_save_list_urls', array($this, 'save_list'));
		add_action('wp_ajax_eld_save_settings', array($this, 'save_settings'));
	}

	public function list_profiles()
	{
		Capabilities::require_ajax();
		wp_send_json_success(
			array(
				'profiles' => Options::get_profiles(),
				'list'     => Options::get_saved_list(),
				'settings' => Options::get_settings(),
			)
		);
	}

	public function save_profile()
	{
		Capabilities::require_ajax();

		$raw = isset($_POST['profile']) ? wp_unslash($_POST['profile']) : '';
		$profile = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);

		if (!is_array($profile) || empty($profile['domain'])) {
			wp_send_json_error(array('message' => 'Profile thiếu domain'));
		}

		$id = !empty($profile['id']) ? sanitize_key($profile['id']) : sanitize_key($profile['domain']);
		$profile['id'] = $id;
		$profile['domain'] = sanitize_text_field($profile['domain']);
		$profile['updated_at'] = time();

		$profiles = Options::get_profiles();
		$profiles[$id] = $profile;
		Options::save_profiles($profiles);

		wp_send_json_success(array('profile' => $profile, 'profiles' => $profiles));
	}

	public function delete_profile()
	{
		Capabilities::require_ajax();
		$id = isset($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '';
		$profiles = Options::get_profiles();
		unset($profiles[$id]);
		Options::save_profiles($profiles);
		wp_send_json_success(array('profiles' => $profiles));
	}

	public function save_list()
	{
		Capabilities::require_ajax();
		$text = isset($_POST['list']) ? wp_unslash($_POST['list']) : '';
		Options::save_list($text);
		wp_send_json_success(array('list' => Options::get_saved_list()));
	}

	public function save_settings()
	{
		Capabilities::require_ajax();
		$raw = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '';
		$settings = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : array());
		if (!is_array($settings)) {
			wp_send_json_error(array('message' => 'Settings invalid'));
		}

		$clean = array();
		if (isset($settings['default_post_type'])) {
			$clean['default_post_type'] = sanitize_key($settings['default_post_type']);
		}
		if (isset($settings['default_taxonomy'])) {
			$clean['default_taxonomy'] = sanitize_key($settings['default_taxonomy']);
		}
		if (isset($settings['user_agent'])) {
			$clean['user_agent'] = sanitize_text_field($settings['user_agent']);
		}
		if (isset($settings['cache_ttl'])) {
			$clean['cache_ttl'] = absint($settings['cache_ttl']);
		}
		if (isset($settings['request_timeout'])) {
			$clean['request_timeout'] = max(5, absint($settings['request_timeout']));
		}

		Options::update_settings($clean);
		wp_send_json_success(array('settings' => Options::get_settings()));
	}
}
