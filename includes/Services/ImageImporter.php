<?php

namespace ELD\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class ImageImporter
{
	/** Skip files smaller than this (bytes) — emoji/icon thường rất nhẹ. */
	const MIN_BYTES = 8192;

	/** Skip images narrower than this (px). */
	const MIN_WIDTH = 120;

	/** Skip images shorter than this (px). */
	const MIN_HEIGHT = 120;

	/**
	 * Download remote image into Media Library.
	 *
	 * @param string $url
	 * @param int    $post_id Attach to post
	 * @param string $title   Attachment title / alt
	 * @return int|\WP_Error Attachment ID
	 */
	public function sideload($url, $post_id = 0, $title = '')
	{
		$url = esc_url_raw($url);
		if ($url === '' || !preg_match('#^https?://#i', $url)) {
			return new \WP_Error('eld_img_url', 'URL ảnh không hợp lệ');
		}

		if (!function_exists('media_handle_sideload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = download_url($url, 60);
		if (is_wp_error($tmp)) {
			return $tmp;
		}

		$path = wp_parse_url($url, PHP_URL_PATH);
		$name = $path ? basename($path) : 'eld-image.jpg';
		$name = sanitize_file_name($name);
		if ($name === '' || strpos($name, '.') === false) {
			$name = 'eld-image-' . time() . '.jpg';
		}

		$file_array = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);

		// null (không phải '') — tránh post_title rỗng → "Không có tiêu đề".
		$desc = $title !== '' ? $title : null;
		$id = media_handle_sideload($file_array, $post_id, $desc);

		if (is_wp_error($id)) {
			@unlink($tmp);
			return $id;
		}

		$this->apply_attachment_meta((int) $id, $title, $url);
		return (int) $id;
	}

	/**
	 * @return string Local URL or empty on failure
	 */
	public function sideload_url($url, $post_id = 0, $title = '')
	{
		$id = $this->sideload($url, $post_id, $title);
		if (is_wp_error($id)) {
			return '';
		}
		$local = wp_get_attachment_url($id);
		return $local ? $local : '';
	}

	/**
	 * Download remote <img> in content into Media Library and rewrite src.
	 * Skips emoji/icons via URL heuristics + min bytes/dimensions.
	 * Fills empty alt with $fallback_alt (thường là tiêu đề bài).
	 *
	 * @param string $html
	 * @param int    $post_id
	 * @param string $fallback_alt
	 * @return string
	 */
	public function rewrite_content_images($html, $post_id = 0, $fallback_alt = '')
	{
		if ($html === '' || stripos($html, '<img') === false) {
			return $html;
		}

		$cache = array();
		$uploads = wp_upload_dir();
		$local_base = !empty($uploads['baseurl']) ? $uploads['baseurl'] : '';
		$fallback_alt = sanitize_text_field($fallback_alt);

		return preg_replace_callback(
			'#<img\b([^>]*?)>#is',
			function ($m) use (&$cache, $post_id, $local_base, $fallback_alt) {
				$attrs = $m[1];
				if (!preg_match('#\bsrc\s*=\s*(["\'])(.*?)\1#i', $attrs, $sm)) {
					return $m[0];
				}

				$src = html_entity_decode(trim($sm[2]), ENT_QUOTES, 'UTF-8');
				if ($src === '' || strpos($src, 'data:') === 0) {
					return $this->with_img_alt($attrs, $fallback_alt, true);
				}

				$img_alt = $this->read_attr($attrs, 'alt');
				$title_for_media = $img_alt !== '' ? $img_alt : $fallback_alt;

				if ($local_base !== '' && strpos($src, $local_base) === 0) {
					return $this->with_img_alt($attrs, $title_for_media, true);
				}

				if ($this->should_skip_by_markup($attrs, $src)) {
					return $this->with_img_alt($attrs, $title_for_media, true);
				}

				$abs = esc_url_raw($src);
				if ($abs === '' || !preg_match('#^https?://#i', $abs)) {
					return $this->with_img_alt($attrs, $title_for_media, true);
				}

				if (isset($cache[$abs])) {
					$local = $cache[$abs];
				} else {
					$local = $this->sideload_product_image($abs, $post_id, $title_for_media);
					$cache[$abs] = $local;
				}

				if ($local !== '') {
					$attrs = preg_replace('#\bsrc\s*=\s*(["\']).*?\1#i', 'src="' . esc_url($local) . '"', $attrs, 1);
					$attrs = preg_replace('#\s*\b(?:srcset|sizes)\s*=\s*(["\'])(.*?)\1#i', '', $attrs);
				}

				return $this->with_img_alt($attrs, $title_for_media, true);
			},
			$html
		);
	}

	/**
	 * Sideload only if file looks like a real product photo.
	 *
	 * @param string $url
	 * @param int    $post_id
	 * @param string $title
	 * @return string Local URL or empty
	 */
	public function sideload_product_image($url, $post_id = 0, $title = '')
	{
		$url = esc_url_raw($url);
		if ($url === '' || !preg_match('#^https?://#i', $url)) {
			return '';
		}

		if (!function_exists('download_url')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if (!function_exists('media_handle_sideload')) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = download_url($url, 60);
		if (is_wp_error($tmp)) {
			return '';
		}

		$bytes = @filesize($tmp);
		if ($bytes === false || $bytes < self::MIN_BYTES) {
			@unlink($tmp);
			return '';
		}

		$size = @getimagesize($tmp);
		if (!$size || empty($size[0]) || empty($size[1])) {
			@unlink($tmp);
			return '';
		}
		if ((int) $size[0] < self::MIN_WIDTH || (int) $size[1] < self::MIN_HEIGHT) {
			@unlink($tmp);
			return '';
		}

		$path = wp_parse_url($url, PHP_URL_PATH);
		$name = $path ? basename($path) : 'eld-content.jpg';
		$name = sanitize_file_name($name);
		if ($name === '' || strpos($name, '.') === false) {
			$ext = !empty($size['mime']) ? $this->ext_from_mime($size['mime']) : 'jpg';
			$name = 'eld-content-' . time() . '.' . $ext;
		}

		$file_array = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);

		$desc = $title !== '' ? $title : null;
		$id = media_handle_sideload($file_array, $post_id, $desc);
		if (is_wp_error($id)) {
			@unlink($tmp);
			return '';
		}

		$this->apply_attachment_meta((int) $id, $title, $url);
		$local = wp_get_attachment_url((int) $id);
		return $local ? $local : '';
	}

	/**
	 * Đặt post_title + _wp_attachment_image_alt cho attachment.
	 *
	 * @param int    $attachment_id
	 * @param string $title
	 * @param string $source_url
	 */
	private function apply_attachment_meta($attachment_id, $title = '', $source_url = '')
	{
		$title = sanitize_text_field($title);
		if ($title === '') {
			$title = $this->title_from_url_or_file($attachment_id, $source_url);
		}
		if ($title === '') {
			return;
		}

		$post = get_post($attachment_id);
		if (!$post) {
			return;
		}

		if (trim((string) $post->post_title) === '') {
			wp_update_post(
				array(
					'ID'         => $attachment_id,
					'post_title' => $title,
				)
			);
		}

		$alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
		if ($alt === '' || $alt === null) {
			update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);
		}
	}

