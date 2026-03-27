<?php

class Jcc_model extends CI_Model {


	private $location_list=null;
	function __construct() {
		$this->load->library('Genfunctions');
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		$this->location_list = "'".implode("','",$logbooks_locations_array)."'";
		$this->load_jcc_data_from_json();
		$this->load_ku_data_from_json();
	}

	public $ja_cities = array();
	public $ja_kus = array();

	private function load_jcc_data_from_json() {
		$this->ja_cities = json_decode(file_get_contents(FCPATH . 'assets/json/japan_award/jcc_list.json'), true);
	}

	private function load_ku_data_from_json() {
		$this->ja_kus = json_decode(file_get_contents(FCPATH . 'assets/json/japan_award/ku_list.json'), true);
	}

	private function filter_entity_data($entity_data, $postdata) {
		if (($postdata['includedeleted'] ?? null) != null) {
			return $entity_data;
		}

		return array_filter($entity_data, function ($entity) {
			return !(isset($entity['deleted']) && $entity['deleted'] == true);
		});
	}

	private function build_band_key_expr() {
		return "case
			when col_prop_mode = 'SAT' then 'SAT'
			else col_band
		end";
	}

	private function build_mode_key_expr() {
		return "case
			when col_submode = 'DSTAR' then 'DSTAR'
			when col_mode in ('AM', 'FM', 'CW', 'SSB', 'ATV', 'FAX', 'SSTV', 'DIGITALVOICE') then col_mode
			else 'DIGITAL'
		end";
	}

	private function get_qsl_condition_sql($postdata) {
		$qsl = array();
		if (($postdata['qsl'] ?? null) == 1) {
			$qsl[] = "col_qsl_rcvd = 'Y'";
		}
		if (($postdata['lotw'] ?? null) == 1) {
			$qsl[] = "col_lotw_qsl_rcvd = 'Y'";
		}
		if (($postdata['eqsl'] ?? null) == 1) {
			$qsl[] = "col_eqsl_qsl_rcvd = 'Y'";
		}
		if (($postdata['qrz'] ?? null) == 1) {
			$qsl[] = "COL_QRZCOM_QSO_DOWNLOAD_STATUS = 'Y'";
		}
		if (($postdata['clublog'] ?? null) == 1) {
			$qsl[] = "COL_CLUBLOG_QSO_DOWNLOAD_STATUS = 'Y'";
		}
		if (($postdata['dcl'] ?? null) == 1) {
			$qsl[] = "COL_DCL_QSL_RCVD = 'Y'";
		}

		return count($qsl) > 0 ? implode(' or ', $qsl) : '1=0';
	}

	private function get_qsl_confirmed_expr($postdata) {
		return 'case when (' . $this->get_qsl_condition_sql($postdata) . ') then 1 else 0 end';
	}

	private function build_entity_in_list_sql($entity_data) {
		$keys = array_map(function ($key) {
			return $this->db->escape((string) $key);
		}, array_keys($entity_data));

		return implode(',', $keys);
	}

	private function build_entity_query_where_sql($entity_in_list_sql, $postdata, &$bindings, $confirmed_only = false) {
		$band = $postdata['band'] ?? 'All';
		$mode = $postdata['mode'] ?? 'All';
		$prop_mode = $postdata['prop_mode'] ?? 'All';

		$where = array(
			"col_dxcc in ('339')",
			"col_cnty in (" . $entity_in_list_sql . ")",
			"station_id in (" . $this->location_list . ")",
		);
		if ($band != 'All') {
			if ($band === 'SAT') {
				$where[] = "(col_prop_mode = ?)";
				$bindings[] = $band;
			} else {
				$where[] = "(col_band = ?)";
				$bindings[] = $band;
			}
		}
		if ($mode != 'All') {
			$where[] = "(col_mode = ? or col_submode = ?)";
			$bindings[] = $mode;
			$bindings[] = $mode;
		}
		if ($prop_mode != 'All') {
			$where[] = "(col_prop_mode = ?)";
			$bindings[] = $prop_mode;
		}
		if ($confirmed_only) {
			$where[] = '(' . $this->get_qsl_condition_sql($postdata) . ')';
		}

		return implode(" and ", $where);
	}

