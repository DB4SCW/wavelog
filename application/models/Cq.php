<?php

class CQ extends CI_Model{

	function __construct() {
		$this->load->library('Genfunctions');
	}

	function get_cq_array($bands, $postdata, $location_list) {
		$cqZ = array(); // Used for keeping track of which states that are not worked

		for ($i = 1; $i <= 40; $i++) {
			$cqZ[$i]['count'] = 0;                   // Inits each cq zone's count
		}

		$qsl = $this->genfunctions->gen_qsl_from_postdata($postdata);

		foreach ($bands as $band) {
			for ($i = 1; $i <= 40; $i++) {
				$bandCq[$i][$band] = '-';                  // Sets all to dash to indicate no result
			}

			if ($postdata['worked'] != NULL) {
				$cqBand = $this->getCQWorked($location_list, $band, $postdata);
				foreach ($cqBand as $line) {
					$bandCq[$line->col_cqz][$band] = '<div class="bg-danger awardsBgDanger"><a href=\'javascript:displayContacts("' . str_replace("&", "%26", $line->col_cqz) . '","' . $band . '","All", "All","'. $postdata['mode'] . '","CQZone","")\'>W</a></div>';
					$cqZ[$line->col_cqz]['count']++;
				}
			}
			if ($postdata['confirmed'] != NULL) {
				$cqBand = $this->getCQConfirmed($location_list, $band, $postdata);
				foreach ($cqBand as $line) {
					$bandCq[$line->col_cqz][$band] = '<div class="bg-success awardsBgSuccess"><a href=\'javascript:displayContacts("' . str_replace("&", "%26", $line->col_cqz) . '","' . $band . '","All", "All","'. $postdata['mode'] . '","CQZone","'.$qsl.'")\'>C</a></div>';
					$cqZ[$line->col_cqz]['count']++;
				}
			}
		}

		// We want to remove the worked zones in the list, since we do not want to display them
		if ($postdata['worked'] == NULL) {
			$cqBand = $this->getCQWorked($location_list, $postdata['band'], $postdata);
			foreach ($cqBand as $line) {
				unset($bandCq[$line->col_cqz]);
			}
		}

		// We want to remove the confirmed zones in the list, since we do not want to display them
		if ($postdata['confirmed'] == NULL) {
			$cqBand = $this->getCQConfirmed($location_list, $postdata['band'], $postdata);
			foreach ($cqBand as $line) {
				unset($bandCq[$line->col_cqz]);
			}
		}

		if ($postdata['notworked'] == NULL) {
			for ($i = 1; $i <= 40; $i++) {
				if ($cqZ[$i]['count'] == 0) {
					unset($bandCq[$i]);
				};
			}
		}

		if (isset($bandCq)) {
			return $bandCq;
		} else {
			return 0;
		}
	}

	/*
	 * Function returns all worked, but not confirmed states
	 * $postdata contains data from the form, in this case Lotw or QSL are used
	 */
	function getCQWorked($location_list, $band, $postdata) {
		$bindings=[];
		$sql = "SELECT distinct col_cqz FROM " . $this->config->item('table_name') . " thcv
			where station_id in (" . $location_list . ") and col_cqz <= 40 and col_cqz <> ''";

		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}

		$sql .= $this->genfunctions->addBandToQuery($band,$bindings);

		$sql .= " and not exists (select 1 from " . $this->config->item('table_name') .
			" where station_id in (" . $location_list .
			") and col_cqz = thcv.col_cqz and col_cqz <> '' ";

		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}

		$sql .= $this->genfunctions->addBandToQuery($band,$bindings);

		$sql .= $this->genfunctions->addQslToQuery($postdata);

		$sql .= ")";

		$query = $this->db->query($sql,$bindings);

		return $query->result();
	}

	/*
	 * Function returns all confirmed states on given band and on LoTW or QSL
	 * $postdata contains data from the form, in this case Lotw or QSL are used
	 */
	function getCQConfirmed($location_list, $band, $postdata) {
		$bindings=[];
		$sql = "SELECT distinct col_cqz FROM " . $this->config->item('table_name') . " thcv
			where station_id in (" . $location_list . ") and col_cqz <= 40 and col_cqz <> ''";

		if ($postdata['mode'] != 'All') {
			$sql .= " and (col_mode = ? or col_submode = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}

		$sql .= $this->genfunctions->addBandToQuery($band,$bindings);

		$sql .= $this->genfunctions->addQslToQuery($postdata);

		$query = $this->db->query($sql,$bindings);

		return $query->result();
	}


	/*
	 * Function gets worked and confirmed summary on each band on the active stationprofile
	 */
	function get_cq_summary($bands, $postdata, $location_list) {
		foreach ($bands as $band) {
			$worked = $this->getSummaryByBand($band, $postdata, $location_list);
			$confirmed = $this->getSummaryByBandConfirmed($band, $postdata, $location_list);
			$cqSummary['worked'][$band] = $worked[0]->count;
			$cqSummary['confirmed'][$band] = $confirmed[0]->count;
		}

		$workedTotal = $this->getSummaryByBand($postdata['band'], $postdata, $location_list);
		$confirmedTotal = $this->getSummaryByBandConfirmed($postdata['band'], $postdata, $location_list);

		$cqSummary['worked']['Total'] = $workedTotal[0]->count;
		$cqSummary['confirmed']['Total'] = $confirmedTotal[0]->count;

		return $cqSummary;
	}

	function getSummaryByBand($band, $postdata, $location_list) {
		$bindings=[];
		$sql = "SELECT count(distinct thcv.col_cqz) as count FROM " . $this->config->item('table_name') . " thcv";

		$sql .= " where station_id in (" . $location_list . ') and col_cqz <= 40 and col_cqz > 0';

		if ($band == 'SAT') {
			$sql .= " and thcv.col_prop_mode = ?";
			$bindings[]=$band;
		} else if ($band == 'All') {
			$this->load->model('bands');

			$bandslots = $this->bands->get_worked_bands('cq');

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

		$query = $this->db->query($sql,$bindings);

		return $query->result();
	}

	function getSummaryByBandConfirmed($band, $postdata, $location_list){
		$bindings=[];
		$sql = "SELECT count(distinct thcv.col_cqz) as count FROM " . $this->config->item('table_name') . " thcv";

		$sql .= " where station_id in (" . $location_list . ') and col_cqz <= 40 and col_cqz > 0';

		if ($band == 'SAT') {
			$sql .= " and thcv.col_prop_mode = ?";
			$bindings[]=$band;
		} else if ($band == 'All') {
			$this->load->model('bands');

			$bandslots = $this->bands->get_worked_bands('cq');

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

		$query = $this->db->query($sql,$bindings);

		return $query->result();
	}

}
