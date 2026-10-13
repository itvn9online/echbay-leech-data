<?php

namespace ELD\Support;

if (!defined('ABSPATH')) {
	exit;
}

final class DateParser
{
	/**
	 * Normalize a scraped date string to MySQL format (site local time).
	 *
	 * Supports text around the date, e.g. "Đăng lúc: 02:23 22/09/2017",
	 * day-first numeric dates (dd/mm/yyyy, dd-mm-yyyy, dd.mm.yyyy),
	 * year-first dates (yyyy-mm-dd, yyyy/mm/dd), Vietnamese "22 tháng 9, 2017",
	 * times like "02:23", "02:23:45", "10h30", "2:23 PM". Falls back to strtotime().
	 *
	 * @param string $raw
	 * @return string 'Y-m-d H:i:s' or '' when unparseable.
	 */
	public static function normalize($raw)
	{
		$raw = html_entity_decode(wp_strip_all_tags((string) $raw), ENT_QUOTES, 'UTF-8');
		$raw = trim(preg_replace('/\s+/u', ' ', $raw));
		if ($raw === '') {
			return '';
		}

		$date = self::match_date($raw);
		if ($date) {
			list($y, $m, $d) = $date;
			list($h, $i, $s) = self::match_time($raw);
			return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $m, $d, $h, $i, $s);
		}

		$ts = strtotime($raw);
		return $ts ? date('Y-m-d H:i:s', $ts) : '';
	}

	/**
	 * @param string $raw
	 * @return array{0:int,1:int,2:int}|null [year, month, day]
	 */
	private static function match_date($raw)
	{
		if (preg_match('/(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})/', $raw, $m)) {
			return self::valid_date((int) $m[1], (int) $m[2], (int) $m[3]);
		}

		if (preg_match('/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/', $raw, $m)) {
			$day = (int) $m[1];
			$month = (int) $m[2];
			$year = (int) $m[3];
			// Day-first is the default; swap only when it is clearly month-first (e.g. 09/22/2017).
			if ($month > 12 && $day <= 12) {
				list($day, $month) = array($month, $day);
			}
			return self::valid_date($year, $month, $day);
		}

		if (preg_match('/(\d{1,2})\s*tháng\s*(\d{1,2})\s*(?:,|năm)?\s*(\d{4})/iu', $raw, $m)) {
			return self::valid_date((int) $m[3], (int) $m[2], (int) $m[1]);
		}

		return null;
	}

	/**
	 * @param string $raw
	 * @return array{0:int,1:int,2:int} [hour, minute, second]
	 */
	private static function match_time($raw)
	{
		if (!preg_match('/(?<!\d)(\d{1,2})\s*[:hH]\s*(\d{2})(?:\s*:\s*(\d{2}))?(?:\s*([AaPp])\.?[Mm]\.?)?/', $raw, $m)) {
			return array(0, 0, 0);
		}

		$h = (int) $m[1];
		$i = (int) $m[2];
		$s = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0;
		$ampm = isset($m[4]) ? strtolower($m[4]) : '';

		if ($ampm === 'p' && $h < 12) {
			$h += 12;
		} elseif ($ampm === 'a' && $h === 12) {
			$h = 0;
		}

		if ($h > 23 || $i > 59 || $s > 59) {
			return array(0, 0, 0);
		}
		return array($h, $i, $s);
	}

	/**
	 * @return array{0:int,1:int,2:int}|null
	 */
	private static function valid_date($y, $m, $d)
	{
		return checkdate($m, $d, $y) ? array($y, $m, $d) : null;
	}
}
