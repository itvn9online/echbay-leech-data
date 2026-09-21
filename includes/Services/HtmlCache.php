<?php

namespace ELD\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class HtmlCache
{
	/** @var string */
	private $dir;

	/** @var int */
	private $ttl;

	public function __construct($ttl = null)
	{
		$upload = wp_upload_dir();
		$this->dir = trailingslashit($upload['basedir']) . 'eld-cache';
		$this->ttl = $ttl !== null ? (int) $ttl : (int) \ELD\Support\Options::get_settings()['cache_ttl'];

		if (!is_dir($this->dir)) {
			wp_mkdir_p($this->dir);
		}
	}

	public function path_for_url($url)
	{
		$key = strlen($url) > 200 ? md5($url) : sanitize_file_name(str_replace(array('://', '/', ':', '?', '&', '='), '-', $url));
		$key = preg_replace('/[^a-zA-Z0-9._-]/', '', $key);
		if ($key === '' || $key === null) {
			$key = md5($url);
		}
		return trailingslashit($this->dir) . $key . '.html';
	}

	public function get($url)
	{
		$file = $this->path_for_url($url);
		if (!is_file($file)) {
			return null;
		}

		if ($this->ttl > 0 && (time() - filemtime($file)) > $this->ttl) {
			@unlink($file);
			return null;
		}

		$c = file_get_contents($file);
		return $c === false ? null : $c;
	}

	public function put($url, $html)
	{
		$file = $this->path_for_url($url);
		return (bool) file_put_contents($file, $html);
	}

	public function clear()
	{
		$count = 0;
		foreach (glob(trailingslashit($this->dir) . '*.html') ?: array() as $file) {
			if (@unlink($file)) {
				$count++;
			}
		}
		return $count;
	}
}
