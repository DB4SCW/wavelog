<?php

class Jcc_model extends CI_Model {


	private $location_list=null;
	function __construct() {
		$this->load->library('Genfunctions');
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		$this->location_list = "'".implode("','",$logbooks_locations_array)."'";
		$this->loadJccDataFromJson();
	}

	public $jaCities = array();

	private function loadJccDataFromJson() {
		$this->jaCities = json_decode(file_get_contents(FCPATH . 'assets/json/japan_award/jcc_list.json'), true);
	}

	function get_jcc_array($bands, $postdata) {

		$jccArray = array_keys($this->jaCities);

		$cities = array(); // Used for keeping track of which cities that are not worked
		foreach ($jccArray as $city) {                         // Generating array for use in the table
			$cities[$city]['count'] = 0;                   // Inits each city's count
		}

		$qsl = $this->genfunctions->gen_qsl_from_postdata($postdata);


		foreach ($bands as $band) {
			foreach ($jccArray as $city) {                   // Generating array for use in the table
				$bandJcc[$city]['Number'] = $city;
				$bandJcc[$city]['City'] = $this->jaCities[$city]['name'];
				$bandJcc[$city][$band] = '-';                  // Sets all to dash to indicate no result
			}

			if ($postdata['worked'] != NULL) {
				$jccBand = $this->getJccWorked($this->location_list, $band, $postdata);
				foreach ($jccBand as $line) {
					$bandJcc[$line->col_cnty][$band] = '<div class="bg-danger awardsBgWarning"><a href=\'javascript:displayContacts("' . $line->col_cnty . '","' . $band . '","All","All","'. $postdata['mode'] . '","JCC", "")\'>W</a></div>';
					$cities[$line->col_cnty]['count']++;
				}
			}
			if ($postdata['confirmed'] != NULL) {
				$jccBand = $this->getJccConfirmed($this->location_list, $band, $postdata);
				foreach ($jccBand as $line) {
					$bandJcc[$line->col_cnty][$band] = '<div class="bg-success awardsBgSuccess"><a href=\'javascript:displayContacts("' . $line->col_cnty . '","' . $band . '","All","All","'. $postdata['mode'] . '","JCC", "'.$qsl.'")\'>C</a></div>';
					$cities[$line->col_cnty]['count']++;
				}
			}
		}

		// We want to remove the worked cities in the list, since we do not want to display them
		if ($postdata['worked'] == NULL) {
			$jccBand = $this->getJccWorked($this->location_list, $postdata['band'], $postdata);
			foreach ($jccBand as $line) {
				unset($bandJcc[$line->col_cnty]);
			}
		}

		// We want to remove the confirmed cities in the list, since we do not want to display them
		if ($postdata['confirmed'] == NULL) {
			$wasBand = $this->getJccConfirmed($this->location_list, $postdata['band'], $postdata);
			foreach ($wasBand as $line) {
				unset($bandJcc[$line->col_cnty]);
			}
		}

		if ($postdata['notworked'] == NULL) {
			if (isset($bandJcc)) {
				foreach ($jccArray as $city) {
					if ($cities[$city]['count'] == 0) {
						unset($bandJcc[$city]);
					};
				}
			}
		}

		if (isset($bandJcc)) {
			return $bandJcc;
		} else {
			return 0;
		}
	}

	/*
	 * Function returns all worked, but not confirmed cities
	 * $postdata contains data from the form, in this case Lotw or QSL are used
	 */
	function getJccWorked($location_list, $band, $postdata) {
		$bindings=[];
		$sql = "SELECT distinct col_cnty FROM " . $this->config->item('table_name') . " thcv
			where station_id in (" . $location_list . ")";

		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}