	private function build_entity_status_base_query($entity_expr, $entity_in_list_sql, $key_col, $postdata, &$bindings) {
		$confirmed_expr = $this->get_qsl_confirmed_expr($postdata);

		$select = array(
			$entity_expr . ' as entity',
			$confirmed_expr . ' as confirmed',
		);
		if ($key_col === 'band') {
			$select[] = $this->build_band_key_expr() . ' as key_col';
		} else if ($key_col === 'mode') {
			$select[] = $this->build_mode_key_expr() . ' as key_col';
		} else {
			$select[] = "'All' as key_col";
			// No additional bindings needed since key_col is a constant in this case
		}
		$select_str = implode(", ", $select);

		$from = $this->config->item('table_name') . " thcv";

		$where_str = $this->build_entity_query_where_sql($entity_in_list_sql, $postdata, $bindings);

		$sql = "select " . $select_str . " from " . $from . " where " . $where_str;

		return $sql;
	}

	private function build_entity_status_max_confirmed_group_by_sql($source_sql) {
		return "select entity, key_col, max(confirmed) as confirmed from (" . $source_sql . ") entity_status group by entity, key_col";
	}

	private function build_entity_status_union_all_sql($left_sql, $right_sql) {
		return $left_sql . " union all " . $right_sql;
	}

	function query_entity_status($postdata, $key_col = "none") {
		$jcc_data = $this->filter_entity_data($this->ja_cities, $postdata);
		$ku_data = $this->filter_entity_data($this->ja_kus, $postdata);
		$jcc_in_list = $this->build_entity_in_list_sql($jcc_data);
		$ku_in_list = $this->build_entity_in_list_sql($ku_data);

		$bindings = array();

		$step_1a = $this->build_entity_status_base_query('col_cnty', $jcc_in_list, $key_col, $postdata, $bindings);
		$step_2a = $this->build_entity_status_max_confirmed_group_by_sql($step_1a);

		$step_1b = $this->build_entity_status_base_query('left(col_cnty, 4)', $ku_in_list, $key_col, $postdata, $bindings);
		$step_2b = $this->build_entity_status_max_confirmed_group_by_sql($step_1b);

		$step_3 = $this->build_entity_status_union_all_sql($step_2a, $step_2b);
		$step_4 = $this->build_entity_status_max_confirmed_group_by_sql($step_3);

		$query = $this->db->query($step_4, $bindings);
		$rows = $query->result_array();

		return $rows;
	}

	function get_jcc_array($bands, $postdata, $entity_status = null) {
		if ($entity_status === null) {
			$entity_status = $this->query_entity_status($postdata, 'band');
		}

		$jcc_list = $this->filter_entity_data($this->ja_cities, $postdata);

		$cities = array();
		// Initializing the array with all cities and bands
		foreach ($jcc_list as $city => $city_data) {
			$cities[$city]['Number'] = $city;
			$cities[$city]['City'] = $city_data['name'];
			$cities[$city]['count'] = 0;
			foreach ($bands as $band) {
				// Sets all to dash to indicate no result
				$cities[$city][$band] = '-';
			}
		}

		foreach ($entity_status as $row) {
			if ($row['confirmed'] == 1) {
				if ($postdata['confirmed'] != NULL) {
					$cities[$row['entity']][$row['key_col']] = 'C';
					$cities[$row['entity']]['count'] += 1;
				}
			} else {
				if ($postdata['worked'] != NULL) {
					$cities[$row['entity']][$row['key_col']] = 'W';
					$cities[$row['entity']]['count'] += 1;
				}
			}
		}

		if ($postdata['notworked'] == NULL) {
			foreach ($cities as $city => $city_data) {
				if ($city_data['count'] == 0) {
					unset($cities[$city]);
				}
			}
		}

		if (!empty($cities)) {
			return $cities;
		} else {
			return 0;
		}
	}


