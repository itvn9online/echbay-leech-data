<?php

namespace ELD\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class HttpClient
{
	/** @var array */
	private $settings;

	public function __construct(array $settings = null)
	{
		$this->settings = $settings ?: \ELD\Support\Options::get_settings();
	}

	/**
	 * Fetch remote HTML. Returns HTML string or WP_Error.
	 *
	 * @param string $url
	 * @param array  $args {
	 *   @type bool   $bypass_cache
	 *   @type bool   $convert_encoding
	 *   @type string $source_url  Replace home_url occurrences in HTML with this base.
	 * }
	 * @return string|\WP_Error
	 */
	public function fetch($url, array $args = array())
	{
		$url = esc_url_raw($url);
		if ($url === '' || !preg_match('#^https?://#i', $url)) {
			return new \WP_Error('eld_bad_url', 'URL không hợp lệ (chỉ http/https).');
		}

		$host = wp_parse_url($url, PHP_URL_HOST);
		if (!$host || $this->is_private_host($host)) {
			return new \WP_Error('eld_ssrf', 'Không được fetch IP/host nội bộ.');
		}

		$cache = new HtmlCache(isset($this->settings['cache_ttl']) ? (int) $this->settings['cache_ttl'] : null);
		$bypass = !empty($args['bypass_cache']);

		if (!$bypass) {
			$cached = $cache->get($url);
			if ($cached !== null && $cached !== '') {
				return $this->post_process($cached, $args);
			}
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => (int) $this->settings['request_timeout'],
				'redirection' => 5,
				'user-agent'  => $this->settings['user_agent'],
				'sslverify'   => true,
				'headers'     => array(
					'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
				),
			)
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = (string) wp_remote_retrieve_body($response);

		if ($code < 200 || $code >= 400 || $body === '') {
			return new \WP_Error('eld_http', 'Fetch thất bại HTTP ' . $code);
		}

		if (!empty($args['convert_encoding'])) {
			$detected = mb_detect_encoding($body, 'UTF-8, GBK, ISO-8859-1', true);
			if ($detected && strtoupper($detected) !== 'UTF-8') {
				$body = mb_convert_encoding($body, 'UTF-8', $detected);
			}
		}

		$cache->put($url, $body);

		return $this->post_process($body, $args);
	}

	private function post_process($html, array $args)
	{
		if (!empty($args['source_url'])) {
			$source = esc_url_raw($args['source_url']);
			if ($source !== '') {
				$html = str_replace(home_url('/'), $source, $html);
				$html = str_replace(home_url(), rtrim($source, '/'), $html);
			}
		}

		return $html;
	}

	private function is_private_host($host)
	{
		$host = strtolower($host);

		if (in_array($host, array('localhost', 'localhost.localdomain'), true)) {
			return true;
		}

		if (preg_match('/\.local$/', $host)) {
			return true;
		}

		$ips = array();
		if (filter_var($host, FILTER_VALIDATE_IP)) {
			$ips[] = $host;
		} else {
			$resolved = gethostbynamel($host);
			if (is_array($resolved)) {
				$ips = $resolved;
			}
		}

		foreach ($ips as $ip) {
			if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				return true;
			}
		}

		return false;
	}
}