		$sql .= $this->addStateToQuery();
		$sql .= $this->genfunctions->addBandToQuery($band,$bindings);
		$sql .= " and not exists (select 1 from ". $this->config->item('table_name') .
			" where station_id in (". $location_list . ")" .
			" and col_cnty = thcv.col_cnty";

		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}

		$sql .= $this->genfunctions->addBandToQuery($band,$bindings);
		$sql .= $this->genfunctions->addQslToQuery($postdata);
		$sql .= $this->addStateToQuery();
		$sql .= ")";

		$query = $this->db->query($sql,$bindings);

		return $query->result();
	}

	/*
	 * Function returns all confirmed cities on given band and on LoTW or QSL
	 * $postdata contains data from the form, in this case Lotw or QSL are used
	 */
	function getJccConfirmed($location_list, $band, $postdata) {
		$bindings=[];
		$sql = "SELECT distinct col_cnty FROM " . $this->config->item('table_name') . " thcv
			where station_id in (" . $location_list . ")";

		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}

		$sql .= $this->addStateToQuery();

		$sql .= $this->genfunctions->addBandToQuery($band,$bindings);

		$sql .= $this->genfunctions->addQslToQuery($postdata);

		$query = $this->db->query($sql,$bindings);

		return $query->result();
	}


	/*
	 * Function gets worked and confirmed summary on each band on the active stationprofile
	 */
	function get_jcc_summary($bands, $postdata) {
		foreach ($bands as $band) {
			if ($band != 'SAT') {
				$worked = $this->getSummaryByBand($band, $postdata, $this->location_list);
				$confirmed = $this->getSummaryByBandConfirmed($band, $postdata, $this->location_list);
				$jccSummary['worked'][$band] = $worked[0]->count;
				$jccSummary['confirmed'][$band] = $confirmed[0]->count;
			}
		}

		$workedTotal = $this->getSummaryByBand($postdata['band'], $postdata, $this->location_list);
		$confirmedTotal = $this->getSummaryByBandConfirmed($postdata['band'], $postdata, $this->location_list);

		$jccSummary['worked']['Total'] = $workedTotal[0]->count;
		$jccSummary['confirmed']['Total'] = $confirmedTotal[0]->count;

		if (in_array('SAT', $bands)) {
			$worked = $this->getSummaryByBand('SAT', $postdata, $this->location_list);
			$confirmed = $this->getSummaryByBandConfirmed('SAT', $postdata, $this->location_list);
			$jccSummary['worked']['SAT'] = $worked[0]->count;
			$jccSummary['confirmed']['SAT'] = $confirmed[0]->count;
		}

		return $jccSummary;
	}

	function getSummaryByBand($band, $postdata, $location_list) {
		$bindings=[];
		$sql = "SELECT count(distinct thcv.col_cnty) as count FROM " . $this->config->item('table_name') . " thcv";
		$sql .= " where station_id in (" . $location_list . ")";

		if ($band == 'SAT') {
			$sql .= " and thcv.col_prop_mode = ?";
			$bindings[]=$band;
		} else if ($band == 'All') {
			$this->load->model('bands');
			$bandslots = $this->bands->get_worked_bands('was');
			$bandslots_list = "'".implode("','",$bandslots)."'";

			$sql .= " and thcv.col_band in (" . $bandslots_list . ")" .
				" and thcv.col_prop_mode !='SAT'";
		} else {
			$sql .= " and thcv.col_prop_mode !='SAT'";
			$sql .= " and thcv.col_band = ?";
			$bindings[]=$band;
		}

		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}

		$sql .= $this->addStateToQuery();

		$query = $this->db->query($sql,$bindings);

		return $query->result();
	}

	function getSummaryByBandConfirmed($band, $postdata, $location_list) {
		$bindings=[];
		$sql = "SELECT count(distinct thcv.col_cnty) as count FROM " . $this->config->item('table_name') . " thcv";
		$sql .= " where station_id in (" . $location_list . ")";

		if ($band == 'SAT') {
			$sql .= " and thcv.col_prop_mode = ?";
			$bindings[]=$band;
		} else if ($band == 'All') {
			$this->load->model('bands');
			$bandslots = $this->bands->get_worked_bands('was');
			$bandslots_list = "'".implode("','",$bandslots)."'";

			$sql .= " and thcv.col_band in (" . $bandslots_list . ")" .
				" and thcv.col_prop_mode !='SAT'";
		} else {
			$sql .= " and thcv.col_prop_mode !='SAT'";
			$sql .= " and thcv.col_band = ?";
			$bindings[]=$band;
		}

		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}

		$sql .= $this->genfunctions->addQslToQuery($postdata);
		$sql .= $this->addStateToQuery();
		$query = $this->db->query($sql,$bindings);
		return $query->result();
	}


	function addStateToQuery() {
		if (empty($this->jaCities)) {
			return " and 1 = 0";
		}

		$keys = array_map(function ($key) {
			return $this->db->escape((string) $key);
		}, array_keys($this->jaCities));

		$sql = '';
		$sql .= " and COL_DXCC in ('339')";
		$sql .= " and (COL_CNTY LIKE '____' OR COL_CNTY LIKE '10____')";
		$sql .= " and COL_CNTY in (" . implode(',', $keys) . ")";
		return $sql;
	}

	function exportJcc($postdata) {
		$bindings=[];
		$sql = "SELECT distinct col_cnty FROM " . $this->config->item('table_name') . " thcv
			where station_id in (" . $this->location_list . ")";

		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}

		$sql .= $this->addStateToQuery();
		$sql .= $this->genfunctions->addBandToQuery($postdata['band'],$bindings);
		$sql .= $this->genfunctions->addQslToQuery($postdata);
		$sql .= ' ORDER BY COL_CNTY ASC';

		$query = $this->db->query($sql,$bindings);

		$jccs = array();
		foreach($query->result() as $line) {
			$jccs[] = $line->col_cnty;
		}
		$qsos = array();
		foreach($jccs as $jcc) {
			$qso = $this->getFirstQso($this->location_list, $jcc, $postdata);
			$qsos[] = array('call' => $qso[0]->COL_CALL, 'date' => $qso[0]->COL_TIME_ON, 'band' => $qso[0]->COL_BAND, 'mode' => $qso[0]->COL_MODE, 'prop_mode' => $qso[0]->COL_PROP_MODE, 'cnty' => $qso[0]->COL_CNTY, 'jcc' => $this->jaCities[$qso[0]->COL_CNTY]['name']);
		}

		return $qsos;
	}

	function getFirstQso($location_list, $jcc, $postdata) {
		$bindings=[];
		$sql = 'SELECT COL_CNTY, COL_CALL, COL_TIME_ON, COL_BAND, COL_MODE, COL_PROP_MODE FROM '.$this->config->item('table_name').' t1
			WHERE station_id in ('.$location_list.')';
		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}
		$sql .= $this->addStateToQuery();
		$sql .= $this->genfunctions->addBandToQuery($postdata['band'],$bindings);
		$sql .= $this->genfunctions->addQslToQuery($postdata);
		$sql .= ' AND COL_CNTY = ?';
		$bindings[]=$jcc;
		$sql .= ' ORDER BY COL_TIME_ON ASC LIMIT 1';
		$query = $this->db->query($sql,$bindings);
		return $query->result();
	}

	function fetch_jcc_wkd($postdata) {
		$bindings=[];
		$sql = 'SELECT DISTINCT `COL_CNTY` FROM '.$this->config->item('table_name').' WHERE 1
			and station_id in ('.$this->location_list.')';
		$sql .= $this->addStateToQuery();
		$sql .= $this->genfunctions->addBandToQuery($postdata['band'],$bindings);
		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}
		$sql .= ' ORDER BY COL_CNTY ASC';
		$query = $this->db->query($sql,$bindings);
		return $query->result();
	}

	function fetch_jcc_cnfm($postdata) {
		$bindings=[];
		$sql = 'SELECT DISTINCT `COL_CNTY` FROM '.$this->config->item('table_name').' WHERE 1
			and station_id in ('.$this->location_list.')';
		$sql .= $this->addStateToQuery();
		$sql .= $this->genfunctions->addBandToQuery($postdata['band'],$bindings);
		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}
		$sql .= $this->genfunctions->addQslToQuery($postdata);
		$sql .= ' ORDER BY COL_CNTY ASC';
		$query = $this->db->query($sql,$bindings);
		return $query->result();
	}

}
?>