	/*
	 * Function gets worked and confirmed summary on each band on the active stationprofile
	 */
	function get_jcc_summary($bands, $postdata, $entity_status = null) {
		if ($entity_status === null) {
			$entity_status = $this->query_entity_status($postdata, 'band');
		}

		$summary = array(
			'worked' => array(),
			'confirmed' => array(),
		);

		// $worked_by_band = array();
		// $confirmed_by_band = array();
		foreach ($bands as $band) {
			$summary['worked'][$band] = 0;
			$summary['confirmed'][$band] = 0;
		}

		$worked_total = array();
		$confirmed_total = array();

		foreach ($entity_status as $row) {
			$worked_total[$row['entity']] = true;
			$summary['worked'][$row['key_col']] += 1;
			if ($row['confirmed'] == 1) {
				$confirmed_total[$row['entity']] = true;
				$summary['confirmed'][$row['key_col']] += 1;
			}
		}

		$summary['worked']['Total'] = count($worked_total);
		$summary['confirmed']['Total'] = count($confirmed_total);

		// make sure SAT is after Total
		// I don't know why, but the origin design is such.
		if (isset($summary['worked']['SAT']) && isset($summary['confirmed']['SAT'])) {
			$summary_worked_sat = $summary['worked']['SAT'];
			$summary_confirmed_sat = $summary['confirmed']['SAT'];

			unset($summary['worked']['SAT']);
			unset($summary['confirmed']['SAT']);

			$summary['worked']['SAT'] = $summary_worked_sat;
			$summary['confirmed']['SAT'] = $summary_confirmed_sat;
		}

		return $summary;
	}

	function get_jcc_map_array($postdata, $entity_status = null) {
		if ($entity_status === null) {
			$entity_status = $this->query_entity_status($postdata, 'none');
		}

		$jccs = array();
		foreach ($entity_status as $row) {
			$entity = $row['entity'];
			if (!isset($jccs[$entity])) {
				$jccs[$entity] = array(1, 0);
			}

			if ($row['confirmed'] == 1) {
				$jccs[$entity][1] = 1;
			}
		}

		ksort($jccs, SORT_STRING);

		return $jccs;
	}

	private function build_export_entity_source_query($entity_expr, $entity_in_list_sql, $postdata, &$bindings) {
		$select = array(
			$entity_expr . ' as entity',
			'COL_PRIMARY_KEY',
			'COL_CNTY',
			'COL_CALL',
			'COL_TIME_ON',
			'COL_BAND',
			'COL_MODE',
			'COL_PROP_MODE',
		);

		$select_str = implode(", ", $select);

		$from = $this->config->item('table_name') . " thcv";

		$where_str = $this->build_entity_query_where_sql($entity_in_list_sql, $postdata, $bindings, true);

		return 'select ' . $select_str . ' from ' . $from . ' where ' . $where_str;
	}

	function export_qsos($postdata) {
		$jcc_data = $this->filter_entity_data($this->ja_cities, $postdata);
		$ku_data = $this->filter_entity_data($this->ja_kus, $postdata);
		$jcc_in_list = $this->build_entity_in_list_sql($jcc_data);
		$ku_in_list = $this->build_entity_in_list_sql($ku_data);

		$bindings = array();
		$jcc_source_sql = $this->build_export_entity_source_query('col_cnty', $jcc_in_list, $postdata, $bindings);
		$ku_source_sql = $this->build_export_entity_source_query('left(col_cnty, 4)', $ku_in_list, $postdata, $bindings);
		$source_sql = $this->build_entity_status_union_all_sql($jcc_source_sql, $ku_source_sql);

		$ranked_sql = 'select source.*, row_number() over (partition by entity order by COL_TIME_ON asc, COL_PRIMARY_KEY asc) as rn from (' . $source_sql . ') source';
		$final_sql = 'select entity, COL_CNTY, COL_CALL, COL_TIME_ON, COL_BAND, COL_MODE, COL_PROP_MODE from (' . $ranked_sql . ') ranked where rn = 1 order by entity asc';

		$query = $this->db->query($final_sql, $bindings);
		$rows = $query->result_array();

		return $rows;
	}

	function export_jcc($postdata) {
		$rows = $this->export_qsos($postdata);
		$qsos = array();
		foreach ($rows as $row) {
			$entity = $row['entity'];
			$qsos[] = array(
				'call' => $row['COL_CALL'],
				'date' => $row['COL_TIME_ON'],
				'band' => $row['COL_BAND'],
				'mode' => $row['COL_MODE'],
				'prop_mode' => $row['COL_PROP_MODE'],
				'cnty' => $entity,
				'jcc' => $this->ja_cities[$entity]['name'] ?? '',
			);
		}

		return $qsos;
	}

}
?>