	/**
	 * @param int    $attachment_id
	 * @param string $source_url
	 * @return string
	 */
	private function title_from_url_or_file($attachment_id, $source_url = '')
	{
		$base = '';
		if ($source_url !== '') {
			$path = wp_parse_url($source_url, PHP_URL_PATH);
			$base = $path ? wp_basename($path) : '';
		}
		if ($base === '') {
			$file = get_attached_file($attachment_id);
			$base = $file ? wp_basename($file) : '';
		}
		if ($base === '') {
			return '';
		}
		$title = preg_replace('/\.[^.]+$/', '', $base);
		$title = str_replace(array('-', '_'), ' ', $title);
		return sanitize_text_field($title);
	}

	/**
	 * Gán alt nếu thiếu / trống; trả về thẻ <img…>.
	 *
	 * @param string $attrs
	 * @param string $alt
	 * @param bool   $as_tag
	 * @return string
	 */
	private function with_img_alt($attrs, $alt, $as_tag = false)
	{
		$alt = sanitize_text_field($alt);
		if ($alt !== '') {
			$current = $this->read_attr($attrs, 'alt');
			if ($current === '') {
				if (preg_match('#\balt\s*=#i', $attrs)) {
					$attrs = preg_replace('#\balt\s*=\s*(["\'])(.*?)\1#i', 'alt="' . esc_attr($alt) . '"', $attrs, 1);
				} else {
					$attrs .= ' alt="' . esc_attr($alt) . '"';
				}
			}
		}
		return $as_tag ? '<img' . $attrs . '>' : $attrs;
	}

	/**
	 * @param string $attrs
	 * @param string $name
	 * @return string
	 */
	private function read_attr($attrs, $name)
	{
		if (!preg_match('#\b' . preg_quote($name, '#') . '\s*=\s*(["\'])(.*?)\1#i', $attrs, $m)) {
			return '';
		}
		return trim(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
	}

	/**
	 * @param string $attrs Raw img attributes
	 * @param string $src
	 * @return bool
	 */
	private function should_skip_by_markup($attrs, $src)
	{
		$hay = strtolower($attrs . ' ' . $src);

		$needles = array(
			'emoji',
			'twemoji',
			'emojione',
			'emojipedia',
			's.w.org/images/core/emoji',
			'wp-smiley',
			'icon-',
			'favicon',
			'sprite',
			'1x1',
			'pixel.gif',
			'spacer.',
		);
		foreach ($needles as $n) {
			if (strpos($hay, $n) !== false) {
				return true;
			}
		}

		$w = $this->attr_int($attrs, 'width');
		$h = $this->attr_int($attrs, 'height');
		if ($w > 0 && $w < self::MIN_WIDTH) {
			return true;
		}
		if ($h > 0 && $h < self::MIN_HEIGHT) {
			return true;
		}

		$path = (string) wp_parse_url($src, PHP_URL_PATH);
		if (preg_match('#\.(svg|ico)(\?|$)#i', $path)) {
			return true;
		}

		return false;
	}

	/**
	 * @param string $attrs
	 * @param string $name
	 * @return int
	 */
	private function attr_int($attrs, $name)
	{
		if (!preg_match('#\b' . preg_quote($name, '#') . '\s*=\s*(["\']?)(\d+)\1#i', $attrs, $m)) {
			return 0;
		}
		return (int) $m[2];
	}

	/**
	 * @param string $mime
	 * @return string
	 */
	private function ext_from_mime($mime)
	{
		$map = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);
		return isset($map[$mime]) ? $map[$mime] : 'jpg';
	}
}
