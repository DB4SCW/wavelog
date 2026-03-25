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

	public $jaCities = array();
	public $jaKus = array();

	private function load_jcc_data_from_json() {
		$this->jaCities = json_decode(file_get_contents(FCPATH . 'assets/json/japan_award/jcc_list.json'), true);
	}

	private function load_ku_data_from_json() {
		$this->jaKus = json_decode(file_get_contents(FCPATH . 'assets/json/japan_award/ku_list.json'), true);
	}

	private function get_entity_data_for_postdata($entity_data, $postdata) {
		if (($postdata['includedeleted'] ?? null) != null) {
			return $entity_data;
		}

		return array_filter($entity_data, function ($entity) {
			return !(isset($entity['deleted']) && $entity['deleted'] === true);
		});
	}

	private function build_mode_key_expr() {
		return "case
			when col_submode = 'DSTAR' then 'DSTAR'
			when col_mode in ('AM', 'FM', 'CW', 'SSB', 'ATV', 'FAX', 'SSTV', 'DIGITALVOICE') then col_mode
			else 'DIGITAL'
		end";
	}

	private function get_qsl_confirmed_expr($postdata) {
		$qsl = array();
		if (($postdata['qsl'] ?? '') != '') {
			$qsl[] = "col_qsl_rcvd = 'Y'";
		}
		if (($postdata['lotw'] ?? '') != '') {
			$qsl[] = "col_lotw_qsl_rcvd = 'Y'";
		}
		if (($postdata['eqsl'] ?? '') != '') {
			$qsl[] = "col_eqsl_qsl_rcvd = 'Y'";
		}
		if (($postdata['qrz'] ?? '') != '') {
			$qsl[] = "COL_QRZCOM_QSO_DOWNLOAD_STATUS = 'Y'";
		}
		if (($postdata['clublog'] ?? '') != '') {
			$qsl[] = "COL_CLUBLOG_QSO_DOWNLOAD_STATUS = 'Y'";
		}
		if (($postdata['dcl'] ?? '') != '') {
			$qsl[] = "COL_DCL_QSL_RCVD = 'Y'";
		}

		$condition_sql = count($qsl) > 0 ? implode(' or ', $qsl) : '1=0';

		return 'case when (' . $condition_sql . ') then 1 else 0 end';
	}

	private function build_entity_in_list_sql($entity_data) {
		$keys = array_map(function ($key) {
			return $this->db->escape((string) $key);
		}, array_keys($entity_data));

		return implode(',', $keys);
	}

	private function build_entity_status_base_query($entity_expr, $entity_in_list_sql, $key_col, $postdata, &$bindings) {
		$confirmed_expr = $this->get_qsl_confirmed_expr($postdata);
		$band = $postdata['band'] ?? 'All';
		$mode = $postdata['mode'] ?? 'All';
		$prop_mode = $postdata['prop_mode'] ?? 'All';

		$select = array(
			$entity_expr . ' as entity',
			$confirmed_expr . ' as confirmed',
		);
		if ($key_col === 'band') {
			$select[] = 'col_band as key_col';
		} else if ($key_col === 'mode') {
			$select[] = $this->build_mode_key_expr() . ' as key_col';
		} else {
			$select[] = "'All' as key_col";
			// No additional bindings needed since key_col is a constant in this case
		}
		$select_str = implode(", ", $select);

		$from = $this->config->item('table_name') . " thcv";

		$where = array(
			"col_dxcc in ('339')",
			"col_cnty in (" . $entity_in_list_sql . ")",
			"station_id in (" . $this->location_list . ")",
		);
		if ($band != 'All') {
			if ($band == 'SAT') {
				$where[] = "(col_prop_mode = ?)";
			} else {
				$where[] = "(col_band = ?)";
			}
			$bindings[] = $band;
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
		$where_str = implode(" and ", $where);

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
		$jcc_data = $this->get_entity_data_for_postdata($this->jaCities, $postdata);
		$ku_data = $this->get_entity_data_for_postdata($this->jaKus, $postdata);
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

	function get_jcc_array($bands, $postdata) {

		$jcc_array = array_keys($this->get_entity_data_for_postdata($this->jaCities, $postdata));
		$prop_mode = $postdata['prop_mode'] ?? 'All';

		$cities = array(); // Used for keeping track of which cities that are not worked
		foreach ($jcc_array as $city) {                         // Generating array for use in the table
			$cities[$city]['count'] = 0;                   // Inits each city's count
		}

		$qsl = $this->genfunctions->gen_qsl_from_postdata($postdata);


		foreach ($bands as $band) {
			foreach ($jcc_array as $city) {                   // Generating array for use in the table
				$band_jcc[$city]['Number'] = $city;
				$band_jcc[$city]['City'] = $this->jaCities[$city]['name'];
				$band_jcc[$city][$band] = '-';                  // Sets all to dash to indicate no result
			}

			if ($postdata['worked'] != NULL) {
				$jcc_band = $this->get_jcc_worked($this->location_list, $band, $postdata);
				foreach ($jcc_band as $line) {
					$band_jcc[$line->col_cnty][$band] = '<div class="bg-danger awardsBgWarning"><a href=\'javascript:displayContacts("' . $line->col_cnty . '","' . $band . '","All","All","'. $postdata['mode'] . '","JCC", "")\'>W</a></div>';
					$cities[$line->col_cnty]['count']++;
				}
			}
			if ($postdata['confirmed'] != NULL) {
				$jcc_band = $this->get_jcc_confirmed($this->location_list, $band, $postdata);
				foreach ($jcc_band as $line) {
					$band_jcc[$line->col_cnty][$band] = '<div class="bg-success awardsBgSuccess"><a href=\'javascript:displayContacts("' . $line->col_cnty . '","' . $band . '","All","All","'. $postdata['mode'] . '","JCC", "'.$qsl.'")\'>C</a></div>';
					$cities[$line->col_cnty]['count']++;
				}
			}
		}

		// We want to remove the worked cities in the list, since we do not want to display them
		if ($postdata['worked'] == NULL) {
			$jcc_band = $this->get_jcc_worked($this->location_list, $postdata['band'], $postdata);
			foreach ($jcc_band as $line) {
				unset($band_jcc[$line->col_cnty]);
			}
		}

		// We want to remove the confirmed cities in the list, since we do not want to display them
		if ($postdata['confirmed'] == NULL) {
			$was_band = $this->get_jcc_confirmed($this->location_list, $postdata['band'], $postdata);
			foreach ($was_band as $line) {
				unset($band_jcc[$line->col_cnty]);
			}
		}

		if ($postdata['notworked'] == NULL) {
			if (isset($band_jcc)) {
				foreach ($jcc_array as $city) {
					if ($cities[$city]['count'] == 0) {
						unset($band_jcc[$city]);
					};
				}
			}
		}

		if (isset($band_jcc)) {
			return $band_jcc;
		} else {
			return 0;
		}
	}

	/*
	 * Function returns all worked, but not confirmed cities
	 * $postdata contains data from the form, in this case Lotw or QSL are used
	 */
	function get_jcc_worked($location_list, $band, $postdata) {
		$query_postdata = $postdata;
		$query_postdata['band'] = $band;
		$status_rows = $this->query_entity_status($query_postdata, 'none');

		$result = array();
		foreach ($status_rows as $row) {
			if ((int) $row['confirmed'] === 0) {
				$line = new stdClass();
				$line->col_cnty = $row['entity'];
				$result[] = $line;
			}
		}

		return $result;
	}

	/*
	 * Function returns all confirmed cities on given band and on LoTW or QSL
	 * $postdata contains data from the form, in this case Lotw or QSL are used
	 */
	function get_jcc_confirmed($location_list, $band, $postdata) {
		$query_postdata = $postdata;
		$query_postdata['band'] = $band;
		$status_rows = $this->query_entity_status($query_postdata, 'none');

		$result = array();
		foreach ($status_rows as $row) {
			if ((int) $row['confirmed'] === 1) {
				$line = new stdClass();
				$line->col_cnty = $row['entity'];
				$result[] = $line;
			}
		}

		return $result;
	}


	/*
	 * Function gets worked and confirmed summary on each band on the active stationprofile
	 */
	function get_jcc_summary($bands, $postdata) {
		foreach ($bands as $band) {
			if ($band != 'SAT') {
				$worked = $this->get_summary_by_band($band, $postdata, $this->location_list);
				$confirmed = $this->get_summary_by_band_confirmed($band, $postdata, $this->location_list);
				$jcc_summary['worked'][$band] = $worked[0]->count;
				$jcc_summary['confirmed'][$band] = $confirmed[0]->count;
			}
		}

		$worked_total = $this->get_summary_by_band($postdata['band'], $postdata, $this->location_list);
		$confirmed_total = $this->get_summary_by_band_confirmed($postdata['band'], $postdata, $this->location_list);

		$jcc_summary['worked']['Total'] = $worked_total[0]->count;
		$jcc_summary['confirmed']['Total'] = $confirmed_total[0]->count;

		if (in_array('SAT', $bands)) {
			$worked = $this->get_summary_by_band('SAT', $postdata, $this->location_list);
			$confirmed = $this->get_summary_by_band_confirmed('SAT', $postdata, $this->location_list);
			$jcc_summary['worked']['SAT'] = $worked[0]->count;
			$jcc_summary['confirmed']['SAT'] = $confirmed[0]->count;
		}

		return $jcc_summary;
	}

	function get_summary_by_band($band, $postdata, $location_list) {
		$bindings=[];
		$prop_mode = $postdata['prop_mode'] ?? 'All';
		$sql = "SELECT count(distinct thcv.col_cnty) as count FROM " . $this->config->item('table_name') . " thcv";
		$sql .= " where station_id in (" . $location_list . ")";

		if ($band == 'SAT') {
			$sql .= " and thcv.col_prop_mode = ?";
			$bindings[]=$band;
		} else if ($band == 'All') {
			if ($prop_mode == 'All') {
				$this->load->model('bands');
				$bandslots = $this->bands->get_worked_bands('was');
				$bandslots_list = "'".implode("','",$bandslots)."'";

				$sql .= " and thcv.col_band in (" . $bandslots_list . ")" .
					" and thcv.col_prop_mode !='SAT'";
			}
		} else {
			if ($prop_mode == 'All') {
				$sql .= " and thcv.col_prop_mode !='SAT'";
			}
			$sql .= " and thcv.col_band = ?";
			$bindings[]=$band;
		}

		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}

		if ($prop_mode != 'All') {
			$sql .= " and col_prop_mode = ?";
			$bindings[] = $prop_mode;
		}

		$sql .= $this->add_state_to_query($postdata);

		$query = $this->db->query($sql,$bindings);

		return $query->result();
	}

	function get_summary_by_band_confirmed($band, $postdata, $location_list) {
		$bindings=[];
		$prop_mode = $postdata['prop_mode'] ?? 'All';
		$sql = "SELECT count(distinct thcv.col_cnty) as count FROM " . $this->config->item('table_name') . " thcv";
		$sql .= " where station_id in (" . $location_list . ")";

		if ($band == 'SAT') {
			$sql .= " and thcv.col_prop_mode = ?";
			$bindings[]=$band;
		} else if ($band == 'All') {
			if ($prop_mode == 'All') {
				$this->load->model('bands');
				$bandslots = $this->bands->get_worked_bands('was');
				$bandslots_list = "'".implode("','",$bandslots)."'";

				$sql .= " and thcv.col_band in (" . $bandslots_list . ")" .
					" and thcv.col_prop_mode !='SAT'";
			}
		} else {
			if ($prop_mode == 'All') {
				$sql .= " and thcv.col_prop_mode !='SAT'";
			}
			$sql .= " and thcv.col_band = ?";
			$bindings[]=$band;
		}

		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}

		if ($prop_mode != 'All') {
			$sql .= " and col_prop_mode = ?";
			$bindings[] = $prop_mode;
		}

		$sql .= $this->genfunctions->addQslToQuery($postdata);
		$sql .= $this->add_state_to_query($postdata);
		$query = $this->db->query($sql,$bindings);
		return $query->result();
	}


	function add_state_to_query($postdata = array()) {
		$entity_data = $this->get_entity_data_for_postdata($this->jaCities, $postdata);

		if (empty($entity_data)) {
			return " and 1 = 0";
		}

		$keys = array_map(function ($key) {
			return $this->db->escape((string) $key);
		}, array_keys($entity_data));

		$sql = '';
		$sql .= " and COL_DXCC in ('339')";
		$sql .= " and (COL_CNTY LIKE '____' OR COL_CNTY LIKE '10____')";
		$sql .= " and COL_CNTY in (" . implode(',', $keys) . ")";
		return $sql;
	}

	function export_jcc($postdata) {
		$bindings=[];
		$prop_mode = $postdata['prop_mode'] ?? 'All';
		$sql = "SELECT distinct col_cnty FROM " . $this->config->item('table_name') . " thcv
			where station_id in (" . $this->location_list . ")";

		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}

		$sql .= $this->add_state_to_query($postdata);
		if ($postdata['band'] != 'All') {
			if ($postdata['band'] == 'SAT') {
				$sql .= " and col_prop_mode = ?";
			} else {
				$sql .= " and col_prop_mode !='SAT'";
				$sql .= " and col_band = ?";
			}
			$bindings[] = $postdata['band'];
		}
		if ($prop_mode != 'All') {
			$sql .= " and col_prop_mode = ?";
			$bindings[] = $prop_mode;
		}
		$sql .= $this->genfunctions->addQslToQuery($postdata);
		$sql .= ' ORDER BY COL_CNTY ASC';

		$query = $this->db->query($sql,$bindings);

		$jccs = array();
		foreach($query->result() as $line) {
			$jccs[] = $line->col_cnty;
		}
		$qsos = array();
		foreach($jccs as $jcc) {
			$qso = $this->get_first_qso($this->location_list, $jcc, $postdata);
			$qsos[] = array('call' => $qso[0]->COL_CALL, 'date' => $qso[0]->COL_TIME_ON, 'band' => $qso[0]->COL_BAND, 'mode' => $qso[0]->COL_MODE, 'prop_mode' => $qso[0]->COL_PROP_MODE, 'cnty' => $qso[0]->COL_CNTY, 'jcc' => $this->jaCities[$qso[0]->COL_CNTY]['name']);
		}

		return $qsos;
	}

	function get_first_qso($location_list, $jcc, $postdata) {
		$bindings=[];
		$prop_mode = $postdata['prop_mode'] ?? 'All';
		$sql = 'SELECT COL_CNTY, COL_CALL, COL_TIME_ON, COL_BAND, COL_MODE, COL_PROP_MODE FROM '.$this->config->item('table_name').' t1
			WHERE station_id in ('.$location_list.')';
		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}
		$sql .= $this->add_state_to_query($postdata);
		if ($postdata['band'] != 'All') {
			if ($postdata['band'] == 'SAT') {
				$sql .= " and col_prop_mode = ?";
			} else {
				$sql .= " and col_prop_mode !='SAT'";
				$sql .= " and col_band = ?";
			}
			$bindings[] = $postdata['band'];
		}
		if ($prop_mode != 'All') {
			$sql .= " and col_prop_mode = ?";
			$bindings[] = $prop_mode;
		}
		$sql .= $this->genfunctions->addQslToQuery($postdata);
		$sql .= ' AND COL_CNTY = ?';
		$bindings[]=$jcc;
		$sql .= ' ORDER BY COL_TIME_ON ASC LIMIT 1';
		$query = $this->db->query($sql,$bindings);
		return $query->result();
	}

	function fetch_jcc_wkd($postdata) {
		$status_rows = $this->query_entity_status($postdata, 'none');
		$result = array();

		foreach ($status_rows as $row) {
			$line = new stdClass();
			$line->COL_CNTY = $row['entity'];
			$result[] = $line;
		}

		usort($result, function ($a, $b) {
			return strcmp((string) $a->COL_CNTY, (string) $b->COL_CNTY);
		});

		return $result;
	}

	function fetch_jcc_cnfm($postdata) {
		$status_rows = $this->query_entity_status($postdata, 'none');
		$result = array();

		foreach ($status_rows as $row) {
			if ((int) $row['confirmed'] === 1) {
				$line = new stdClass();
				$line->COL_CNTY = $row['entity'];
				$result[] = $line;
			}
		}

		usort($result, function ($a, $b) {
			return strcmp((string) $a->COL_CNTY, (string) $b->COL_CNTY);
		});

		return $result;
	}

}
?>
