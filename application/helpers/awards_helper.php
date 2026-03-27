<?php defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('awards_build_qsl_string')) {
	function awards_build_qsl_string($postdata) {
		$qsl = '';
		if (($postdata['qsl'] ?? null) == 1) {
			$qsl .= 'Q';
		}
		if (($postdata['lotw'] ?? null) == 1) {
			$qsl .= 'L';
		}
		if (($postdata['eqsl'] ?? null) == 1) {
			$qsl .= 'E';
		}
		if (($postdata['dcl'] ?? null) == 1) {
			$qsl .= 'D';
		}
		if (($postdata['clublog'] ?? null) == 1) {
			$qsl .= 'C';
		}
		if (($postdata['qrz'] ?? null) == 1) {
			$qsl .= 'Z';
		}

		return $qsl;
	}
}

if (!function_exists('awards_build_display_contacts_href')) {
	function awards_build_display_contacts_href($searchphrase, $band, $mode, $type, $qsl = '', $datefrom = '', $dateto = '', $sat = 'All', $orbit = 'All') {
		$args = array(
			(string) $searchphrase,
			(string) $band,
			(string) $sat,
			(string) $orbit,
			(string) $mode,
			(string) $type,
			(string) $qsl,
			(string) $datefrom,
			(string) $dateto,
		);

		return 'javascript:displayContacts(' . implode(',', array_map('json_encode', $args)) . ')';
	}
}

if (!function_exists('awards_render_jcc_cell')) {
	function awards_render_jcc_cell($entity, $band, $status, $postdata) {
		if ($status !== 'W' && $status !== 'C') {
			return '-';
		}

		$qsl_string = $status === 'C' ? awards_build_qsl_string($postdata) : '';
		$href = awards_build_display_contacts_href(
			$entity,
			$band,
			$postdata['mode'] ?? 'All',
			'JCC',
			$qsl_string,
		);
		$class_name = $status === 'C' ? 'bg-success awardsBgSuccess' : 'bg-danger awardsBgWarning';

		return '<div class="' . $class_name . '"><a href="' . html_escape($href) . '">' . $status . '</a></div>';
	}
}
