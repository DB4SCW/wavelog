<?php

class Logbook_model extends CI_Model {

	private $station_result = [];
	public function __construct() {
		$this->oop_populate_modes();
	}

	private $oop_modes = [];
	private function oop_populate_modes() {
		$r = $this->db->get('adif_modes');
		foreach ($r->result_array() as $row) {
			$this->oop_modes[$row['submode']][] = ($row['mode'] ?? '');
		}
	}

	/* Add QSO to Logbook */
	function create_qso() {

		$callsign = trim(str_replace('Ø', '0', $this->input->post('callsign')));
		// Join date+time
		$datetime = date("Y-m-d", strtotime($this->input->post('start_date'))) . " " . $this->input->post('start_time');
		if (($this->input->post('end_time') ?? '') != '') {
			$datetime_off = date("Y-m-d", strtotime($this->input->post('start_date'))) . " " . $this->input->post('end_time');
			// if time off < time on, and time off is on 00:xx >> add 1 day (concidering start and end are between 23:00 and 00:59) //
			$_tmp_datetime_off = strtotime($datetime_off);
			if (($_tmp_datetime_off < strtotime($datetime)) && (substr($this->input->post('end_time'), 0, 2) == "00")) {
				$datetime_off = date("Y-m-d H:i:s", ($_tmp_datetime_off + 60 * 60 * 24));
			}
		} else {
			$datetime_off = $datetime;
		}
		if ($this->input->post('prop_mode') != null) {
			$prop_mode = $this->input->post('prop_mode');
		} else {
			$prop_mode = "";
		}

		if ($this->input->post('email')) {
			$email = $this->input->post('email',TRUE);
		} else {
			$email = '';
		}

		if ($this->input->post('region')) {
			$region = $this->input->post('region',TRUE);
		} else {
			$region = '';
		}

		if ($this->input->post('sat_name')) {
			$prop_mode = "SAT";
		}

		// Contest exchange, need to separate between serial and other type of exchange
		if ($this->input->post('exchangetype')) {
			switch ($this->input->post('exchangetype')) {
				case 'Exchange':
					$srx_string = $this->input->post('exch_rcvd') == '' ? null : $this->input->post('exch_rcvd');
					$stx_string = $this->input->post('exch_sent') == '' ? null : $this->input->post('exch_sent');
					$srx = null;
					$stx = null;
					break;
				case 'Gridsquare':
					$srx_string = null;
					$stx_string = null;
					$srx = null;
					$stx = null;
					break;
				case 'Serial':
					$srx = $this->input->post('exch_serial_r') == '' ? null : $this->input->post('exch_serial_r');
					$stx = $this->input->post('exch_serial_s') == '' ? null : $this->input->post('exch_serial_s');
					$srx_string = null;
					$stx_string = null;
					break;
				case 'Serialexchange':
					$srx_string = $this->input->post('exch_rcvd') == '' ? null : $this->input->post('exch_rcvd');
					$stx_string = $this->input->post('exch_sent') == '' ? null : $this->input->post('exch_sent');
					$srx = $this->input->post('exch_serial_r') == '' ? null : $this->input->post('exch_serial_r');
					$stx = $this->input->post('exch_serial_s') == '' ? null : $this->input->post('exch_serial_s');
					break;
				case 'Serialgridsquare':
					$srx = $this->input->post('exch_serial_r') == '' ? null : $this->input->post('exch_serial_r');
					$stx = $this->input->post('exch_serial_s') == '' ? null : $this->input->post('exch_serial_s');
					$srx_string = null;
					$stx_string = null;
					break;
				case 'SerialGridExchange':
					$srx_string = $this->input->post('exch_rcvd') == '' ? null : $this->input->post('exch_rcvd');
					$stx_string = $this->input->post('exch_sent') == '' ? null : $this->input->post('exch_sent');
					$srx = $this->input->post('exch_serial_r') == '' ? null : $this->input->post('exch_serial_r');
					$stx = $this->input->post('exch_serial_s') == '' ? null : $this->input->post('exch_serial_s');
					break;
				case 'None':
					$srx_string = null;
					$stx_string = null;
					$srx = null;
					$stx = null;
					break;
			}

			if ($srx_string !== null) $srx_string = trim(xss_clean($srx_string));
			if ($stx_string !== null) $stx_string = trim(xss_clean($stx_string));
			if ($srx        !== null) $srx        = trim(xss_clean($srx));
			if ($stx        !== null) $stx        = trim(xss_clean($stx));
		} else {
			$srx_string = null;
			$stx_string = null;
			$srx = null;
			$stx = null;
		}

		if ($this->input->post('contestname')) {
			$contestid = $this->input->post('contestname') == "" ? NULL : $this->input->post('contestname');
		} else {
			$contestid = null;
		}

		if ($this->session->userdata('user_locator')) {
			$locator = $this->session->userdata('user_locator');
		} else {
			$locator = $this->config->item('locator');
		}

		if ($this->input->post('transmit_power')) {
			$tx_power = $this->input->post('transmit_power');
		} else {
			$tx_power = null;
		}

		if ($this->input->post('country') == "") {
			$dxcc = $this->check_dxcc_table(strtoupper(trim($callsign)), $datetime);
			$country = ucwords(strtolower($dxcc[1]), "- (/");
		} else {
			$country = $this->input->post('country');
		}

		if ($this->input->post('cqz') == "") {
			$dxcc = $this->check_dxcc_table(strtoupper(trim($callsign)), $datetime);
			if (empty($dxcc[2])) {
				$cqz = null;
			} else {
				$cqz = $dxcc[2];
			}
		} else {
			$cqz = $this->input->post('cqz');
		}

		if ($this->input->post('dxcc_id') == "") {

			$dxcc = $this->check_dxcc_table(strtoupper(trim($callsign)), $datetime);
			if (empty($dxcc[0])) {
				$dxcc_id = null;
			} else {
				$dxcc_id = $dxcc[0];
			}
		} else {
			$dxcc_id = $this->input->post('dxcc_id');
		}

		if ($this->input->post('continent') == "") {

			$dxcc = $this->check_dxcc_table(strtoupper(trim($callsign)), $datetime);
			if (empty($dxcc[3])) {
				$continent = null;
			} else {
				$continent = $dxcc[3];
			}
		} else {
			$continent = $this->input->post('continent');
		}

		$mode = $this->get_main_mode_if_submode($this->input->post('mode'));
		if ($mode == null) {
			$mode = $this->input->post('mode');
			$submode = null;
		} else {
			$submode = $this->input->post('mode');
		}

		// Represent cnty with "state,cnty" only for USA
		// Others do no need it
		
		if ($this->input->post('county') && $this->input->post('input_state')) {
			switch ($dxcc_id) {
				case 6:
				case 110:
				case 291:
					$clean_county_input = trim($this->input->post('input_state')) . "," . trim($this->input->post('county'));
					break;
				default:
					$clean_county_input = trim($this->input->post('county'));
			}
		} else {
			$clean_county_input = null;
		}

		if (is_numeric($this->input->post('ant_az'))) {
			$ant_az = trim(xss_clean($this->input->post('ant_az')));
		} else {
			$ant_az = null;
		}

		if (is_numeric($this->input->post('ant_el'))) {
			$ant_el = trim(xss_clean($this->input->post('ant_el')));
		} else {
			$ant_el = null;
		}

		$ant_path_input = $this->input->post('ant_path') ?? '';
		$possible_ant_paths = ['G', 'O', 'S', 'L'];
		if (!empty($ant_path_input) && in_array($ant_path_input, $possible_ant_paths)) {
			$ant_path = trim(xss_clean($ant_path_input));
		} else {
			$ant_path = null;
		}

		$darc_dok = trim(xss_clean($this->input->post('darc_dok')));
		$qso_locator = strtoupper(trim(xss_clean($this->input->post('locator')) ?? ''));
		$qso_qth = trim(xss_clean($this->input->post('qth')));
		$qso_name = trim(xss_clean($this->input->post('name')));
		$qso_age = null;
		$qso_state = $this->input->post('input_state') == null ? '' : trim(xss_clean($this->input->post('input_state')));
		$qso_rx_power = null;

		if ($this->input->post('copyexchangeto')) {
			switch ($this->input->post('copyexchangeto')) {
				case 'dok':
					$darc_dok = strtoupper($srx_string);
					break;
				case 'locator':
					// Matching 4-10 character-locator
					if (preg_match('/^[A-R]{2}[0-9]{2}([A-X]{2}([0-9]{2}([A-X]{2})?)?)?$/', $srx_string)) {
						$qso_locator = strtoupper($srx_string);
					}
					break;
				case 'qth':
					$qso_qth = ucfirst($srx_string);
					break;
				case 'name':
					$qso_name = ucfirst($srx_string);
					break;
				case 'age':
					if (is_numeric($srx_string)) {   // ADIF spec say this has to be a number https://adif.org/314/ADIF_314.htm#QSO_Field_AGE
						$qso_age = intval($srx_string);
					}
					break;
				case 'state':
					if (preg_match('/^[A-Za-z]*$/', $srx_string) && $srx_string != "DX") {
						$qso_state = strtoupper($srx_string);
					}
					break;
				case 'power':
					if (is_numeric($srx_string)) {  		// ADIF spec say this has to be a number https://adif.org/314/ADIF_314.htm#QSO_Field_RX_PWR
						$qso_rx_power = intval($srx_string);
					}
					break;
					// Example for more sophisticated exchanges and their split into the db:
					//case 'name/power':
					//  if (strlen($srx_string) == 0) break;
					//  $exch_pt = explode("/",$srx_string);
					//  $qso_name = $exch_pt[0];
					//  if (count($exch_pt)>1) $qso_rx_power = intval($exch_pt[1]);
					//  break;
				default:
			}
		}


		if ($this->input->post('qsl_sent')) {
			$qsl_sent = $this->input->post('qsl_sent');
		} else {
			$qsl_sent = 'N';
		}

		if ($this->input->post('qsl_rcvd')) {
			$qsl_rcvd = $this->input->post('qsl_rcvd');
		} else {
			$qsl_rcvd = 'N';
		}

		if ($qsl_sent == 'N') {
			$qslsdate = null;
		} else {
			$qslsdate = date('Y-m-d H:i:s');
		}

		if ($qsl_rcvd == 'N') {
			$qslrdate = null;
		} else {
			$qslrdate = date('Y-m-d H:i:s');
		}

		// Create array with QSO Data
		$data = array(
			'COL_TIME_ON' => $datetime,
			'COL_TIME_OFF' => $datetime_off,
			'COL_CALL' => strtoupper(trim($callsign)),
			'COL_BAND' => $this->input->post('band'),
			'COL_BAND_RX' => $this->input->post('band_rx'),
			'COL_FREQ' => $this->parse_frequency($this->input->post('freq_display')),
			'COL_MODE' => $mode,
			'COL_SUBMODE' => $submode,
			'COL_RST_RCVD' => $this->input->post('rst_rcvd'),
			'COL_RST_SENT' => $this->input->post('rst_sent'),
			'COL_NAME' => $qso_name,
			'COL_COMMENT' => $this->input->post('comment'),
			'COL_SAT_NAME' => $this->input->post('sat_name') == null ? '' : strtoupper($this->input->post('sat_name')),
			'COL_SAT_MODE' => $this->input->post('sat_mode') == null ? '' : strtoupper($this->input->post('sat_mode')),
			'COL_COUNTRY' => $country,
			'COL_CONT' => $continent,
			'COL_QSLSDATE' => $qslsdate,
			'COL_QSLRDATE' => $qslrdate,
			'COL_QSL_SENT' => $qsl_sent,
			'COL_QSL_RCVD' => $qsl_rcvd,
			'COL_QSL_SENT_VIA' => $this->input->post('qsl_sent_method'),
			'COL_QSL_RCVD_VIA' => $this->input->post('qsl_rcvd_method'),
			'COL_QSL_VIA' => $this->input->post('qsl_via'),
			'COL_QSLMSG' => $this->input->post('qslmsg'),
			'COL_OPERATOR' => $this->input->post('operator_callsign') ?? $this->session->userdata('operator_callsign'),
			'COL_QTH' => $qso_qth,
			'COL_PROP_MODE' => $prop_mode,
			'COL_IOTA' => $this->input->post('iota_ref')  == null ? '' : trim($this->input->post('iota_ref')),
			'COL_FREQ_RX' => $this->parse_frequency($this->input->post('freq_display_rx')),
			'COL_ANT_AZ' => $ant_az,
			'COL_ANT_EL' => $ant_el,
			'COL_ANT_PATH' => $ant_path,
			'COL_A_INDEX' => null,
			'COL_AGE' => $qso_age,
			'COL_TEN_TEN' => null,
			'COL_TX_PWR' => $tx_power,
			'COL_STX' => $stx,
			'COL_SRX' => $srx,
			'COL_STX_STRING' => $stx_string == null ? '' : strtoupper(trim($stx_string)),
			'COL_SRX_STRING' => $srx_string == null ? '' : strtoupper(trim($srx_string)),
			'COL_CONTEST_ID' => $contestid,
			'COL_NR_BURSTS' => null,
			'COL_NR_PINGS' => null,
			'COL_MAX_BURSTS' => null,
			'COL_K_INDEX' => null,
			'COL_SFI' => null,
			'COL_RX_PWR' => $qso_rx_power,
			'COL_LAT' => null,
			'COL_LON' => null,
			'COL_DXCC' => $dxcc_id,
			'COL_CQZ' => $cqz,
			'COL_ITUZ' => $this->input->post('ituz', true) ?? null,
			'COL_STATE' => $qso_state,
			'COL_CNTY' => $clean_county_input,
			'COL_SOTA_REF' => $this->input->post('sota_ref') == null ? '' : strtoupper(trim($this->input->post('sota_ref'))),
			'COL_WWFF_REF' => $this->input->post('wwff_ref') == null ? '' : strtoupper(trim($this->input->post('wwff_ref'))),
			'COL_POTA_REF' => $this->input->post('pota_ref') == null ? '' : strtoupper(trim($this->input->post('pota_ref'))),
			'COL_SIG' => $this->input->post('sig') == null ? '' : strtoupper(trim($this->input->post('sig'))),
			'COL_SIG_INFO' => $this->input->post('sig_info') == null ? '' : strtoupper(trim($this->input->post('sig_info'))),
			'COL_DARC_DOK' => $darc_dok  == null ? '' : strtoupper(trim($darc_dok)),
			'COL_NOTES' => $this->input->post('notes'),
			'COL_EMAIL' => $email ?? '',
			'COL_REGION' => $region ?? '',
		);

		$station_id = $this->input->post('station_profile');

		if ($station_id == "" || $station_id == "0") {
			$this->load->model('stations');
			$station_id = $this->stations->find_active();
		}

		$this->load->model('stations');
		if (!$this->stations->check_station_is_accessible($station_id)) {	// Hard Exit if station_profile not accessible
			return 'Station not accessible<br>';
		}

		// If station profile has been provided fill in the fields
		if ($station_id != "0") {
			$station = $this->check_station($station_id);
			$data['station_id'] = $station_id;

			// [eQSL default msg] add info to QSO for Contest or SFLE //
			if (empty($data['COL_QSLMSG']) && (($this->input->post('isSFLE') == true) || (!empty($data['COL_CONTEST_ID'])))) {
				$this->load->model('user_options_model');
				$options_object = $this->user_options_model->get_options('eqsl_default_qslmsg', array('option_name' => 'key_station_id', 'option_key' => $station_id))->result();
				$data['COL_QSLMSG'] = (isset($options_object[0]->option_value)) ? $options_object[0]->option_value : '';
			}

			if (strpos(trim($station['station_gridsquare']), ',') !== false) {
				$data['COL_MY_VUCC_GRIDS'] = strtoupper(trim($station['station_gridsquare']));
			} else {
				$data['COL_MY_GRIDSQUARE'] = strtoupper(trim($station['station_gridsquare']));
			}

			$distance=null;
			if ( (($this->input->post('distance') ?? '') != '') && (is_numeric($this->input->post('distance'))) ) {
				$distance=$this->input->post('distance');
			} elseif (isset($qso_locator) && $qso_locator != '') {
				if (!$this->load->is_loaded('Qra')) {
					$this->load->library('Qra');
				}
				$distance = $this->qra->distance(strtoupper(trim($station['station_gridsquare'])), $qso_locator, 'K');
			}
			if ($distance != null ) {
				$data['COL_DISTANCE'] = $distance;
			}

			if ($this->exists_hrdlog_credentials($station_id)) {
				$data['COL_HRDLOG_QSO_UPLOAD_STATUS'] = 'N';
			}

			if ($this->exists_qrz_api_key($station_id)) {
				$data['COL_QRZCOM_QSO_UPLOAD_STATUS'] = 'N';
			}

			$data['COL_MY_IOTA'] = $station['station_iota'] ? strtoupper(trim($station['station_iota'])) : '';
			$data['COL_MY_SOTA_REF'] = $station['station_sota'] ? strtoupper(trim($station['station_sota'])) : '';
			$data['COL_MY_WWFF_REF'] = $station['station_wwff'] ? strtoupper(trim($station['station_wwff'])) : '';
			$data['COL_MY_POTA_REF'] = $station['station_pota'] ? strtoupper(trim($station['station_pota'])) : '';

			$data['COL_STATION_CALLSIGN'] = strtoupper(trim($station['station_callsign']));
			$data['COL_MY_CITY'] = strtoupper(trim($station['station_city']));
			$data['COL_MY_DXCC'] = strtoupper(trim($station['station_dxcc']));
			$data['COL_MY_COUNTRY'] = strtoupper(trim($station['station_country'] ?? ''));
			$data['COL_MY_CNTY'] = strtoupper(trim($station['station_cnty']));
			$data['COL_MY_CQ_ZONE'] = strtoupper(trim($station['station_cq']));
			$data['COL_MY_ITU_ZONE'] = strtoupper(trim($station['station_itu']));

			// if there are any static map images for this station, remove them so they can be regenerated
			if (!$this->load->is_loaded('staticmap_model')) {
				$this->load->model('staticmap_model');
			}
			$this->staticmap_model->remove_static_map_image($station_id);
		}

		// Decide whether its single gridsquare or a multi which makes it vucc_grids
		if (strpos($qso_locator, ',') !== false) {
			$data['COL_VUCC_GRIDS'] = strtoupper(preg_replace('/\s+/', '', $qso_locator));
		} else {
			$data['COL_GRIDSQUARE'] = $qso_locator;
		}

		// if eQSL username set, default SENT & RCVD to 'N' else leave as null
		if ($this->session->userdata('user_eqsl_name')) {
			$data['COL_EQSL_QSL_SENT'] = 'N';
			$data['COL_EQSL_QSL_RCVD'] = 'N';
		}

		// if LoTW username set, default SENT & RCVD to 'N' else leave as null
		if ($this->session->userdata('user_lotw_name')) {
			if (in_array($prop_mode, $this->config->item('lotw_unsupported_prop_modes'))) {
				$data['COL_LOTW_QSL_SENT'] = 'I';
				$data['COL_LOTW_QSL_RCVD'] = 'I';
			} else {
				$data['COL_LOTW_QSL_SENT'] = 'N';
				$data['COL_LOTW_QSL_RCVD'] = 'N';
			}
		}

		$this->add_qso($data, $skipexport = false);
		if (($this->config->item('mqtt_server') ?? '') != '') {
			$this->load->model('stations');
			$this->load->library('Mh');
			$h_user=$this->stations->get_user_from_station($station_id);
			$event_data=$data;
			$event_data['user_name']=$h_user->user_name;
			$event_data['user_id']=$h_user->user_id;
			$this->mh->wl_event('qso/logged/'.($h_user->user_id ?? ''), json_encode($event_data));
			unset($event_data);
			unset($h_user);
		}
		unset($data);
	}

	public function check_last_lotw($call) {	// Fetch difference in days when $call has last updated LotW

		$sql = "select datediff(now(),lastupload) as DAYS from lotw_users where callsign = ?";	// Use binding to prevent SQL-injection
		$query = $this->db->query($sql, $call);
		$row = $query->row();
		if (isset($row)) {
			return ($row->DAYS);
		}
	}

	public function check_station($id) {

		$this->db->select('station_profile.*, dxcc_entities.name as station_country');
		$this->db->join('dxcc_entities', 'station_profile.station_dxcc = dxcc_entities.adif', 'left outer');
		$this->db->where('station_id', $id);
		$query = $this->db->get('station_profile');

		if ($query->num_rows() > 0) {
			$row = $query->row_array();
			return ($row);
		}
	}

	/*
	 * Used to fetch QSOs from the logbook in the awards
	 */
	public function qso_details($searchphrase, $band, $mode, $type, $qsl, $sat = null, $orbit = null, $searchmode = null, $propagation = null) {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		$this->db->select($this->config->item('table_name').'.*, `station_profile`.*, `dxcc_entities`.*, `lotw_users`.*, `satellite.displayname` AS sat_displayname');
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->join('dxcc_entities', 'dxcc_entities.adif = ' . $this->config->item('table_name') . '.COL_DXCC', 'left outer');
		$this->db->join('lotw_users', 'lotw_users.callsign = ' . $this->config->item('table_name') . '.col_call', 'left outer');
		if (isset($sat) || strtoupper($band) == 'ALL' || $band == 'SAT' && ($type == 'VUCC' || $type == 'DXCC' || $type == 'DXCC2')) {
			$this->db->join('satellite', 'satellite.name = ' . $this->config->item('table_name') . '.col_sat_name', 'left outer');
		}
		switch ($type) {
			case 'CALL':
				$this->db->where('COL_CALL', $searchphrase);
				break;
			case 'WAE':
				$this->db->group_start();
				$this->db->where("COL_DXCC", $searchphrase);
				$this->db->or_where("COL_REGION", $searchphrase);
				$this->db->group_end();
				break;
			case 'DXCC':
				$this->db->where('COL_COUNTRY', $searchphrase);
				if ($band == 'SAT' && $type == 'DXCC') {
					if ($sat != 'All' && $sat != null) {
						$this->db->where("COL_SAT_NAME", $sat);
					}
					if ($orbit != 'All' && $orbit != null) {
						$this->db->where("satellite.orbit", $orbit);
					}
				}
				break;
			case 'DXCC2':
				$this->db->where('COL_DXCC', $searchphrase);
				if ($band == 'SAT' && $type == 'DXCC2') {
					if ($sat != 'All' && $sat != null) {
						$this->db->where("COL_SAT_NAME", $sat);
					}
					if ($orbit != 'All' && $orbit != null) {
						$this->db->where("satellite.orbit", $orbit);
					}
				}
				break;
			case 'IOTA':
				$this->db->where('COL_IOTA', $searchphrase);
				break;
			case 'VUCC':
				if ($searchmode == 'activated') {
					$this->db->like("station_gridsquare", $searchphrase);
					if ($band == 'SAT' && $type == 'VUCC') {
						if ($sat != 'All' && $sat != null) {
							$this->db->where("COL_SAT_NAME", $sat);
						}
						if ($orbit != 'All' && $orbit != null) {
							$this->db->where("satellite.orbit", $orbit);
						}
					}
				} else {
					$this->db->group_start();
					// to avoid unnecessary QSO are returned, when a 2-digit GL is provided
					// see https://github.com/wavelog/wavelog/pull/992
					$this->db->like("COL_GRIDSQUARE", $searchphrase, 'after');
					$this->db->or_like("COL_VUCC_GRIDS", $searchphrase, 'after');
					// in case of the CALL has more than one GL
					// see https://github.com/wavelog/wavelog/issues/1055
					$this->db->or_like("COL_GRIDSQUARE", ',' . $searchphrase);
					$this->db->or_like("COL_VUCC_GRIDS", ',' . $searchphrase);
					$this->db->group_end();
					if ($band == 'SAT' && $type == 'VUCC') {
						if ($sat != 'All' && $sat != null) {
							$this->db->where("COL_SAT_NAME", $sat);
						}
						if ($orbit != 'All' && $orbit != null) {
							$this->db->where("satellite.orbit", $orbit);
						}
					}
					if (($propagation ?? '') == 'None') {
						$this->db->group_start();
						$this->db->where("COL_PROP_MODE = ''");
						$this->db->or_where("COL_PROP_MODE is null");
						$this->db->group_end();
					} elseif ($propagation == 'NoSAT') {
						$this->db->where("COL_PROP_MODE != 'SAT'");
					} elseif ($propagation != '' && $propagation != null) {
						$this->db->where("COL_PROP_MODE", $propagation);
					}
				}
				break;
			case 'SAT':
				$this->db->where('COL_CALL', $searchphrase);
				$this->db->where('COL_PROP_MODE', 'SAT');
				$this->db->where('COL_SAT_NAME', $sat);
				break;
			case 'CQZone':
				$this->db->where('COL_CQZ', $searchphrase);
				break;
			case 'ITU':
				$this->db->where('COL_ITUZ', $searchphrase);
				break;
			case 'WAS':
				$this->db->where('COL_STATE', $searchphrase);
				$this->db->where_in('COL_DXCC', ['291', '6', '110']);
				break;
			case 'WAP':
				$this->db->where('COL_STATE', $searchphrase);
				$this->db->where_in('COL_DXCC', ['263']);
				break;
			case 'RAC':
				$this->db->where('COL_STATE', $searchphrase);
				$this->db->where_in('COL_DXCC', ['1']);
				break;
			case 'helvetia':
				$this->db->where('COL_STATE', $searchphrase);
				$this->db->where_in('COL_DXCC', ['287']);
				break;
			case 'JCC':
				$this->db->where('COL_CNTY', $searchphrase);
				$this->db->where('COL_DXCC', '339');
				break;
			case 'SOTA':
				$this->db->where('COL_SOTA_REF', $searchphrase);
				break;
			case 'WWFF':
				$this->db->where('COL_WWFF_REF', $searchphrase);
				break;
			case 'POTA':
				$this->db->where('COL_POTA_REF', $searchphrase);
				break;
			case 'DOK':
				$this->db->where('COL_DARC_DOK', $searchphrase);
				break;
			case 'WAB':
				$this->db->where('COL_SIG', 'WAB');
				$this->db->where('COL_SIG_INFO', $searchphrase);
				break;
			case 'WAC':
				$this->db->where('COL_CONT', $searchphrase);
				break;
			case 'WAJA':
				$state = str_pad($searchphrase, 2, '0', STR_PAD_LEFT);
				$this->db->where('COL_STATE', $state);
				$this->db->where('COL_DXCC', '339');
				break;
			case 'QSLRDATE':
				$this->db->where('date(COL_QSLRDATE)=date(SYSDATE())');
				break;
			case 'QSLSDATE':
				$this->db->where('date(COL_QSLSDATE)=date(SYSDATE())');
				break;
			case 'EQSLRDATE':
				$this->db->where('date(COL_EQSL_QSLRDATE)=date(SYSDATE())');
				break;
			case 'EQSLSDATE':
				$this->db->where('date(COL_EQSL_QSLSDATE)=date(SYSDATE())');
				break;
			case 'LOTWRDATE':
				$this->db->where('date(COL_LOTW_QSLRDATE)=date(SYSDATE())');
				break;
			case 'LOTWSDATE':
				$this->db->where('date(COL_LOTW_QSLSDATE)=date(SYSDATE())');
				break;
			case 'QRZRDATE':
				$this->db->where('date(COL_QRZCOM_QSO_DOWNLOAD_DATE)=date(SYSDATE())');
				break;
			case 'QRZSDATE':
				$this->db->where('date(COL_QRZCOM_QSO_UPLOAD_DATE)=date(SYSDATE())');
				break;
		}

		$this->db->where_in($this->config->item('table_name') . '.station_id', $logbooks_locations_array);

		if (strtolower($band) != 'all') {
			if ($band != "SAT") {
				$this->db->where('COL_PROP_MODE !=', 'SAT');
				$this->db->where('COL_BAND', $band);
			} else {
				$this->db->where('COL_PROP_MODE', "SAT");
			}
		}

		if (!empty($qsl)) {
			$qslfilter = array();
			if (strpos($qsl, "Q") !== false) {
				$qslfilter[] = 'COL_QSL_RCVD = "Y"';
			}
			if (strpos($qsl, "L") !== false) {
				$qslfilter[] = 'COL_LOTW_QSL_RCVD = "Y"';
			}
			if (strpos($qsl, "E") !== false) {
				$qslfilter[] = 'COL_EQSL_QSL_RCVD = "Y"';
			}
			if (strpos($qsl, "Z") !== false) {
				$qslfilter[] = 'COL_QRZCOM_QSO_DOWNLOAD_STATUS = "Y"';
			}
			if (strpos($qsl, "C") !== false) {
				$qslfilter[] = 'COL_CLUBLOG_QSO_DOWNLOAD_STATUS = "Y"';
			}
			$sql = "(" . implode(' OR ', $qslfilter) . ")";	// harmless, because value is checked b4
			$this->db->where($sql);
		}

		if (strtolower($mode) != 'all' && $mode != '') {
			$this->db->group_start();
			$this->db->where("COL_MODE", $mode);
			$this->db->or_where("COL_SUBMODE", $mode);
			$this->db->group_end();
		}
		$this->db->order_by("COL_TIME_ON", "desc");
		$this->db->order_by("COL_PRIMARY_KEY", "desc");

		$this->db->limit(500);

		return $this->db->get($this->config->item('table_name'));
	}


	public function vucc_qso_details($gridsquare, $band) {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		$location_list = "'" . implode("','", $logbooks_locations_array) . "'";

		$binding = [];
		$sql = "select * from " . $this->config->item('table_name') .
			" where station_id in (" . $location_list . ")" .
			" and (col_gridsquare like concat(?,'%')
			or col_vucc_grids like concat('%',?,'%')";
		$binding[] = $gridsquare;
		$binding[] = $gridsquare;

		if ($band != 'All') {
			if ($band == 'SAT') {
				$sql .= " and col_prop_mode = ?";
				$binding[] = $band;
			} else {
				$sql .= " and col_prop_mode !='SAT'";
				$sql .= " and col_band = ?";
				$binding[] = $band;
			}
		}

		return $this->db->query($sql, $binding);
	}

	public function activator_details($call, $band, $leogeo) {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->join('dxcc_entities', 'dxcc_entities.adif = ' . $this->config->item('table_name') . '.COL_DXCC', 'left outer');
		$this->db->join('lotw_users', 'lotw_users.callsign = ' . $this->config->item('table_name') . '.col_call', 'left outer');
		$this->db->where('COL_CALL', $call);
		if ($band != 'All') {
			if ($band == 'SAT') {
				$this->db->where('col_prop_mode', $band);
				switch ($leogeo) {
					case 'leo':
						$this->db->where('COL_SAT_NAME !=', 'QO-100');
						break;
					case 'geo':
						$this->db->where('COL_SAT_NAME', 'QO-100');
						break;
				}
			} else {
				$this->db->where('COL_PROP_MODE !=', 'SAT');
				$this->db->where('col_band', $band);
			}
		}

		$this->db->where_in('station_profile.station_id', $logbooks_locations_array);
		$this->db->order_by('COL_TIME_ON', 'DESC');

		return $this->db->get($this->config->item('table_name'));
	}

	public function get_callsigns($callsign) {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		$this->db->select('COL_CALL');
		$this->db->distinct();
		$this->db->like('COL_CALL', $callsign);
		$this->db->where_in('station_id', $logbooks_locations_array);

		return $this->db->get($this->config->item('table_name'));
	}

	public function get_dok($callsign) {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		$this->db->select('COL_DARC_DOK');
		$this->db->where('COL_CALL', $callsign);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->order_by("COL_TIME_ON", "desc");
		$this->db->limit(1);

		return $this->db->get($this->config->item('table_name'));
	}

	function add_qso($data, $skipexport = false, $batchmode = false) {

		if ($data['COL_DXCC'] == "Not Found") {
			$data['COL_DXCC'] = NULL;
		}

		if (!is_null($data['COL_RX_PWR'])) {
			$data['COL_RX_PWR'] = str_replace("W", "", $data['COL_RX_PWR']);
		}

		if ((!is_null($data['COL_RX_PWR'])) && (!((is_numeric($data['COL_RX_PWR']))))) {	// Filled but not numeric?
			$data['COL_RX_PWR']=NULL;
		}

		// Add QSO to database
		if ($batchmode) {
			return $data;
		} else {
			$this->db->insert($this->config->item('table_name'), $data);

			$last_id = $this->db->insert_id();

			if ($this->session->userdata('user_amsat_status_upload') && $data['COL_PROP_MODE'] == "SAT") {
				$this->upload_amsat_status($data);
			}

			// No point in fetching hrdlog code or qrz api key and qrzrealtime setting if we're skipping the export
			if (!$skipexport) {

				$result = $this->exists_clublog_credentials($data['station_id']);
				if (isset($result->ucp) && isset($result->ucn) && (($result->ucp ?? '') != '') && (($result->ucn ?? '') != '') && ($result->clublogrealtime == 1)) {
					if (!$this->load->is_loaded('AdifHelper')) {
						$this->load->library('AdifHelper');
					}
					$qso = $this->get_qso($last_id, true)->result();

					if (!$this->load->is_loaded('clublog_model')) {
						$this->load->model('clublog_model');
					}

					$adif = $this->adifhelper->getAdifLine($qso[0]);
					$result = $this->clublog_model->push_qso_to_clublog($result->ucn, $result->ucp, $data['COL_STATION_CALLSIGN'], $adif, $data['station_id']);
					if ($result['status'] == 'OK') {
						$this->mark_clublog_qsos_sent($last_id);
					}
				}

				$result = '';
				$result = $this->exists_hrdlog_credentials($data['station_id']);
				// Push qso to hrdlog if code is set, and realtime upload is enabled, and we're not importing an adif-file
				if (isset($result->hrdlog_code) && isset($result->hrdlog_username) && $result->hrdlogrealtime == 1) {
					if (!$this->load->is_loaded('AdifHelper')) {
						$this->load->library('AdifHelper');
					}
					$qso = $this->get_qso($last_id, true)->result();

					$adif = $this->adifhelper->getAdifLine($qso[0]);
					$result = $this->push_qso_to_hrdlog($result->hrdlog_username, $result->hrdlog_code, $adif);
					if (($result['status'] == 'OK') || (($result['status'] == 'error') || ($result['status'] == 'duplicate') || ($result['status'] == 'auth_error'))) {
						$this->mark_hrdlog_qsos_sent($last_id);
					}
				}
				$result = ''; // Empty result from previous hrdlog-attempt for safety
				$result = $this->exists_qrz_api_key($data['station_id']);
				// Push qso to qrz if apikey is set, and realtime upload is enabled, and we're not importing an adif-file
				if (isset($result->qrzapikey) && $result->qrzrealtime == 1) {
					if (!$this->load->is_loaded('AdifHelper')) {
						$this->load->library('AdifHelper');
					}
					$qso = $this->get_qso($last_id, true)->result();

					$adif = $this->adifhelper->getAdifLine($qso[0]);
					$result = $this->push_qso_to_qrz($result->qrzapikey, $adif);
					if (($result['status'] == 'OK') || (($result['status'] == 'error') && ($result['message'] == 'STATUS=FAIL&REASON=Unable to add QSO to database: duplicate&EXTENDED='))) {
						$this->mark_qrz_qsos_sent($last_id);
					}
				}

				$result = $this->exists_webadif_api_key($data['station_id']);
				// Push qso to webadif if apikey is set, and realtime upload is enabled, and we're not importing an adif-file
				if (isset($result->webadifapikey) && $result->webadifrealtime == 1) {
					if (!$this->load->is_loaded('AdifHelper')) {
						$this->load->library('AdifHelper');
					}
					$qso = $this->get_qso($last_id, true)->result();

					$adif = $this->adifhelper->getAdifLine($qso[0]);
					$result = $this->push_qso_to_webadif(
						$result->webadifapiurl,
						$result->webadifapikey,
						$adif
					);

					if ($result) {
						$this->mark_webadif_qsos_sent([$last_id]);
					}
				}
			}
		}
	}

	/*
   * Function checks if a HRDLog Code and Username exists in the table with the given station id
   */
	function exists_hrdlog_credentials($station_id) {
		$sql = 'select hrdlog_username, hrdlog_code, hrdlogrealtime from station_profile
		  where station_id = ? and hrdlogrealtime>=0';

		$query = $this->db->query($sql, $station_id);

		$result = $query->row();

		if ($result) {
			return $result;
		} else {
			return false;
		}
	}

	/*
   * Function checks if a Clublog Credebtials exists in the table with the given station id
  */
	function exists_clublog_credentials($station_id) {
		$sql = 'select auth.user_clublog_name ucn, auth.user_clublog_password ucp, prof.clublogrealtime from ' . $this->config->item('auth_table') . ' auth inner join station_profile prof on (auth.user_id=prof.user_id) where prof.station_id = ? and prof.clublogrealtime=1';

		$query = $this->db->query($sql, $station_id);

		$result = $query->row();

		if ($result) {
			return $result;
		} else {
			return false;
		}
	}


	/*
   * Function checks if a QRZ API Key exists in the table with the given station id
  */
	function exists_qrz_api_key($station_id) {
		$sql = 'select qrzapikey, qrzrealtime from station_profile
            where station_id = ?';

		$query = $this->db->query($sql, $station_id);

		$result = $query->row();

		if ($result) {
			return $result;
		} else {
			return false;
		}
	}

	/*
	 * Function checks if a WebADIF API Key exists in the table with the given station id
	*/
	function exists_webadif_api_key($station_id) {
		$sql = 'select webadifapikey, webadifapiurl, webadifrealtime from station_profile
		  where station_id = ?';

		$query = $this->db->query($sql, $station_id);

		$result = $query->row();

		if ($result) {
			return $result;
		} else {
			return false;
		}
	}

	/*
   * Function uploads a QSO to HRDLog with the API given.
   * $adif contains a line with the QSO in the ADIF format. QSO ends with an <EOR>
   */
	function push_qso_to_hrdlog($hrdlog_username, $apikey, $adif, $replaceoption = false) {
		$url = 'https://robot.hrdlog.net/newentry.aspx';

		$post_data['Code'] = $apikey;
		if ($replaceoption) {
			$post_data['Cmd'] = 'UPDATE';
			$post_data['ADIFKey'] = $adif;
		}
		$post_data['ADIFData'] = $adif;

		$post_data['Callsign'] = $hrdlog_username;


		$post_encoded = http_build_query($post_data);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_encoded);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
		$content = curl_exec($ch);
		if ($content) {
			if (stristr($content, '<insert>1')) {
				$result['status'] = 'OK';
				return $result;
			} elseif (stristr($content, '<insert>0')) {
				$result['status'] = 'duplicate';
				$result['message'] = $content;
				return $result;
			} elseif (stristr($content, 'Unknown user</error>')) {
				$result['status'] = 'auth_error';
				$result['message'] = $content;
				return $result;
			} elseif (stristr($content, 'Invalid token</error>')) {
				$result['status'] = 'auth_error';
				$result['message'] = $content;
				return $result;
			} else {
				$result['status'] = 'error';
				$result['message'] = $content;
				return $result;
			}
		}
		if (curl_errno($ch)) {
			$result['status'] = 'error';
			$result['message'] = 'Curl error: ' . curl_errno($ch);
			return $result;
		}
		curl_close($ch);
	}

	/*
   * Function uploads a QSO to QRZ with the API given.
   * $adif contains a line with the QSO in the ADIF format. QSO ends with an <EOR>
   */
	function push_qso_to_qrz($apikey, $adif, $replaceoption = false) {
		$url = 'http://logbook.qrz.com/api'; // TODO: Move this to database

		$post_data['KEY'] = $apikey;
		$post_data['ACTION'] = 'INSERT';
		$post_data['ADIF'] = $adif;

		if ($replaceoption) {
			$post_data['OPTION'] = 'REPLACE';
		}

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Wavelog/'.$this->optionslib->get_option('version'));
		$content = curl_exec($ch);
		if ($content) {
			if (stristr($content, 'RESULT=OK') || stristr($content, 'RESULT=REPLACE')) {
				$result['status'] = 'OK';
				return $result;
			} else {
				$result['status'] = 'error';
				$result['message'] = $content;
				return $result;
			}
		}
		if (curl_errno($ch)) {
			$result['status'] = 'error';
			$result['message'] = 'Curl error: ' . curl_errno($ch);
			return $result;
		}
		curl_close($ch);
	}

	/*
	 * Function uploads a QSO to WebADIF consumer with the API given.
	 * $adif contains a line with the QSO in the ADIF format.
	 */
	function push_qso_to_webadif($url, $apikey, $adif): bool {

		$headers = array(
			'Content-Type: text/plain',
			'X-API-Key: ' . $apikey
		);

		if (substr($url, -1) !== "/") {
			$url .= "/";
		}

		$ch = curl_init($url . "qso");
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$adif);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$content = curl_exec($ch); // TODO: better error handling
		$errors = curl_error($ch);
		$response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $response === 200;
	}

	/*
   * Function marks QSOs as uploaded to Clublog
   * $primarykey is the unique id for that QSO in the logbook
   */
	function mark_clublog_qsos_sent($primarykey) {
		$data = array(
			'COL_CLUBLOG_QSO_UPLOAD_DATE' => date("Y-m-d H:i:s", strtotime("now")),
			'COL_CLUBLOG_QSO_UPLOAD_STATUS' => 'Y',
		);

		$this->db->where('COL_PRIMARY_KEY', $primarykey);

		$this->db->update($this->config->item('table_name'), $data);

		return true;
	}


	/*
   * Function marks QSOs as uploaded to HRDLog.
   * $primarykey is the unique id for that QSO in the logbook
   */
	function mark_hrdlog_qsos_sent($primarykey) {
		$data = array(
			'COL_HRDLOG_QSO_UPLOAD_DATE' => date("Y-m-d H:i:s", strtotime("now")),
			'COL_HRDLOG_QSO_UPLOAD_STATUS' => 'Y',
		);

		$this->db->where('COL_PRIMARY_KEY', $primarykey);

		$this->db->update($this->config->item('table_name'), $data);

		return true;
	}

	/*
   * Function marks QSOs as uploaded to QRZ.
   * $primarykey is the unique id for that QSO in the logbook
   */
	function mark_qrz_qsos_sent($primarykey, $state = 'Y') {
		$data = array(
			'COL_QRZCOM_QSO_UPLOAD_DATE' => date("Y-m-d H:i:s", strtotime("now")),
			'COL_QRZCOM_QSO_UPLOAD_STATUS' => $state,
		);

		$this->db->where('COL_PRIMARY_KEY', $primarykey);

		$this->db->update($this->config->item('table_name'), $data);

		return true;
	}

	/*
	* Function marks QSOs as uploaded to WebADIF.
	* $qsoIDs is an arroy of unique id for the QSOs in the logbook
	*/
	function mark_webadif_qsos_sent(array $qsoIDs) {
		$data = [];
		$now = date("Y-m-d H:i:s", strtotime("now"));
		foreach ($qsoIDs as $qsoID) {
			$data[] = [
				'upload_date' => $now,
				'qso_id' => $qsoID,
			];
		}
		$this->db->insert_batch('webadif', $data);
		return true;
	}

	function upload_amsat_status($data) {
		$sat_name = '';
		if ($data['COL_SAT_NAME'] == 'AO-7') {
			if ($data['COL_BAND'] == '2m' && $data['COL_BAND_RX'] == '10m') {
				$sat_name = 'AO-7[A]';
			}
			if ($data['COL_BAND'] == '70cm' && $data['COL_BAND_RX'] == '2m') {
				$sat_name = 'AO-7[B]';
			}
		} else if ($data['COL_SAT_NAME'] == 'QO-100') {
			$sat_name = 'QO-100_NB';
		} else if ($data['COL_SAT_NAME'] == 'AO-92') {
			if ($data['COL_BAND'] == '70cm' && $data['COL_BAND_RX'] == '2m') {
				$sat_name = 'AO-92_U/v';
			}
			if ($data['COL_BAND'] == '23cm' && $data['COL_BAND_RX'] == '2m') {
				$sat_name = 'AO-92_L/v';
			}
		} else if ($data['COL_SAT_NAME'] == 'AO-95') {
			if ($data['COL_BAND'] == '70cm' && $data['COL_BAND_RX'] == '2m') {
				$sat_name = 'AO-95_U/v';
			}
			if ($data['COL_BAND'] == '23cm' && $data['COL_BAND_RX'] == '2m') {
				$sat_name = 'AO-95_L/v';
			}
		} else if ($data['COL_SAT_NAME'] == 'PO-101') {
			if ($data['COL_MODE'] == 'PKT') {
				$sat_name = 'PO-101[APRS]';
			} else {
				$sat_name = 'PO-101[FM]';
			}
		} else if ($data['COL_SAT_NAME'] == 'FO-118') {
			if ($data['COL_BAND'] == '2m') {
				if ($data['COL_MODE'] == 'FM') {
					$sat_name = 'FO-118[V/u FM]';
				} else if ($data['COL_MODE'] == 'SSB') {
					$sat_name = 'FO-118[V/u]';
				}
			} else if ($data['COL_BAND'] == '15m') {
				$sat_name = 'FO-118[H/u]';
			}
		} else if ($data['COL_SAT_NAME'] == 'ARISS' || $data['COL_SAT_NAME'] == 'ISS') {
			if ($data['COL_MODE'] == 'FM') {
				$sat_name = 'ISS-FM';
			} else if ($data['COL_MODE'] == 'PKT') {
				$sat_name = 'ISS-DATA';
			}
		} else if ($data['COL_SAT_NAME'] == 'CAS-3H') {
			$sat_name = 'LilacSat-2';
		} else {
			$sat_name = $data['COL_SAT_NAME'];
		}
		$amsat_source_grid = '';
		if (array_key_exists('COL_MY_GRIDSQUARE', $data)) {
			$amsat_source_grid = $data['COL_MY_GRIDSQUARE'];
		} else if (array_key_exists('COL_MY_VUCC_GRIDS', $data)) {
			$amsat_source_grid = strtok($data['COL_MY_VUCC_GRIDS'], ',');
		}
		if ($amsat_source_grid != '') {
			$datearray = date_parse_from_format("Y-m-d H:i:s", $data['COL_TIME_ON']);
			$url = 'https://amsat.org/status/submit.php?SatSubmit=yes&Confirm=yes&SatName=' . $sat_name . '&SatYear=' . $datearray['year'] . '&SatMonth=' . str_pad($datearray['month'], 2, '0', STR_PAD_LEFT) . '&SatDay=' . str_pad($datearray['day'], 2, '0', STR_PAD_LEFT) . '&SatHour=' . str_pad($datearray['hour'], 2, '0', STR_PAD_LEFT) . '&SatPeriod=' . (intdiv(($datearray['minute'] - 1), 15)) . '&SatCall=' . $data['COL_STATION_CALLSIGN'] . '&SatReport=Heard&SatGridSquare=' . substr($amsat_source_grid,0,6);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_exec($ch);
		}
	}

	/* Edit QSO */
	function edit() {
		$retvals=[];
		$retvals['success']=false;
		$qso = $this->get_qso($this->input->post('id'))->row();

		$entity = $this->get_entity($this->input->post('dxcc_id'));
		$stationId = $this->input->post('station_profile');
		$country = ucwords(strtolower($entity['name'] ?? ''), "- (/");	// Prevent Errors, if JS-Fence doesn't help

		// be sure that station belongs to user
		$this->load->model('stations');
		if (!$this->stations->check_station_is_accessible($stationId)) {
			$retvals['detail']=__('Station ID not allowed');
			return $retvals;
		}

		if (trim($this->input->post('callsign')) == '') {
			$retvals['detail']=__('No Call given');
			return $retvals;
		}

		$station_profile = $this->stations->profile_clean($stationId);
		$stationCallsign = trim($station_profile->station_callsign);
		$iotaRef = $station_profile->station_iota ?? '';
		$sotaRef = $station_profile->station_sota ?? '';
		$wwffRef = $station_profile->station_wwff ?? '';
		$potaRef = $station_profile->station_pota ?? '';
		$sig     = $station_profile->station_sig ?? '';
		$sigInfo = $station_profile->station_sig_info ?? '';

		$mode = $this->get_main_mode_if_submode($this->input->post('mode'));
		if ($mode == null) {
			$mode = $this->input->post('mode');
			$submode = null;
		} else {
			$submode = $this->input->post('mode');
		}

		if ($this->input->post('transmit_power')) {
			$txpower = $this->input->post('transmit_power');
		} else {
			$txpower = null;
		}

		if ($this->input->post('email')) {
			$email = $this->input->post('email',TRUE);
		} else {
			$email = null;
		}

		if ($this->input->post('region')) {
			$region = $this->input->post('region',TRUE);
		} else {
			$region = null;
		}

		if ($this->input->post('stx')) {
			$stx_string = $this->input->post('stx');
		} else {
			$stx_string = null;
		}

		if ($this->input->post('srx')) {
			$srx_string = $this->input->post('srx');
		} else {
			$srx_string = null;
		}

		if (is_numeric($this->input->post('dxcc_id'))) {
			$dxcc=$this->input->post('dxcc_id');
			if (stristr($this->input->post('usa_county') ?? '', ',')) {	// Already comma-seperated County?
				$uscounty = $this->input->post('usa_county');
			} elseif ($this->input->post('usa_county') && $this->input->post('input_state_edit')) {	// Both filled (and no comma - because that fits one above)
				switch ($dxcc) {
					case 6:
					case 110:
					case 291:
						$uscounty = trim($this->input->post('input_state_edit') . "," . $this->input->post('usa_county'));
						break;
					default:
						$uscounty = $this->input->post('usa_county');
				}
			} else {	// nothing from above?
				$uscounty = null;
			}
			
		} else {
			$retvals['detail']=__("DXCC has to be Numeric");
			return $retvals;
		}

		if ($this->input->post('qsl_sent')) {
			$qsl_sent = $this->input->post('qsl_sent');
		} else {
			$qsl_sent = 'N';
		}

		if ($this->input->post('qsl_rcvd')) {
			$qsl_rcvd = $this->input->post('qsl_rcvd');
		} else {
			$qsl_rcvd = 'N';
		}

		if ($this->input->post('eqsl_sent')) {
			$eqsl_sent = $this->input->post('eqsl_sent');
		} else {
			$eqsl_sent = 'N';
		}

		if ($this->input->post('eqsl_rcvd')) {
			$eqsl_rcvd = $this->input->post('eqsl_rcvd');
		} else {
			$eqsl_rcvd = 'N';
		}

		if ($this->input->post('qrz_sent')) {
			$qrz_sent = $this->input->post('qrz_sent');
		} else {
			$qrz_sent = 'N';
		}

		if ($this->input->post('qrz_rcvd')) {
			$qrz_rcvd = $this->input->post('qrz_rcvd');
		} else {
			$qrz_rcvd = 'N';
		}

		if ($this->input->post('clublog_sent')) {
			$clublog_sent = $this->input->post('clublog_sent');
		} else {
			$clublog_sent = 'N';
		}

		if ($this->input->post('clublog_rcvd')) {
			$clublog_rcvd = $this->input->post('clublog_rcvd');
		} else {
			$clublog_rcvd = 'N';
		}

		if (in_array($this->input->post('prop_mode'), $this->config->item('lotw_unsupported_prop_modes'))) {
			$lotw_sent = 'I';
		} elseif ($this->input->post('lotw_sent')) {
			$lotw_sent = $this->input->post('lotw_sent');
		} else {
			$lotw_sent = 'N';
		}

		if (in_array($this->input->post('prop_mode'), $this->config->item('lotw_unsupported_prop_modes'))) {
			$lotw_rcvd = 'I';
		} elseif ($this->input->post('lotw_rcvd')) {
			$lotw_rcvd = $this->input->post('lotw_rcvd');
		} else {
			$lotw_rcvd = 'N';
		}

		if ($qsl_sent == 'N') {
			$qslsdate = null;
		} elseif (!$qso->COL_QSLSDATE || $qso->COL_QSL_SENT != $qsl_sent) {
			$qslsdate = date('Y-m-d H:i:s');
		} else {
			$qslsdate = $qso->COL_QSLSDATE;
		}

		if ($qsl_rcvd == 'N') {
			$qslrdate = null;
		} elseif (!$qso->COL_QSLRDATE || $qso->COL_QSL_RCVD != $qsl_rcvd) {
			$qslrdate = date('Y-m-d H:i:s');
		} else {
			$qslrdate = $qso->COL_QSLRDATE;
		}

		if ($eqsl_sent == 'N') {
			$eqslsdate = null;
		} elseif (!$qso->COL_EQSL_QSLSDATE || $qso->COL_EQSL_QSL_SENT != $eqsl_sent) {
			$eqslsdate = date('Y-m-d H:i:s');
		} else {
			$eqslsdate = $qso->COL_EQSL_QSLSDATE;
		}

		if ($eqsl_rcvd == 'N') {
			$eqslrdate = null;
		} elseif (!$qso->COL_EQSL_QSLRDATE || $qso->COL_EQSL_QSL_RCVD != $eqsl_rcvd) {
			$eqslrdate = date('Y-m-d H:i:s');
		} else {
			$eqslrdate = $qso->COL_EQSL_QSLRDATE;
		}

		if ($lotw_sent == 'N') {
			$lotwsdate = null;
		} elseif (!$qso->COL_LOTW_QSLSDATE || $qso->COL_LOTW_QSL_SENT != $lotw_sent) {
			$lotwsdate = date('Y-m-d H:i:s');
		} else {
			$lotwsdate = $qso->COL_LOTW_QSLSDATE;
		}

		if ($lotw_rcvd == 'N') {
			$lotwrdate = null;
		} elseif (!$qso->COL_LOTW_QSLRDATE || $qso->COL_LOTW_QSL_RCVD != $lotw_rcvd) {
			$lotwrdate = date('Y-m-d H:i:s');
		} else {
			$lotwrdate = $qso->COL_LOTW_QSLRDATE;
		}

		$qrz_modified=false;
		if ($qrz_sent == 'N' && $qso->COL_QRZCOM_QSO_UPLOAD_STATUS != $qrz_sent) {
			$qrzsdate = null;
			$qrz_modified=true;
		} elseif (!$qso->COL_QRZCOM_QSO_UPLOAD_DATE || $qso->COL_QRZCOM_QSO_UPLOAD_STATUS != $qrz_sent) {
			$qrzsdate = date('Y-m-d H:i:s');
			$qrz_modified=true;
		} else {
			$qrzsdate = $qso->COL_QRZCOM_QSO_UPLOAD_DATE;
		}

		if ($qrz_rcvd == 'N' && $qso->COL_QRZCOM_QSO_DOWNLOAD_STATUS != $qrz_rcvd) {
			$qrzrdate = null;
			$qrz_modified=true;
		} elseif (!$qso->COL_QRZCOM_QSO_DOWNLOAD_DATE || $qso->COL_QRZCOM_QSO_DOWNLOAD_STATUS != $qrz_rcvd) {
			$qrzrdate = date('Y-m-d H:i:s');
			$qrz_modified=true;
		} else {
			$qrzrdate = $qso->COL_QRZCOM_QSO_DOWNLOAD_DATE;
		}

		if ($clublog_sent == 'N' && $qso->COL_CLUBLOG_QSO_UPLOAD_STATUS != $clublog_sent) {
			$clublogsdate = null;
		} elseif (!$qso->COL_CLUBLOG_QSO_UPLOAD_DATE || $qso->COL_CLUBLOG_QSO_UPLOAD_STATUS != $clublog_sent) {
			$clublogsdate = date('Y-m-d H:i:s');
		} else {
			$clublogsdate = $qso->COL_CLUBLOG_QSO_UPLOAD_DATE;
		}

		if ($clublog_rcvd == 'N' && $qso->COL_CLUBLOG_QSO_DOWNLOAD_STATUS != $clublog_rcvd) {
			$clublogrdate = null;
		} elseif (!$qso->COL_CLUBLOG_QSO_DOWNLOAD_DATE || $qso->COL_CLUBLOG_QSO_DOWNLOAD_STATUS != $clublog_rcvd) {
			$clublogrdate = date('Y-m-d H:i:s');
		} else {
			$clublogrdate = $qso->COL_CLUBLOG_QSO_DOWNLOAD_DATE;
		}

		if (($this->input->post('distance')) && (is_numeric($this->input->post('distance')))) {
			$distance = $this->input->post('distance');
		} else {
			$distance = null;
		}

		// Check if time_off is before time_on. If: set time_off to time_on
		$time_on = date("Y-m-d H:i:s", strtotime($this->input->post('time_on')));
		if (($this->input->post('time_off') ?? '') != '') {
			$time_off = date("Y-m-d H:i:s", strtotime($this->input->post('time_off')));
			$_tmp_datetime_off = strtotime($time_off);
			if ($_tmp_datetime_off < strtotime($this->input->post('time_on'))) {
				$time_off = $time_on;
			}
		} else {
			$time_off = $time_on;
		}

 		if (is_numeric($this->input->post('dxcc_id'))) {
			$dxcc=$this->input->post('dxcc_id');
		} else {
			$retvals['detail']=__("DXCC has to be Numeric");
			return $retvals;
		}

		$data = array(
			'COL_TIME_ON' => $time_on,
			'COL_TIME_OFF' => $time_off,
			'COL_CALL' => strtoupper(trim($this->input->post('callsign'))),
			'COL_BAND' => $this->input->post('band'),
			'COL_BAND_RX' => $this->input->post('band_rx'),
			'COL_FREQ' => $this->parse_frequency($this->input->post('freq')),
			'COL_MODE' => $mode,
			'COL_SUBMODE' => $submode,
			'COL_RST_RCVD' => $this->input->post('rst_rcvd'),
			'COL_RST_SENT' => $this->input->post('rst_sent'),
			'COL_GRIDSQUARE' => strtoupper(trim($this->input->post('locator'))),
			'COL_VUCC_GRIDS' => strtoupper(preg_replace('/\s+/', '', $this->input->post('vucc_grids'))),
			'COL_DISTANCE' => $distance,
			'COL_COMMENT' => $this->input->post('comment'),
			'COL_NAME' => $this->input->post('name'),
			'COL_COUNTRY' => $country,
			'COL_CONT' => $this->input->post('continent'),
			'COL_DXCC' => $dxcc,
			'COL_CQZ' => $this->input->post('cqz'),
			'COL_ITUZ' => $this->input->post('ituz') != '' ? $this->input->post('ituz') : null,
			'COL_SAT_NAME' => $this->input->post('sat_name'),
			'COL_SAT_MODE' => $this->input->post('sat_mode'),
			'COL_NOTES' => $this->input->post('notes'),
			'COL_QSLSDATE' => $qslsdate,
			'COL_QSLRDATE' => $qslrdate,
			'COL_QSL_SENT' => $qsl_sent,
			'COL_QSL_RCVD' => $qsl_rcvd,
			'COL_QSL_SENT_VIA' => $this->input->post('qsl_sent_method'),
			'COL_QSL_RCVD_VIA' => $this->input->post('qsl_rcvd_method'),
			'COL_EQSL_QSLSDATE' => $eqslsdate,
			'COL_EQSL_QSLRDATE' => $eqslrdate,
			'COL_EQSL_QSL_SENT' => $this->input->post('eqsl_sent'),
			'COL_EQSL_QSL_RCVD' => $this->input->post('eqsl_rcvd'),
			'COL_QSLMSG' => $this->input->post('qslmsg'),
			'COL_QRZCOM_QSO_UPLOAD_DATE' => $qrzsdate,
			'COL_QRZCOM_QSO_DOWNLOAD_DATE' => $qrzrdate,
			'COL_QRZCOM_QSO_UPLOAD_STATUS' => $qrz_sent,
			'COL_QRZCOM_QSO_DOWNLOAD_STATUS' => $qrz_rcvd,
			'COL_LOTW_QSLSDATE' => $lotwsdate,
			'COL_LOTW_QSLRDATE' => $lotwrdate,
			'COL_LOTW_QSL_SENT' => $lotw_sent,
			'COL_LOTW_QSL_RCVD' => $lotw_rcvd,
			'COL_CLUBLOG_QSO_UPLOAD_DATE' => $clublogsdate,
			'COL_CLUBLOG_QSO_DOWNLOAD_DATE' => $clublogrdate,
			'COL_CLUBLOG_QSO_DOWNLOAD_STATUS' => $clublog_rcvd,
			'COL_CLUBLOG_QSO_UPLOAD_STATUS' => $clublog_sent,
			'COL_IOTA' => $this->input->post('iota_ref'),
			'COL_SOTA_REF' => strtoupper(trim($this->input->post('sota_ref'))),
			'COL_WWFF_REF' => strtoupper(trim($this->input->post('wwff_ref'))),
			'COL_POTA_REF' => strtoupper(trim($this->input->post('pota_ref'))),
			'COL_TX_PWR' => $txpower,
			'COL_SIG' => strtoupper(trim($this->input->post('sig'))),
			'COL_SIG_INFO' => strtoupper(trim($this->input->post('sig_info'))),
			'COL_DARC_DOK' => strtoupper(trim($this->input->post('darc_dok'))),
			'COL_QTH' => $this->input->post('qth'),
			'COL_PROP_MODE' => $this->input->post('prop_mode'),
			'COL_ANT_PATH' => $this->input->post('ant_path'),
			'COL_FREQ_RX' => $this->parse_frequency($this->input->post('freq_display_rx')),
			'COL_STX_STRING' => strtoupper(trim($this->input->post('stx_string'))),
			'COL_SRX_STRING' => strtoupper(trim($this->input->post('srx_string'))),
			'COL_STX' => $stx_string,
			'COL_SRX' => $srx_string,
			'COL_CONTEST_ID' => $this->input->post('contest_name'),
			'COL_QSL_VIA' => $this->input->post('qsl_via_callsign'),
			'COL_ANT_AZ' => $this->input->post('ant_az') != '' ? $this->input->post('ant_az') : null,
			'COL_ANT_EL' => $this->input->post('ant_el') != '' ? $this->input->post('ant_el') : null,
			'station_id' => $stationId,
			'COL_STATION_CALLSIGN' => $stationCallsign,
			'COL_OPERATOR' => $this->input->post('operator_callsign') ?? $qso->COL_OPERATOR,
			'COL_STATE' => $this->input->post('input_state_edit'),
			'COL_CNTY' => $uscounty,
			'COL_MY_IOTA' => $iotaRef,
			'COL_MY_SOTA_REF' => $sotaRef,
			'COL_MY_WWFF_REF' => $wwffRef,
			'COL_MY_POTA_REF' => $potaRef,
			'COL_MY_SIG' => $sig,
			'COL_MY_SIG_INFO' => $sigInfo,
			'COL_EMAIL' => $email ?? '',
			'COL_REGION' => $region ?? '',
		);

		if ($this->exists_hrdlog_credentials($data['station_id']) && !$qrz_modified) {
			$data['COL_HRDLOG_QSO_UPLOAD_STATUS'] = 'M';
		}

		$old_qrz=($qso->COL_QRZCOM_QSO_UPLOAD_STATUS ?? '');
		if ( ($old_qrz == 'I' || $old_qrz == 'Y') && ($this->exists_qrz_api_key($data['station_id']) && !$qrz_modified) ) {	// Update only to "M" if uploaded before or Invalid (may be correct after update)
			$data['COL_QRZCOM_QSO_UPLOAD_STATUS'] = 'M';
		}

		$this->db->where('COL_PRIMARY_KEY', $this->input->post('id'));
		try {
			$this->db->update($this->config->item('table_name'), $data);
			$retvals['success']=true;
		} catch (Exception $e) {
			$retvals['success']=false;
			$retvals['detail']=$e;
		} finally {
			return($retvals);
		}
	}

	/* QSL received */
	function qsl_rcvd() {

		$data = array(
			'COL_QSLRDATE' => date('Y-m-d H:i:s'),
			'COL_QSL_RCVD' => "Y"
		);

		$this->db->where('COL_PRIMARY_KEY', $this->input->post('id'));
		$this->db->update($this->config->item('table_name'), $data);
	}

	/* Show custom number of qsos */
	function last_custom($num) {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!empty($logbooks_locations_array)) {
			$this->db->select('COL_CALL, COL_BAND, COL_FREQ, COL_TIME_ON, COL_RST_RCVD, COL_RST_SENT, COL_MODE, COL_SUBMODE, COL_NAME, COL_COUNTRY, COL_DXCC, COL_PRIMARY_KEY, COL_SAT_NAME, COL_SRX, COL_SRX_STRING, COL_STX, COL_STX_STRING, COL_VUCC_GRIDS, COL_GRIDSQUARE, COL_MY_GRIDSQUARE, COL_OPERATOR, COL_IOTA, COL_WWFF_REF, COL_POTA_REF, COL_STATE, COL_CNTY, COL_DISTANCE, COL_SOTA_REF, COL_CONTEST_ID, dxcc_entities.end AS end');
			$this->db->join('dxcc_entities', $this->config->item('table_name') . '.col_dxcc = dxcc_entities.adif', 'left outer');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->order_by("COL_TIME_ON", "desc");
			$this->db->order_by("COL_PRIMARY_KEY", "desc");
			$this->db->limit($num);

			return $this->db->get($this->config->item('table_name'));
		} else {
			return false;
		}
	}

	/*
  *
  * Function: call_lookup_result
  *
  * Usage: Callsign lookup data for API/callsign_lookup
  *
  */
	function call_lookup_result($callsign, $station_ids, $user_default_confirmation, $band, $mode) {
		$binding=[];
		$qsl_where = $this->qsl_default_where($user_default_confirmation);
		$band_addon='COL_BAND=?';
		if ($band == 'SAT') {
			$band_addon="COL_PROP_MODE=?";
		}

		$sql="SELECT COL_CALL, COL_NAME, COL_QSL_VIA, COL_GRIDSQUARE, COL_QTH, COL_IOTA, COL_TIME_ON, COL_STATE, COL_CNTY, COL_DXCC, COL_CONT,
			CASE WHEN ( (".$qsl_where.") ) THEN 1  ELSE 0 END AS CALL_CNF,
			CASE WHEN ( (".$qsl_where.") AND ".$band_addon.") THEN 1  ELSE 0 END AS CALL_CNF_BAND,
			CASE WHEN ( (".$qsl_where.") AND ".$band_addon." AND COL_MODE=?) THEN 1  ELSE 0 END AS CALL_CNF_BAND_MODE,
			CASE WHEN ( ".$band_addon.") THEN 1  ELSE 0 END AS CALL_WORKED_BAND,
			CASE WHEN ( ".$band_addon." AND COL_MODE=?) THEN 1  ELSE 0 END AS CALL_WORKED_BAND_MODE
		FROM ".$this->config->item('table_name')." WHERE ";
		$sql.="station_id IN (".$station_ids.") AND COL_CALL = ? ORDER BY call_cnf desc, call_worked_band desc, call_cnf_band desc, call_worked_band_mode desc, call_cnf_band_mode desc limit 1";
		$binding[]=$band;
		$binding[]=$band;
		$binding[]=$mode;
		$binding[]=$band;
		$binding[]=$band;
		$binding[]=$mode;
		$binding[]=$callsign;

		$query = $this->db->query($sql, $binding);
		$data = [];
		if ($query->num_rows() > 0) {
			$data = $query->row();
		}
		return $data;
	}

	/* Callsign QRA */
	function call_qra($callsign) {
		$this->db->select('COL_CALL, COL_GRIDSQUARE, COL_TIME_ON');
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('station_profile.user_id', $this->session->userdata('user_id'));
		$where = "COL_GRIDSQUARE != \"\"";

		$this->db->where($where);

		$this->db->order_by("COL_TIME_ON", "desc");
		$this->db->limit(1);
		$query = $this->db->get($this->config->item('table_name'));
		$callsign = "";
		if ($query->num_rows() > 0) {
			$data = $query->row();
			$callsign = strtoupper($data->COL_GRIDSQUARE);
		}

		return $callsign;
	}

	function call_name($callsign) {
		$this->db->select('COL_CALL, COL_NAME, COL_TIME_ON');
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('station_profile.user_id', $this->session->userdata('user_id'));
		$where = "COL_NAME != \"\"";

		$this->db->where($where);

		$this->db->order_by("COL_TIME_ON", "desc");
		$this->db->limit(1);
		$query = $this->db->get($this->config->item('table_name'));
		$name = "";
		if ($query->num_rows() > 0) {
			$data = $query->row();
			$name = $data->COL_NAME;
		}

		return $name;
	}

	function call_email($callsign) {
		$this->db->select('COL_CALL, COL_EMAIL, COL_TIME_ON');
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('station_profile.user_id', $this->session->userdata('user_id'));
		$this->db->order_by("COL_TIME_ON", "desc");
		$this->db->limit(1);
		$query = $this->db->get($this->config->item('table_name'));
		$email = "";
		if ($query->num_rows() > 0) {
			$data = $query->row();
			$email = $data->COL_EMAIL;
		}

		return $email;
	}

	function times_worked($callsign) {
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		$this->db->select('count(1) as TWKED');
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->group_start();
		$this->db->where($this->config->item('table_name') . '.COL_CALL', $callsign);
		$this->db->or_like($this->config->item('table_name') . '.COL_CALL', '/' . $callsign, 'before');
		$this->db->or_like($this->config->item('table_name') . '.COL_CALL', $callsign . '/', 'after');
		$this->db->or_like($this->config->item('table_name') . '.COL_CALL', '/' . $callsign . '/');

		$this->db->group_end();
		$this->db->where('station_profile.user_id', $this->session->userdata('user_id'));
		$this->db->where_in('station_profile.station_id', $logbooks_locations_array);
		$this->db->limit(1);
		$query = $this->db->get($this->config->item('table_name'));
		$name = "";
		if ($query->num_rows() > 0) {
			$data = $query->row();
			$times_worked = $data->TWKED;
		}

		return $times_worked;
	}

	function call_qslvia($callsign) {
		$this->db->select('COL_CALL, COL_QSL_VIA, COL_TIME_ON');
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('station_profile.user_id', $this->session->userdata('user_id'));
		$where = "COL_QSL_VIA != \"\"";

		$this->db->where($where);

		$this->db->order_by("COL_TIME_ON", "desc");
		$this->db->limit(1);
		$query = $this->db->get($this->config->item('table_name'));
		$qsl_via = "";
		if ($query->num_rows() > 0) {
			$data = $query->row();
			$qsl_via = $data->COL_QSL_VIA;
		}

		return $qsl_via;
	}

	function call_state($callsign) {
		$this->db->select('COL_CALL, COL_STATE');
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('station_profile.user_id', $this->session->userdata('user_id'));
		$where = "COL_STATE != \"\"";

		$this->db->where($where);

		$this->db->order_by("COL_TIME_ON", "desc");
		$this->db->limit(1);
		$query = $this->db->get($this->config->item('table_name'));
		$qsl_state = "";
		if ($query->num_rows() > 0) {
			$data = $query->row();
			$qsl_state = $data->COL_STATE;
		}

		return $qsl_state;
	}

	function call_us_county($callsign) {
		$this->db->select('COL_CALL, COL_CNTY');
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('station_profile.user_id', $this->session->userdata('user_id'));
		$where = "COL_CNTY != \"\"";

		$this->db->where($where);

		$this->db->order_by("COL_TIME_ON", "desc");
		$this->db->limit(1);
		$query = $this->db->get($this->config->item('table_name'));
		if ($query->num_rows() > 0) {
			$data = $query->row();
			$qsl_county = $data->COL_CNTY;
			$qsl_county = substr($qsl_county, (strpos($qsl_county, ',') + 1));
			return $qsl_county;
		} else {
			return NULL;
		}
	}

	function call_ituzone($callsign) {
		$this->db->select('COL_CALL, COL_ITUZ');
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('station_profile.user_id', $this->session->userdata('user_id'));
		$where = "COL_ITUZ != \"\"";

		$this->db->where($where);

		$this->db->order_by("COL_TIME_ON", "desc");
		$this->db->limit(1);
		$query = $this->db->get($this->config->item('table_name'));
		if ($query->num_rows() > 0) {
			$data = $query->row();
			$qsl_ituz = $data->COL_ITUZ;
			return $qsl_ituz;
		} else {
			return NULL;
		}
	}

	function call_cqzone($callsign) {
		$this->db->select('COL_CALL, COL_CQZ');
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('station_profile.user_id', $this->session->userdata('user_id'));
		$where = "COL_CQZ != \"\"";

		$this->db->where($where);

		$this->db->order_by("COL_TIME_ON", "desc");
		$this->db->limit(1);
		$query = $this->db->get($this->config->item('table_name'));
		if ($query->num_rows() > 0) {
			$data = $query->row();
			$qsl_cqz = $data->COL_CQZ;
			return $qsl_cqz;
		} else {
			return NULL;
		}
	}

	function call_qth($callsign) {
		$this->db->select('COL_CALL, COL_QTH, COL_TIME_ON');
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('station_profile.user_id', $this->session->userdata('user_id'));
		$where = "COL_QTH != \"\"";

		$this->db->where($where);

		$this->db->order_by("COL_TIME_ON", "desc");
		$this->db->limit(1);
		$query = $this->db->get($this->config->item('table_name'));
		$name = "";
		if ($query->num_rows() > 0) {
			$data = $query->row();
			$name = $data->COL_QTH;
		}

		return $name;
	}

	function call_iota($callsign) {
		$this->db->select('COL_CALL, COL_IOTA, COL_TIME_ON');
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('station_profile.user_id', $this->session->userdata('user_id'));
		$this->db->order_by("COL_TIME_ON", "desc");
		$this->db->limit(1);
		$query = $this->db->get($this->config->item('table_name'));
		$name = "";
		if ($query->num_rows() > 0) {
			$data = $query->row();
			$name = $data->COL_IOTA;
		}

		return $name;
	}

	/* Return QSO Info */
	function qso_info($id) {
		if ($this->check_qso_is_accessible($id)) {
			$this->db->where('COL_PRIMARY_KEY', $id);
			$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
			$this->db->join('dxcc_entities', $this->config->item('table_name') . '.col_dxcc = dxcc_entities.adif', 'left');
			$this->db->join('lotw_users', 'lotw_users.callsign = ' . $this->config->item('table_name') . '.col_call', 'left outer');

			return $this->db->get($this->config->item('table_name'));
		} else {
			return;
		}
	}


	// Set Paper to received
	function paperqsl_update($qso_id, $method) {
		if ($this->check_qso_is_accessible($qso_id)) {

			$data = array(
				'COL_QSLRDATE' => date('Y-m-d H:i:s'),
				'COL_QSL_RCVD' => 'Y',
				'COL_QSL_RCVD_VIA' => $method
			);

			$this->db->where('COL_PRIMARY_KEY', $qso_id);
			$this->db->where('COL_QSL_RCVD !=', 'Y');

			$this->db->update($this->config->item('table_name'), $data);
			if ($this->db->affected_rows()>0) {	// Only set to modified if REALLY modified
				$this->set_qrzcom_modified($qso_id);
			}

		} else {
			return;
		}
	}


	// Set Paper to sent
	function paperqsl_update_sent($qso_id, $method) {
		if ($this->check_qso_is_accessible($qso_id)) {
			if ($method != '') {
				$data = array(
					'COL_QSLSDATE' => date('Y-m-d H:i:s'),
					'COL_QSL_SENT' => 'Y',
					'COL_QSL_SENT_VIA' => $method
				);
			} else {
				$data = array(
					'COL_QSLSDATE' => date('Y-m-d H:i:s'),
					'COL_QSL_SENT' => 'Y'
				);
			}

			$this->db->where('COL_PRIMARY_KEY', $qso_id);
			$this->db->where('COL_QSL_SENT !=', 'Y');

			$this->db->update($this->config->item('table_name'), $data);

			if ($this->db->affected_rows()>0) {	// Only set to modified if REALLY modified
				$this->set_qrzcom_modified($qso_id);
			}
		} else {
			return;
		}
	}


	// Set Paper to requested
	function paperqsl_requested($qso_id, $method) {
		if ($this->check_qso_is_accessible($qso_id)) {

			$data = array(
				'COL_QSLSDATE' => date('Y-m-d H:i:s'),
				'COL_QSL_SENT' => 'R',
				'COL_QSL_SENT_VIA' => $method
			);

			$this->db->where('COL_PRIMARY_KEY', $qso_id);
			$this->db->group_start();
			$this->db->where('COL_QSL_SENT !=','R');
			$this->db->or_where('COL_QSL_SENT_VIA !=', $method);
			$this->db->group_end();

			$this->db->update($this->config->item('table_name'), $data);

			if ($this->db->affected_rows()>0) {	// Only set to modified if REALLY modified
				$this->set_qrzcom_modified($qso_id);
			}
		} else {
			return;
		}
	}


	function paperqsl_ignore($qso_id, $method) {
		if ($this->check_qso_is_accessible($qso_id)) {

			$data = array(
				'COL_QSLSDATE' => date('Y-m-d H:i:s'),
				'COL_QSL_SENT' => 'I'
			);

			$this->db->where('COL_PRIMARY_KEY', $qso_id);
			$this->db->where('COL_QSL_SENT !=', 'I');

			$this->db->update($this->config->item('table_name'), $data);

			if ($this->db->affected_rows()>0) {	// Only set to modified if REALLY modified
				$this->set_qrzcom_modified($qso_id);
			}
		} else {
			return;
		}
	}

	function get_qsos_for_printing($station_id2 = null) {
		$binding = [];
		$this->load->model('stations');
		$station_id = $this->stations->find_active();

		$sql = 'SELECT
		  STATION_CALLSIGN,
		  COL_PRIMARY_KEY,
		  COL_CALL,
		  COL_QSL_VIA,
		  COL_TIME_ON,
		  COL_MODE,
		  COL_SUBMODE,
		  COL_FREQ,
		  UPPER(COL_BAND) as COL_BAND,
		  COL_RST_SENT,
		  COL_SAT_NAME,
		  COL_SAT_MODE,
		  COL_QSL_RCVD,
		  COL_COMMENT,
		  (select adif from dxcc_prefixes where  (CASE WHEN COL_QSL_VIA != \'\' THEN COL_QSL_VIA ELSE COL_CALL END) like concat(dxcc_prefixes.`call`,\'%\') order by end limit 1) as ADIF,
		  (select entity from dxcc_prefixes where  (CASE WHEN COL_QSL_VIA != \'\' THEN COL_QSL_VIA ELSE COL_CALL END) like concat(dxcc_prefixes.`call`,\'%\') order by end limit 1) as ENTITY,
		  (CASE WHEN COL_QSL_VIA != \'\' THEN COL_QSL_VIA ELSE COL_CALL END) AS COL_ROUTING
		  FROM ' . $this->config->item('table_name') . ' thcv
		  join station_profile on thcv.station_id = station_profile.station_id
		  WHERE
		  COL_QSL_SENT in (\'R\', \'Q\')';

		if ($station_id2 == NULL) {
			$sql .= ' and thcv.station_id = ?';
			$binding[] = $station_id;
		} else if ($station_id2 != 'All') {
			$sql .= ' and thcv.station_id = ?';
			$binding[] = $station_id2;
		}

		// always filter user. this ensures that even if the station_id is from another user no inaccesible QSOs will be returned
		$sql .= ' and station_profile.user_id = ?';
		$binding[] = $this->session->userdata('user_id');

		$sql .= ' ORDER BY ADIF, COL_ROUTING';

		$query = $this->db->query($sql, $binding);
		return $query;
	}

	function get_qsos($num, $offset, $StationLocationsArray = null, $band = '') {
		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		if (empty($logbooks_locations_array)) {
			return array();
		}

		if (empty($logbooks_locations_array)) {
			return array();
		}

		$this->db->select($this->config->item('table_name') . '.*, station_profile.*, dxcc_entities.*, lotw_users.callsign, lotw_users.lastupload, satellite.displayname AS sat_displayname');
		$this->db->from($this->config->item('table_name'));

		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->join('dxcc_entities', $this->config->item('table_name') . '.col_dxcc = dxcc_entities.adif', 'left');
		$this->db->join('lotw_users', 'lotw_users.callsign = ' . $this->config->item('table_name') . '.col_call', 'left outer');
		$this->db->join('satellite', 'satellite.name = ' . $this->config->item('table_name') . '.COL_SAT_NAME', 'left outer');

		if ($band != '') {
			if ($band == 'SAT') {
				$this->db->where($this->config->item('table_name') . '.col_prop_mode', 'SAT');
			} else {
				$this->db->where($this->config->item('table_name') . '.col_prop_mode !="SAT"');
				$this->db->where($this->config->item('table_name') . '.col_band', $band);
			}
		}

		$this->db->where_in($this->config->item('table_name') . '.station_id', $logbooks_locations_array);
		$this->db->order_by('' . $this->config->item('table_name') . '.COL_TIME_ON', "desc");
		$this->db->order_by('' . $this->config->item('table_name') . '.COL_PRIMARY_KEY', "desc");

		$this->db->limit($num);
		$this->db->offset($offset);

		return $this->db->get();
	}

	function get_qso($id, $trusted = false) {
		if ($trusted || ($this->check_qso_is_accessible($id))) {
			$this->db->select($this->config->item('table_name') . '.*, station_profile.*, dxcc_entities.*, coalesce(dxcc_entities_2.name, "- NONE -") as station_country, dxcc_entities_2.end as station_end, eQSL_images.image_file as eqsl_image_file, lotw_users.callsign as lotwuser, lotw_users.lastupload, primary_subdivisions.subdivision, satellite.displayname AS sat_displayname, coalesce(contest.name, COL_CONTEST_ID) AS contestname');
			$this->db->from($this->config->item('table_name'));
			$this->db->join('dxcc_entities', $this->config->item('table_name') . '.col_dxcc = dxcc_entities.adif', 'left');
			$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id', 'left');
			$this->db->join('dxcc_entities as dxcc_entities_2', 'station_profile.station_dxcc = dxcc_entities_2.adif', 'left outer');
			$this->db->join('eQSL_images', $this->config->item('table_name') . '.COL_PRIMARY_KEY = eQSL_images.qso_id', 'left outer');
			$this->db->join('lotw_users', $this->config->item('table_name') . '.COL_CALL = lotw_users.callsign', 'left outer');
			$this->db->join('primary_subdivisions', $this->config->item('table_name') . '.COL_DXCC = primary_subdivisions.adif AND ' . $this->config->item('table_name') . '.COL_STATE = primary_subdivisions.state', 'left outer');
			$this->db->join('satellite', $this->config->item('table_name') . '.COL_SAT_NAME = satellite.name', 'left outer');
			$this->db->join('contest', $this->config->item('table_name') . '.COL_CONTEST_ID = contest.adifname', 'left outer');
			$this->db->where('COL_PRIMARY_KEY', $id);
			$this->db->limit(1);

			return $this->db->get();
		} else {
			return;
		}
	}

	/*
     * Function returns the QSOs from the logbook, which have not been either marked as uploaded to hrdlog, or has been modified with an edit
     */
	function get_hrdlog_qsos($station_id) {
		$binding = [];
		$sql = 'select *, dxcc_entities.name as station_country from ' . $this->config->item('table_name') . ' thcv ' .
			' left join station_profile on thcv.station_id = station_profile.station_id' .
			' left outer join dxcc_entities on thcv.col_my_dxcc = dxcc_entities.adif' .
			' where thcv.station_id = ?' .
			' and station_profile.hrdlogrealtime>=0
			  and (COL_HRDLOG_QSO_UPLOAD_STATUS is NULL
		  or COL_HRDLOG_QSO_UPLOAD_STATUS = ""
		  or COL_HRDLOG_QSO_UPLOAD_STATUS = "M"
		  or COL_HRDLOG_QSO_UPLOAD_STATUS = "N")';
		$binding[] = $station_id;

		$query = $this->db->query($sql, $binding);
		return $query;
	}

	/*
     * Function returns the QSOs from the logbook, which have not been either marked as uploaded to qrz, or has been modified with an edit
     */
	function get_qrz_qsos($station_id, $trusted = false) {
		$binding = [];
		$this->load->model('stations');
		if ((!$trusted) && (!$this->stations->check_station_is_accessible($station_id))) {
			return;
		}
		$sql = 'select *, dxcc_entities.name as station_country from ' . $this->config->item('table_name') . ' thcv ' .
			' left join station_profile on thcv.station_id = station_profile.station_id' .
			' left outer join dxcc_entities on thcv.col_my_dxcc = dxcc_entities.adif' .
			' where thcv.station_id = ?' .
			' and (COL_QRZCOM_QSO_UPLOAD_STATUS is NULL
		  or COL_QRZCOM_QSO_UPLOAD_STATUS = ""
		  or COL_QRZCOM_QSO_UPLOAD_STATUS = "M"
		  or COL_QRZCOM_QSO_UPLOAD_STATUS = "N")';
		$binding[] = $station_id;

		$query = $this->db->query($sql, $binding);
		return $query;
	}

	/**
	 * Generic function to set the QRZ.com Upload status to 'modified'
	 *
	 * @param int $qso_id  the QSO primary key (COL_PRIMARY_KEY)
	 */

	function set_qrzcom_modified($qso_id) {
		$data = array(
			'COL_QRZCOM_QSO_UPLOAD_STATUS' => 'M'
		);

		$this->db->where('COL_PRIMARY_KEY', $qso_id);
		$this->db->group_start();
		$this->db->where('COL_QRZCOM_QSO_UPLOAD_STATUS', 'Y');
		$this->db->or_where('COL_QRZCOM_QSO_UPLOAD_STATUS', 'I');
		$this->db->group_end();
		$this->db->update($this->config->item('table_name'), $data);
	}

	/*
     * Function returns the QSOs from the logbook, which have not been either marked as uploaded to webADIF
     */
	function get_webadif_qsos($station_id, $from = null, $to = null, $trusted = false) {
		$binding = [];
		$this->load->model('stations');
		if ((!$trusted) && (!$this->stations->check_station_is_accessible($station_id))) {
			return;
		}
		$sql = "
			SELECT qsos.*, station_profile.*, dxcc_entities.name as station_country
			FROM " . $this->config->item('table_name') . " qsos
			INNER JOIN station_profile ON qsos.station_id = station_profile.station_id
			LEFT JOIN dxcc_entities on qsos.col_my_dxcc = dxcc_entities.adif
			LEFT OUTER JOIN webadif ON qsos.COL_PRIMARY_KEY = webadif.qso_id
			WHERE qsos.station_id = ?
			AND qsos.COL_SAT_NAME = 'QO-100'
			AND webadif.upload_date IS NULL
		";
		$binding[] = $station_id;

		if ($from) {
			$from = DateTime::createFromFormat('d/m/Y', $from);
			$from = $from->format('Y-m-d');

			$sql .= "  AND qsos.COL_TIME_ON >= ?";
			$binding[] = $from;
		}
		if ($to) {
			$to = DateTime::createFromFormat('d/m/Y', $to);
			$to = $to->format('Y-m-d');

			$sql .= "  AND qsos.COL_TIME_ON <= ?";
			$binding[] = $to;
		}

		return $this->db->query($sql, $binding);
	}

	/*
     * Function returns all the station_id's with QRZ API Key's
     */
	function get_station_id_with_qrz_api() {
		$sql = 'select station_id, qrzapikey, qrzrealtime from station_profile
		  where coalesce(qrzapikey, "") <> ""';

		$query = $this->db->query($sql);

		$result = $query->result();

		if ($result) {
			return $result;
		} else {
			return null;
		}
	}

	/*
     * Function returns all the station_id's with HRDLOG Code
     */
	function get_station_id_with_hrdlog_code() {
		$sql = 'SELECT station_id, hrdlog_username, hrdlog_code, station_callsign
                FROM station_profile
                WHERE coalesce(hrdlog_username, "") <> ""
		AND hrdlogrealtime>=0
                AND coalesce(hrdlog_code, "") <> ""';

		$query = $this->db->query($sql);

		$result = $query->result();

		if ($result) {
			return $result;
		} else {
			return null;
		}
	}

	/*
     * Function returns all the station_id's with QRZ API Key's
     */
	function get_qrz_apikeys() {
		$sql = 'select GROUP_CONCAT(station_id) as station_ids, qrzapikey, user_id from station_profile
			where coalesce(qrzapikey, "") <> "" group by qrzapikey, user_id order by qrzapikey';

		$query = $this->db->query($sql);

		$result = $query->result();

		if ($result) {
			return $result;
		} else {
			return null;
		}
	}

	/*
     * Function returns all the station_id's with QRZ API Key's
     */
	function get_station_id_with_webadif_api() {
		$sql = "
			SELECT station_id, webadifapikey, webadifapiurl
			FROM station_profile
            WHERE COALESCE(webadifapikey, '') <> ''
              AND COALESCE(webadifapiurl, '') <> ''
		";

		$query = $this->db->query($sql);
		$result = $query->result();
		if ($result) {
			return $result;
		} else {
			return null;
		}
	}

	function get_last_qsos($num, $StationLocationsArray = null) {
		$binding = [];
		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		if ($logbooks_locations_array) {
			$location_list = "'" . implode("','", $logbooks_locations_array) . "'";

			$sql = "SELECT * FROM ( SELECT * FROM " . $this->config->item('table_name') . "
			    WHERE station_id IN(" . $location_list . ")
			    ORDER BY col_time_on DESC, col_primary_key DESC
			    LIMIT ?) hrd
			    JOIN station_profile ON station_profile.station_id = hrd.station_id
			    LEFT JOIN dxcc_entities ON hrd.col_dxcc = dxcc_entities.adif
			    ORDER BY col_time_on DESC, col_primary_key DESC";
			$binding[] = $num * 1;
			$query = $this->db->query($sql, $binding);

			return $query;
		} else {
			return null;
		}
	}

	function check_if_callsign_cnfmd_in_logbook($callsign, $StationLocationsArray = null, $band = null) {

		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		$user_default_confirmation = $this->session->userdata('user_default_confirmation');
		$extrawhere = '';
		if (isset($user_default_confirmation) && strpos($user_default_confirmation, 'Q') !== false) {
			$extrawhere = "COL_QSL_RCVD='Y'";
		}
		if (isset($user_default_confirmation) && strpos($user_default_confirmation, 'L') !== false) {
			if ($extrawhere != '') {
				$extrawhere .= " OR";
			}
			$extrawhere .= " COL_LOTW_QSL_RCVD='Y'";
		}
		if (isset($user_default_confirmation) && strpos($user_default_confirmation, 'E') !== false) {
			if ($extrawhere != '') {
				$extrawhere .= " OR";
			}
			$extrawhere .= " COL_EQSL_QSL_RCVD='Y'";
		}

		if (isset($user_default_confirmation) && strpos($user_default_confirmation, 'Z') !== false) {
			if ($extrawhere != '') {
				$extrawhere .= " OR";
			}
			$extrawhere .= " COL_QRZCOM_QSO_DOWNLOAD_STATUS='Y'";
		}


		$this->db->select('COL_CALL');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_CALL', $callsign);

		$band = ($band == 'All') ? null : $band;
		if ($band != null && $band != 'SAT') {
			$this->db->where('COL_BAND', $band);
		} else if ($band == 'SAT') {
			// Where col_sat_name is not empty
			$this->db->where('COL_SAT_NAME !=', '');
		}
		if ($extrawhere != '') {
			$this->db->where('(' . $extrawhere . ')');
		} else {
			$this->db->where("1=0");
		}
		$this->db->limit('2');
		$query = $this->db->get($this->config->item('table_name'));

		return $query->num_rows();
	}

	function last_worked_callsign_in_logbook($callsign, $StationLocationsArray = null, $band = null) {

		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		$this->db->select('COL_TIME_ON as LAST_QSO,COL_MODE as LAST_MODE');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_CALL', $callsign);

		$band = ($band == 'All') ? null : $band;
		if ($band != null && $band != 'SAT') {
			$this->db->where('COL_BAND', $band);
		} else if ($band == 'SAT') {
			// Where col_sat_name is not empty
			$this->db->where('COL_SAT_NAME !=', '');
		}
		$this->db->order_by('COL_TIME_ON desc');
		$this->db->limit('1');
		$query = $this->db->get($this->config->item('table_name'));

		return $query->result();
	}

	function check_if_callsign_worked_in_logbook($callsign, $StationLocationsArray = null, $band = null) {

		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		$this->db->select('COL_CALL');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_CALL', $callsign);

		$band = ($band == 'All') ? null : $band;
		if ($band != null && $band != 'SAT') {
			$this->db->where('COL_BAND', $band);
		} else if ($band == 'SAT') {
			// Where col_sat_name is not empty
			$this->db->where('COL_SAT_NAME !=', '');
		}
		$this->db->limit('2');
		$query = $this->db->get($this->config->item('table_name'));

		return $query->num_rows();
	}

	function check_if_dxcc_worked_in_logbook($dxcc, $StationLocationsArray = null, $band = null) {

		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		$this->db->select('COL_DXCC');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_DXCC', $dxcc);

		$band = ($band == 'All') ? null : $band;
		if ($band != null && $band != 'SAT') {
			$this->db->where('COL_BAND', $band);
		} else if ($band == 'SAT') {
			// Where col_sat_name is not empty
			$this->db->where('COL_SAT_NAME !=', '');
		}
		$this->db->limit('2');

		$query = $this->db->get($this->config->item('table_name'));
		return $query->num_rows();
	}

	private function qsl_default_where($user_default_confirmation) {
		$extrawhere='';
		if (isset($user_default_confirmation) && strpos($user_default_confirmation, 'Q') !== false) {
			$extrawhere = "COL_QSL_RCVD='Y'";
		}
		if (isset($user_default_confirmation) && strpos($user_default_confirmation, 'L') !== false) {
			if ($extrawhere != '') {
				$extrawhere .= " OR";
			}
			$extrawhere .= " COL_LOTW_QSL_RCVD='Y'";
		}
		if (isset($user_default_confirmation) && strpos($user_default_confirmation, 'E') !== false) {
			if ($extrawhere != '') {
				$extrawhere .= " OR";
			}
			$extrawhere .= " COL_EQSL_QSL_RCVD='Y'";
		}

		if (isset($user_default_confirmation) && strpos($user_default_confirmation, 'Z') !== false) {
			if ($extrawhere != '') {
				$extrawhere .= " OR";
			}
			$extrawhere .= " COL_QRZCOM_QSO_DOWNLOAD_STATUS='Y'";
		}
		if ($extrawhere == '') {
			$extrawhere='1=0';	// No default_confirmations set? in that case everything is false
		}
		return $extrawhere;
	}

	function check_if_dxcc_cnfmd_in_logbook_api($user_default_confirmation,$dxcc, $station_ids = null, $band = null, $mode = null) {
		$binding=[];
		if ($station_ids == null) {
			return [];
		}

		$extrawhere = $this->qsl_default_where($user_default_confirmation);

		$sql="SELECT count(1) as CNT from ".$this->config->item('table_name')." where station_id in (".$station_ids.") and (".$extrawhere.") and COL_DXCC=?";
		$binding[]=$dxcc;

		$band = ($band == 'All') ? null : $band;
		if ($band != null && $band != 'SAT') {
			$sql.=" AND COL_BAND = ?";
			$binding[]=$band;
		} else if ($band == 'SAT') {
			$sql.=" AND COL_PROP_MODE = ?";
			$binding[]=$band;
		}

		if ($mode != null) {
			$sql.=" AND COL_MODE = ?";
			$binding[]=$mode;
		}

		$query = $this->db->query($sql, $binding);
		$row = $query->row();
		if (isset($row)) {
			return ($row->CNT);
		} else {
			return 0;
		}
	}


	function check_if_dxcc_cnfmd_in_logbook($dxcc, $StationLocationsArray = null, $band = null) {

		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		$user_default_confirmation = $this->session->userdata('user_default_confirmation');
		$extrawhere = '';
		if (isset($user_default_confirmation) && strpos($user_default_confirmation, 'Q') !== false) {
			$extrawhere = "COL_QSL_RCVD='Y'";
		}
		if (isset($user_default_confirmation) && strpos($user_default_confirmation, 'L') !== false) {
			if ($extrawhere != '') {
				$extrawhere .= " OR";
			}
			$extrawhere .= " COL_LOTW_QSL_RCVD='Y'";
		}
		if (isset($user_default_confirmation) && strpos($user_default_confirmation, 'E') !== false) {
			if ($extrawhere != '') {
				$extrawhere .= " OR";
			}
			$extrawhere .= " COL_EQSL_QSL_RCVD='Y'";
		}

		if (isset($user_default_confirmation) && strpos($user_default_confirmation, 'Z') !== false) {
			if ($extrawhere != '') {
				$extrawhere .= " OR";
			}
			$extrawhere .= " COL_QRZCOM_QSO_DOWNLOAD_STATUS='Y'";
		}


		$this->db->select('COL_DXCC');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_DXCC', $dxcc);

		$band = ($band == 'All') ? null : $band;
		if ($band != null && $band != 'SAT') {
			$this->db->where('COL_BAND', $band);
		} else if ($band == 'SAT') {
			// Where col_sat_name is not empty
			$this->db->where('COL_SAT_NAME !=', '');
		}
		if ($extrawhere != '') {
			$this->db->where('(' . $extrawhere . ')');
		} else {
			$this->db->where("1=0");
		}
		$this->db->limit('2');

		$query = $this->db->get($this->config->item('table_name'));

		return $query->num_rows();
	}


	function check_if_grid_worked_in_logbook($grid, $StationLocationsArray = null, $band = null, $cnfm = null) {

		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		switch ($cnfm) {
			case 'qsl':
				$this->db->select('COL_QSL_RCVD as gridorcnfm');
				$this->db->group_by('COL_QSL_RCVD');
				break;
			case 'lotw':
				$this->db->select('COL_LOTW_QSL_RCVD as gridorcnfm');
				$this->db->group_by('COL_LOTW_QSL_RCVD');
				break;
			case 'eqsl':
				$this->db->select('COL_EQSL_QSL_RCVD as gridorcnfm');
				$this->db->group_by('COL_EQSL_QSL_RCVD');
				break;
			default:
				$this->db->select('SUBSTR(COL_GRIDSQUARE,1 ,4) as gridorcnfm');
				$this->db->group_by('gridorcnfm');
				break;
		}
		$this->db->order_by('gridorcnfm');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->group_start();
		$this->db->like('COL_GRIDSQUARE', $grid);
		$this->db->or_like('COL_VUCC_GRIDS', $grid);
		$this->db->group_end();

		$band = ($band == 'All') ? null : $band;
		if ($band != null && $band != 'SAT') {
			$this->db->where('COL_BAND', $band);
		} else if ($band == 'SAT') {
			// Where col_sat_name is not empty
			$this->db->where('COL_SAT_NAME !=', '');
		}

		$query = $this->db->get($this->config->item('table_name'));

		return $query;
	}

	/* Get all QSOs with a valid grid for use in the KML export */
	function kml_get_all_qsos($band, $mode, $dxcc, $cqz, $propagation, $fromdate, $todate) {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		$this->db->select('COL_CALL, COL_BAND, COL_TIME_ON, COL_RST_RCVD, COL_RST_SENT, COL_MODE, COL_SUBMODE, COL_NAME, COL_COUNTRY, COL_PRIMARY_KEY, COL_SAT_NAME, COL_GRIDSQUARE');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where("coalesce(COL_GRIDSQUARE, '') <> ''");

		if ($band != 'All') {
			if ($band == 'SAT') {
				$this->db->where('COL_PROP_MODE', $band);
			} else {
				$this->db->where('COL_PROP_MODE != \'SAT\'');
				$this->db->where('COL_BAND', $band);
			}
		}

		if ($mode != 'All') {
			$this->db->where('COL_MODE', $mode);
		}

		if ($dxcc != 'All') {
			$this->db->where('COL_DXCC', $dxcc);
		}

		if ($cqz != 'All') {
			$this->db->where('COL_CQZ', $cqz);
		}

		if ($propagation != 'All') {
			$this->db->where('COL_PROP_MODE', $propagation);
		}

		// If date is set, we add it to the where-statement
		if ($fromdate != "") {
			$this->db->where("date(" . $this->config->item('table_name') . ".COL_TIME_ON) >=", $fromdate);
		}
		if ($todate != "") {
			$this->db->where("date(" . $this->config->item('table_name') . ".COL_TIME_ON) <=", $todate);
		}

		$query = $this->db->get($this->config->item('table_name'));
		return $query;
	}

	function cfd_get_all_qsos($fromdate, $todate) {
		$binding = [];
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		// If date is set, we add it to the where-statement
		if ($fromdate ?? '' != "") {
			$from = " AND date(q.COL_TIME_ON) >= ?";
			$binding[] = $fromdate;
		} else {
			$from = "";
		}
		if ($todate ?? '' != "") {
			$till = " AND date(q.COL_TIME_ON) <= ?";
			$binding[] = $todate;
		} else {
			$till = '';
		}

		$location_list = "'" . implode("','", $logbooks_locations_array) . "'";

		$sql = "SELECT
		  dx.prefix,dx.name,
		  CASE
		  WHEN q.col_mode = 'CW' THEN 'C'
		  WHEN mo.qrgmode = 'DATA' THEN 'R'
		  WHEN mo.qrgmode = 'SSB' THEN 'F'
		  ELSE mo.qrgmode
		  END AS mode,q.col_band as band,
		  COUNT(1) as cnfmd
		  FROM " . $this->config->item('table_name') . " q
		  INNER JOIN
		  dxcc_entities dx ON (dx.adif = q.COL_DXCC)
		  INNER JOIN
		  adif_modes mo ON (mo.mode = q.COL_MODE)
		  inner join bands b on (b.band=q.COL_BAND)
		  WHERE
		  (q.COL_QSL_RCVD = 'Y'
		  OR q.COL_LOTW_QSL_RCVD = 'Y'
		  OR q.COL_EQSL_QSL_RCVD = 'Y')
		  AND q.station_id in (" . $location_list . ")
		  AND (b.bandgroup='hf' or b.band = '6m' or b.band = '160m') " . ($from ?? '') . " " . ($till ?? '') . "
		  GROUP BY dx.prefix,dx.name , CASE
		  WHEN q.col_mode = 'CW' THEN 'C'
		  WHEN mo.qrgmode = 'DATA' THEN 'R'
		  WHEN mo.qrgmode = 'SSB' THEN 'F'
		  ELSE mo.qrgmode
		  END,q.COL_BAND order by dx.prefix asc, q.col_band desc";

		$query = $this->db->query($sql, $binding);
		return $query;
	}

	function totals_year() {

		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!$logbooks_locations_array) {
			return null;
		}

		$this->db->select('DATE_FORMAT(COL_TIME_ON, \'%Y\') as \'year\',COUNT(COL_PRIMARY_KEY) as \'total\'', FALSE);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->group_by('DATE_FORMAT(COL_TIME_ON, \'%Y\')');
		$this->db->order_by('year', 'DESC');

		$query = $this->db->get($this->config->item('table_name'));

		return $query;
	}

	/* Return total number of qsos */
	function total_qsos($StationLocationsArray = null, $api_key = null) {
		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			if ($api_key != null) {
				$this->load->model('api_model');
				if (strpos($this->api_model->access($api_key), 'r') !== false) {
					$this->api_model->update_last_used($api_key);
					$user_id = $this->api_model->key_userid($api_key);
					$active_station_logbook = $this->logbooks_model->find_active_station_logbook_from_userid($user_id);
					$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($active_station_logbook);
				} else {
					$logbooks_locations_array = [];
				}
			} else {
				$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
			}
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		if ($logbooks_locations_array) {
			$this->db->select('COUNT( * ) as count', FALSE);
			$this->db->where_in('station_id', $logbooks_locations_array);
			$query = $this->db->get($this->config->item('table_name'));

			if ($query->num_rows() > 0) {
				foreach ($query->result() as $row) {
					return $row->count;
				}
			}
		} else {
			return null;
		}
	}

	/* Return number of QSOs had today */
	function todays_qsos($StationLocationsArray = null, $api_key = null) {
		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			if ($api_key != null) {
				$this->load->model('api_model');
				if (strpos($this->api_model->access($api_key), 'r') !== false) {
					$this->api_model->update_last_used($api_key);
					$user_id = $this->api_model->key_userid($api_key);
					$active_station_logbook = $this->logbooks_model->find_active_station_logbook_from_userid($user_id);
					$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($active_station_logbook);
				} else {
					$logbooks_locations_array = [];
				}
			} else {
				$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
			}
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		if ($logbooks_locations_array) {
			$morning = date('Y-m-d 00:00:00');
			$night = date('Y-m-d 23:59:59');

			$this->db->select('COUNT( * ) as count', FALSE);
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_TIME_ON >=', $morning);
			$this->db->where('COL_TIME_ON <=', $night);
			$query = $this->db->get($this->config->item('table_name'));

			if ($query->num_rows() > 0) {
				foreach ($query->result() as $row) {
					return $row->count;
				}
			}
		} else {
			return null;
		}
	}

	/* Return QSOs over a period of days */
	function map_week_qsos($start, $end) {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		$this->db->where("COL_TIME_ON >= ", $start);
		$this->db->where("COL_TIME_ON <= ", $end);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->order_by("COL_TIME_ON", "ASC");
		$query = $this->db->get($this->config->item('table_name'));

		return $query;
	}

	/* used to return custom qsos requires start, end date plus a band */
	function map_custom_qsos($start, $end, $band, $mode, $propagation) {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!$logbooks_locations_array) {
			return null;
		}

		$this->db->join('dxcc_entities', $this->config->item('table_name') . '.col_dxcc = dxcc_entities.adif', 'left');
		$this->db->where("COL_TIME_ON >=", $start . " 00:00:00");
		$this->db->where("COL_TIME_ON <=", $end . " 23:59:59'");
		$this->db->where_in("station_id", $logbooks_locations_array);

		if ($band != "All" && $band != "SAT") {
			$this->db->where("COL_BAND", $band);
		}

		if ($band == "SAT") {
			$this->db->where("COL_PROP_MODE", "SAT");
		}

		if ($mode != 'All') {
			$this->db->group_start();
			$this->db->where("COL_MODE", $mode);
			$this->db->or_where("COL_SUBMODE", $mode);
			$this->db->group_end();
		}

		if ($propagation != 'All') {
			$this->db->where("COL_PROP_MODE", $propagation);
		}

		$this->db->order_by("COL_TIME_ON", "ASC");
		$query = $this->db->get($this->config->item('table_name'));

		return $query;
	}

	/* Returns QSOs for the date sent eg 2011-09-30 */
	function map_day($date) {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		$start = $date . " 00:00:00";
		$end = $date . " 23:59:59";

		$this->db->where("COL_TIME_ON >= ", $start);
		$this->db->where("COL_TIME_ON <= ", $end);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->order_by("COL_TIME_ON", "ASC");
		$query = $this->db->get($this->config->item('table_name'));

		return $query;
	}

	// Return QSOs made during the current month
	function month_qsos($StationLocationsArray = null, $api_key = null) {
		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			if ($api_key != null) {
				$this->load->model('api_model');
				if (strpos($this->api_model->access($api_key), 'r') !== false) {
					$this->api_model->update_last_used($api_key);
					$user_id = $this->api_model->key_userid($api_key);
					$active_station_logbook = $this->logbooks_model->find_active_station_logbook_from_userid($user_id);
					$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($active_station_logbook);
				} else {
					$logbooks_locations_array = [];
				}
			} else {
				$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
			}
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		if ($logbooks_locations_array) {

			$morning = date('Y-m-01 00:00:00');

			$date = new DateTime('now');
			$date->modify('last day of this month');

			$night = $date->format('Y-m-d') . " 23:59:59";

			$this->db->select('COUNT( * ) as count', FALSE);
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_TIME_ON >=', $morning);
			$this->db->where('COL_TIME_ON <=', $night);
			$query = $this->db->get($this->config->item('table_name'));

			if ($query->num_rows() > 0) {
				foreach ($query->result() as $row) {
					return $row->count;
				}
			}
		} else {
			return null;
		}
	}

	/* Return QSOs made during the current Year */
	function year_qsos($StationLocationsArray = null, $api_key = null) {

		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			if ($api_key != null) {
				$this->load->model('api_model');
				if (strpos($this->api_model->access($api_key), 'r') !== false) {
					$this->api_model->update_last_used($api_key);
					$user_id = $this->api_model->key_userid($api_key);
					$active_station_logbook = $this->logbooks_model->find_active_station_logbook_from_userid($user_id);
					$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($active_station_logbook);
				} else {
					$logbooks_locations_array = [];
				}
			} else {
				$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
			}
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		if ($logbooks_locations_array) {

			$morning = date('Y-01-01 00:00:00');
			$night = date('Y-12-31 23:59:59');

			$this->db->select('COUNT( * ) as count', FALSE);
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_TIME_ON >=', $morning);
			$this->db->where('COL_TIME_ON <=', $night);
			$query = $this->db->get($this->config->item('table_name'));

			if ($query->num_rows() > 0) {
				foreach ($query->result() as $row) {
					return $row->count;
				}
			}
		} else {
			return null;
		}
	}

	private function where_year($yr) {
		if ($yr != 'All') {
			$syr = date($yr.'-01-01 00:00:00');
			$eyr = date($yr.'-12-31 23:59:59');
			$this->db->where('COL_TIME_ON >=', $syr);
			$this->db->where('COL_TIME_ON <=', $eyr);
		}
	}

	/* Return total amount of SSB QSOs logged */
	function total_ssb($yr = 'All') {

		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!$logbooks_locations_array) {
			return null;
		}
		$mode[] = 'SSB';
		$mode[] = 'LSB';
		$mode[] = 'USB';

		$this->db->select('COUNT( * ) as count', FALSE);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where_in('COL_MODE', $mode);
		$this->where_year($yr);
		$query = $this->db->get($this->config->item('table_name'));

		if ($query->num_rows() > 0) {
			foreach ($query->result() as $row) {
				return $row->count;
			}
		}
	}

	/* Return total number of satellite QSOs */
	function total_sat($yr = 'All') {

		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!$logbooks_locations_array) {
			return null;
		}

		$this->db->select('COL_SAT_NAME, COUNT( * ) as count', FALSE);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_SAT_NAME is not null');
		$this->db->where('COL_SAT_NAME !=', '');
		$this->db->where('COL_PROP_MODE', 'SAT');
		$this->where_year($yr);
		$this->db->order_by('count DESC');
		$this->db->group_by('COL_SAT_NAME');
		$query = $this->db->get($this->config->item('table_name'));

		return $query;
	}

	/* Return total number of QSOs per continent */
	function total_continents($searchCriteria) {

		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!$logbooks_locations_array) {
			return null;
		}

		$this->db->select('COL_CONT, COUNT( * ) as count', FALSE);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_CONT is not null');
		$this->db->where('COL_CONT !=', '');
		$this->db->where_in('COL_CONT', ['AF', 'EU', 'AS', 'SA', 'NA', 'OC', 'AN']);

		if ($searchCriteria['mode'] !== '') {
			$this->db->group_start();
			$this->db->where('COL_MODE', $searchCriteria['mode']);
			$this->db->or_where('COL_SUBMODE', $searchCriteria['mode']);
			$this->db->group_end();
		}

		if ($searchCriteria['band'] !== '') {
			if ($searchCriteria['band'] != "SAT") {
				$this->db->where('COL_BAND', $searchCriteria['band']);
				$this->db->where('COL_PROP_MODE != "SAT"');
			} else {
				$this->db->where('COL_PROP_MODE', 'SAT');
			}
		}

		$this->db->order_by('count DESC');
		$this->db->group_by('COL_CONT');
		$query = $this->db->get($this->config->item('table_name'));

		return $query;
	}

	/* Return total number of CW QSOs */
	function total_cw($yr = 'All') {

		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!$logbooks_locations_array) {
			return null;
		}

		$this->db->select('COUNT( * ) as count', FALSE);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_MODE', 'CW');
		$this->where_year($yr);
		$query = $this->db->get($this->config->item('table_name'));

		if ($query->num_rows() > 0) {
			foreach ($query->result() as $row) {
				return $row->count;
			}
		}
	}

	/* Return total number of FM QSOs */
	function total_am($yr = 'All') {

		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!$logbooks_locations_array) {
			return null;
		}

		$this->db->select('COUNT( * ) as count', FALSE);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_MODE', 'AM');
		$this->where_year($yr);
		$query = $this->db->get($this->config->item('table_name'));

		if ($query->num_rows() > 0) {
			foreach ($query->result() as $row) {
				return $row->count;
			}
		}
	}

	function total_fm($yr = 'All') {

		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!$logbooks_locations_array) {
			return null;
		}

		$this->db->select('COUNT( * ) as count', FALSE);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_MODE', 'FM');
		$this->where_year($yr);
		$query = $this->db->get($this->config->item('table_name'));

		if ($query->num_rows() > 0) {
			foreach ($query->result() as $row) {
				return $row->count;
			}
		}
	}

	/* Return total number of Digital QSOs */
	function total_digi($yr = 'All') {

		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!$logbooks_locations_array) {
			return null;
		}

		$this->db->select('COUNT( * ) as count', FALSE);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_MODE !=', 'SSB');
		$this->db->where('COL_MODE !=', 'LSB');
		$this->db->where('COL_MODE !=', 'USB');
		$this->db->where('COL_MODE !=', 'CW');
		$this->db->where('COL_MODE !=', 'FM');
		$this->db->where('COL_MODE !=', 'AM');
		$this->where_year($yr);
		$query = $this->db->get($this->config->item('table_name'));

		if ($query->num_rows() > 0) {
			foreach ($query->result() as $row) {
				return $row->count;
			}
		}
	}

	/* Return total number of QSOs per band */
	function total_bands($yr = 'All') {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!$logbooks_locations_array) {
			return null;
		}

		$this->db->select('COL_BAND AS band, count( * ) AS count', FALSE);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->where_year($yr);
		$this->db->group_by('band');
		$this->db->order_by('count', 'DESC');

		$query = $this->db->get($this->config->item('table_name'));

		return $query;
	}

	/* Return total number of QSOs per operator */
	function total_operators($yr = 'All') {

		//Load logbook model and get station locations
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!$logbooks_locations_array) {
			return null;
		}

		//get statistics from database
		$this->db->select('IFNULL(IF(COL_OPERATOR = "", COL_STATION_CALLSIGN, COL_OPERATOR), COL_STATION_CALLSIGN) AS operator, count( * ) AS count', FALSE);
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->where_year($yr);
		$this->db->group_by('operator');
		$this->db->order_by('count', 'DESC');

		$query = $this->db->get($this->config->item('table_name'));

		//return result
		return $query;
	}

	function get_QSLStats($StationLocationsArray = null) {

		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		if (!empty($logbooks_locations_array)) {
			$this->db->select('
	  COUNT(IF(COL_QSL_SENT="Y",COL_QSL_SENT,null)) as QSL_Sent,
	  COUNT(IF(COL_QSL_RCVD="Y",COL_QSL_RCVD,null)) as QSL_Received,
	  COUNT(IF(COL_QSL_SENT IN("Q", "R") ,COL_QSL_SENT,null)) as QSL_Requested,
	  COUNT(IF(COL_EQSL_QSL_SENT="Y",COL_EQSL_QSL_SENT,null)) as eQSL_Sent,
	  COUNT(IF(COL_EQSL_QSL_RCVD="Y",COL_EQSL_QSL_RCVD,null)) as eQSL_Received,
	  COUNT(IF(COL_LOTW_QSL_SENT="Y",COL_LOTW_QSL_SENT,null)) as LoTW_Sent,
	  COUNT(IF(COL_LOTW_QSL_RCVD="Y",COL_LOTW_QSL_RCVD,null)) as LoTW_Received,
	  COUNT(IF(COL_QRZCOM_QSO_UPLOAD_STATUS="Y",COL_QRZCOM_QSO_UPLOAD_STATUS,null)) as QRZ_Sent,
	  COUNT(IF(COL_QRZCOM_QSO_DOWNLOAD_STATUS="Y",COL_QRZCOM_QSO_DOWNLOAD_STATUS,null)) as QRZ_Received,
	  COUNT(IF(COL_QSL_SENT="Y" and DATE(COL_QSLSDATE)=DATE(SYSDATE()),COL_QSL_SENT,null)) as QSL_Sent_today,
	  COUNT(IF(COL_QSL_RCVD="Y" and DATE(COL_QSLRDATE)=DATE(SYSDATE()),COL_QSL_RCVD,null)) as QSL_Received_today,
	  COUNT(IF(COL_QSL_SENT IN("Q", "R") and DATE(COL_QSLSDATE)=DATE(SYSDATE()) ,COL_QSL_SENT,null)) as QSL_Requested_today,
	  COUNT(IF(COL_EQSL_QSL_SENT="Y" and DATE(COL_EQSL_QSLSDATE)=DATE(SYSDATE()),COL_EQSL_QSL_SENT,null)) as eQSL_Sent_today,
	  COUNT(IF(COL_EQSL_QSL_RCVD="Y" and DATE(COL_EQSL_QSLRDATE)=DATE(SYSDATE()),COL_EQSL_QSL_RCVD,null)) as eQSL_Received_today,
	  COUNT(IF(COL_LOTW_QSL_SENT="Y" and DATE(COL_LOTW_QSLSDATE)=DATE(SYSDATE()),COL_LOTW_QSL_SENT,null)) as LoTW_Sent_today,
	  COUNT(IF(COL_LOTW_QSL_RCVD="Y" and DATE(COL_LOTW_QSLRDATE)=DATE(SYSDATE()),COL_LOTW_QSL_RCVD,null)) as LoTW_Received_today,
	  COUNT(IF(COL_QRZCOM_QSO_UPLOAD_STATUS="Y" and DATE(COL_QRZCOM_QSO_UPLOAD_DATE)=DATE(SYSDATE()),COL_QRZCOM_QSO_UPLOAD_STATUS,null)) as QRZ_Sent_today,
	  COUNT(IF(COL_QRZCOM_QSO_DOWNLOAD_STATUS="Y" and DATE(COL_QRZCOM_QSO_DOWNLOAD_DATE)=DATE(SYSDATE()),COL_QRZCOM_QSO_DOWNLOAD_STATUS,null)) as QRZ_Received_today
	');
			$this->db->where_in('station_id', $logbooks_locations_array);

			if ($query = $this->db->get($this->config->item('table_name'))) {
				$this->db->last_query();
				foreach ($query->result() as $row) {
					$QSLBreakdown['QSL_Sent'] = $row->QSL_Sent;
					$QSLBreakdown['QSL_Received'] =  $row->QSL_Received;
					$QSLBreakdown['QSL_Requested'] =  $row->QSL_Requested;
					$QSLBreakdown['eQSL_Sent'] =  $row->eQSL_Sent;
					$QSLBreakdown['eQSL_Received'] =  $row->eQSL_Received;
					$QSLBreakdown['LoTW_Sent'] =  $row->LoTW_Sent;
					$QSLBreakdown['LoTW_Received'] =  $row->LoTW_Received;
					$QSLBreakdown['QRZ_Sent'] =  $row->QRZ_Sent;
					$QSLBreakdown['QRZ_Received'] =  $row->QRZ_Received;
					$QSLBreakdown['QSL_Sent_today'] = $row->QSL_Sent_today;
					$QSLBreakdown['QSL_Received_today'] =  $row->QSL_Received_today;
					$QSLBreakdown['QSL_Requested_today'] =  $row->QSL_Requested_today;
					$QSLBreakdown['eQSL_Sent_today'] =  $row->eQSL_Sent_today;
					$QSLBreakdown['eQSL_Received_today'] =  $row->eQSL_Received_today;
					$QSLBreakdown['LoTW_Sent_today'] =  $row->LoTW_Sent_today;
					$QSLBreakdown['LoTW_Received_today'] =  $row->LoTW_Received_today;
					$QSLBreakdown['QRZ_Sent_today'] =  $row->QRZ_Sent_today;
					$QSLBreakdown['QRZ_Received_today'] =  $row->QRZ_Received_today;
				}

				return $QSLBreakdown;
			} else {
				$QSLBreakdown['QSL_Sent'] = 0;
				$QSLBreakdown['QSL_Received'] =  0;
				$QSLBreakdown['QSL_Requested'] =  0;
				$QSLBreakdown['eQSL_Sent'] =  0;
				$QSLBreakdown['eQSL_Received'] =  0;
				$QSLBreakdown['LoTW_Sent'] =  0;
				$QSLBreakdown['LoTW_Received'] = 0;
				$QSLBreakdown['QRZ_Sent'] = 0;
				$QSLBreakdown['QRZ_Received'] = 0;
				$QSLBreakdown['QSL_Sent_today'] = 0;
				$QSLBreakdown['QSL_Received_today'] =  0;
				$QSLBreakdown['QSL_Requested_today'] =  0;
				$QSLBreakdown['eQSL_Sent_today'] =  0;
				$QSLBreakdown['eQSL_Received_today'] =  0;
				$QSLBreakdown['LoTW_Sent_today'] =  0;
				$QSLBreakdown['LoTW_Received_today'] = 0;
				$QSLBreakdown['QRZ_Sent_today'] = 0;
				$QSLBreakdown['QRZ_Received_today'] = 0;

				return $QSLBreakdown;
			}
		} else {
			$QSLBreakdown['QSL_Sent'] = 0;
			$QSLBreakdown['QSL_Received'] =  0;
			$QSLBreakdown['QSL_Requested'] =  0;
			$QSLBreakdown['eQSL_Sent'] =  0;
			$QSLBreakdown['eQSL_Received'] =  0;
			$QSLBreakdown['LoTW_Sent'] =  0;
			$QSLBreakdown['LoTW_Received'] = 0;
			$QSLBreakdown['QRZ_Sent'] = 0;
			$QSLBreakdown['QRZ_Received'] = 0;
			$QSLBreakdown['QSL_Sent_today'] = 0;
			$QSLBreakdown['QSL_Received_today'] =  0;
			$QSLBreakdown['QSL_Requested_today'] =  0;
			$QSLBreakdown['eQSL_Sent_today'] =  0;
			$QSLBreakdown['eQSL_Received_today'] =  0;
			$QSLBreakdown['LoTW_Sent_today'] =  0;
			$QSLBreakdown['LoTW_Received_today'] = 0;
			$QSLBreakdown['QRZ_Sent_today'] = 0;
			$QSLBreakdown['QRZ_Received_today'] = 0;

			return $QSLBreakdown;
		}
	}

	/* Return total number of QSL Cards sent */
	function total_qsl_sent() {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!empty($logbooks_locations_array)) {
			$this->db->select('count(COL_QSL_SENT) AS count');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_QSL_SENT =', 'Y');

			$query = $this->db->get($this->config->item('table_name'));

			$row = $query->row();

			if ($row == null) {
				return 0;
			} else {
				return $row->count;
			}
		} else {
			return 0;
		}
	}

	/* Return total number of QSL Cards requested for printing - that means "requested" or "queued" */
	function total_qsl_requested() {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!empty($logbooks_locations_array)) {
			$this->db->select('count(COL_QSL_SENT) AS count');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where_in('COL_QSL_SENT', array('Q', 'R'));

			$query = $this->db->get($this->config->item('table_name'));

			$row = $query->row();

			if ($row == null) {
				return 0;
			} else {
				return $row->count;
			}
		} else {
			return 0;
		}
	}

	/* Return total number of QSL Cards received */
	function total_qsl_rcvd() {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!empty($logbooks_locations_array)) {
			$this->db->select('count(COL_QSL_RCVD) AS count');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_QSL_RCVD =', 'Y');

			$query = $this->db->get($this->config->item('table_name'));

			$row = $query->row();

			if ($row == null) {
				return 0;
			} else {
				return $row->count;
			}
		} else {
			return 0;
		}
	}

	/* Return total number of eQSL Cards sent */
	function total_eqsl_sent() {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!empty($logbooks_locations_array)) {
			$this->db->select('count(COL_EQSL_QSL_SENT) AS count');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_EQSL_QSL_SENT =', 'Y');

			$query = $this->db->get($this->config->item('table_name'));

			$row = $query->row();

			if ($row == null) {
				return 0;
			} else {
				return $row->count;
			}
		} else {
			return 0;
		}
	}

	/* Return total number of eQSL Cards received */
	function total_eqsl_rcvd() {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!empty($logbooks_locations_array)) {
			$this->db->select('count(COL_EQSL_QSL_RCVD) AS count');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_EQSL_QSL_RCVD =', 'Y');

			$query = $this->db->get($this->config->item('table_name'));

			$row = $query->row();

			if ($row == null) {
				return 0;
			} else {
				return $row->count;
			}
		} else {
			return 0;
		}
	}

	/* Return total number of LoTW sent */
	function total_lotw_sent() {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!empty($logbooks_locations_array)) {
			$this->db->select('count(COL_LOTW_QSL_SENT) AS count');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_LOTW_QSL_SENT =', 'Y');

			$query = $this->db->get($this->config->item('table_name'));

			$row = $query->row();

			if ($row == null) {
				return 0;
			} else {
				return $row->count;
			}
		} else {
			return 0;
		}
	}

	/* Return total number of LoTW received */
	function total_lotw_rcvd() {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!empty($logbooks_locations_array)) {
			$this->db->select('count(COL_LOTW_QSL_RCVD) AS count');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_LOTW_QSL_RCVD =', 'Y');

			$query = $this->db->get($this->config->item('table_name'));

			$row = $query->row();

			if ($row == null) {
				return 0;
			} else {
				return $row->count;
			}
		} else {
			return 0;
		}
	}

	/* Return total number of countries worked */
	function total_countries() {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!empty($logbooks_locations_array)) {
			$this->db->select('DISTINCT (COL_COUNTRY)');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_COUNTRY !=', 'Invalid');
			$this->db->where('COL_DXCC >', '0');
			$query = $this->db->get($this->config->item('table_name'));

			return $query->num_rows();
		} else {
			return 0;
		}
	}

	/* Return total number of countries worked */
	function total_countries_current($StationLocationsArray = null) {
		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		if (!empty($logbooks_locations_array)) {
			$this->db->select('DISTINCT (' . $this->config->item('table_name') . '.COL_COUNTRY)');
			$this->db->join('dxcc_entities', 'dxcc_entities.adif = ' . $this->config->item('table_name') . '.col_dxcc');
			$this->db->where_in($this->config->item('table_name') . '.station_id', $logbooks_locations_array);
			$this->db->where($this->config->item('table_name') . '.COL_COUNTRY !=', 'Invalid');
			$this->db->where('dxcc_entities.end is null');
			$this->db->where('dxcc_entities.adif != 0');
			$query = $this->db->get($this->config->item('table_name'));

			return $query->num_rows();
		} else {
			return 0;
		}
	}

	/* Return total number of countries confirmed with along with qsl types confirmed */
	function total_countries_confirmed($StationLocationsArray = null) {

		if ($StationLocationsArray == null) {
			$this->load->model('logbooks_model');
			$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		} else {
			$logbooks_locations_array = $StationLocationsArray;
		}

		if (!empty($logbooks_locations_array)) {
			$this->db->select('COUNT(DISTINCT COL_COUNTRY) as Countries_Worked,
            COUNT(DISTINCT IF(COL_QSL_RCVD = "Y", COL_COUNTRY, NULL)) as Countries_Worked_QSL,
            COUNT(DISTINCT IF(COL_EQSL_QSL_RCVD = "Y", COL_COUNTRY, NULL)) as Countries_Worked_EQSL,
            COUNT(DISTINCT IF(COL_LOTW_QSL_RCVD = "Y", COL_COUNTRY, NULL)) as Countries_Worked_LOTW');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_COUNTRY !=', 'Invalid');
			$this->db->where('COL_DXCC >', '0');

			if ($query = $this->db->get($this->config->item('table_name'))) {
				foreach ($query->result() as $row) {
					$CountriesBreakdown['Countries_Worked'] = $row->Countries_Worked;
					$CountriesBreakdown['Countries_Worked_QSL'] =  $row->Countries_Worked_QSL;
					$CountriesBreakdown['Countries_Worked_EQSL'] =  $row->Countries_Worked_EQSL;
					$CountriesBreakdown['Countries_Worked_LOTW'] =  $row->Countries_Worked_LOTW;
				}

				return $CountriesBreakdown;
			} else {
				$CountriesBreakdown['Countries_Worked'] = 0;
				$CountriesBreakdown['Countries_Worked_QSL'] = 0;
				$CountriesBreakdown['Countries_Worked_EQSL'] = 0;
				$CountriesBreakdown['Countries_Worked_LOTW'] = 0;
				return $CountriesBreakdown;
			}
		} else {
			$CountriesBreakdown['Countries_Worked'] = 0;
			$CountriesBreakdown['Countries_Worked_QSL'] = 0;
			$CountriesBreakdown['Countries_Worked_EQSL'] = 0;
			$CountriesBreakdown['Countries_Worked_LOTW'] = 0;
			return $CountriesBreakdown;
		}
	}

	/* Return total number of countries confirmed with paper QSL */
	function total_countries_confirmed_paper() {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!empty($logbooks_locations_array)) {
			$this->db->select('DISTINCT (COL_COUNTRY)');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_COUNTRY !=', 'Invalid');
			$this->db->where('COL_DXCC >', '0');
			$this->db->where('COL_QSL_RCVD =', 'Y');
			$query = $this->db->get($this->config->item('table_name'));

			return $query->num_rows();
		} else {
			return 0;
		}
	}

	/* Return total number of countries confirmed with eQSL */
	function total_countries_confirmed_eqsl() {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!empty($logbooks_locations_array)) {
			$this->db->select('DISTINCT (COL_COUNTRY)');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_COUNTRY !=', 'Invalid');
			$this->db->where('COL_DXCC >', '0');
			$this->db->where('COL_EQSL_QSL_RCVD =', 'Y');
			$query = $this->db->get($this->config->item('table_name'));

			return $query->num_rows();
		} else {
			return 0;
		}
	}

	/* Return total number of countries confirmed with LoTW */
	function total_countries_confirmed_lotw() {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (!empty($logbooks_locations_array)) {
			$this->db->select('DISTINCT (COL_COUNTRY)');
			$this->db->where_in('station_id', $logbooks_locations_array);
			$this->db->where('COL_COUNTRY !=', 'Invalid');
			$this->db->where('COL_DXCC >', '0');
			$this->db->where('COL_LOTW_QSL_RCVD =', 'Y');
			$query = $this->db->get($this->config->item('table_name'));

			return $query->num_rows();
		} else {
			return 0;
		}
	}

	/* Delete QSO based on the QSO ID */
	function delete($id) {
		if ($this->check_qso_is_accessible($id)) {
			$this->load->model('qsl_model');
			$this->load->model('eqsl_images');

			$this->qsl_model->del_image_for_qso($id);
			$this->eqsl_images->del_image($id);

			$this->db->where('COL_PRIMARY_KEY', $id);
			$this->db->delete($this->config->item('table_name'));

			$this->db->where('qsoid', $id);
			$this->db->delete("oqrs");
		} else {
			return;
		}
	}

	/* Used to check if the qso is already in the database */
	function import_check($datetime, $callsign, $band, $mode, $prop_mode, $sat_name, $station_callsign, $station_ids = null) {
		$binding = [];
		$mode = $this->get_main_mode_from_mode($mode);

		$sql = 'SELECT  COL_PRIMARY_KEY, COL_TIME_ON, COL_CALL, COL_BAND, COL_GRIDSQUARE, COL_ANT_PATH from ' . $this->config->item('table_name') . '
		    WHERE COL_TIME_ON >= DATE_ADD(DATE_FORMAT(?, \'%Y-%m-%d %H:%i\' ), INTERVAL -15 MINUTE )
		    AND COL_TIME_ON <= DATE_ADD(DATE_FORMAT(?, \'%Y-%m-%d %H:%i\' ), INTERVAL 15 MINUTE )
		    AND COL_CALL=?
		    AND COL_STATION_CALLSIGN=?
		    AND COL_BAND=?
		    AND COL_MODE=?';

		$binding[] = $datetime;
		$binding[] = $datetime;
		$binding[] = trim($callsign ?? '');
		$binding[] = trim($station_callsign);
		$binding[] = $band;
		$binding[] = $mode;

		if (($prop_mode ?? '') != '') {
			$sql.=' AND COL_PROP_MODE=?';
			$binding[] = $prop_mode;
		} else {
			$sql.=' AND (COL_PROP_MODE is null OR COL_PROP_MODE=\'\')';
		}

		if (($sat_name ?? '') != '') {
			$sql.=' AND COL_SAT_NAME=?';
			$binding[] = $sat_name;
		} else {
			$sql.=' AND (COL_SAT_NAME is null OR COL_SAT_NAME=\'\')';
		}

		if ((isset($station_ids)) && (($station_ids ?? '') != '')) {
			$sql .= ' AND station_id IN (' . $station_ids . ')';
		}

		$query = $this->db->query($sql, $binding);

		if ($query->num_rows() > 0) {
			$ret = $query->row();
			return ["Found", $ret->COL_PRIMARY_KEY, $ret->COL_GRIDSQUARE, $ret->COL_ANT_PATH];
		} else {
			return ["No Match", 0, '', ''];
		}
	}

	function clublog_update($datetime, $callsign, $band, $qsl_status, $station_callsign, $station_ids) {

		$logbooks_locations_array = explode(",", $station_ids);
		$data = array(
			'COL_CLUBLOG_QSO_DOWNLOAD_DATE' => date('Y-m-d'),
			'COL_CLUBLOG_QSO_DOWNLOAD_STATUS' => $qsl_status,
		);

		$this->db->where('date_format(COL_TIME_ON, \'%Y-%m-%d %H:%i:%s\') = ', $datetime);
		$this->db->where('COL_CALL', $callsign);
		$this->db->where("replace(replace(COL_BAND,'cm',''),'m','')", $band); // no way to achieve a real bandmatch, so fallback to match without unit. e.g.: "6" was provided by Clublog. Do they mean 6m or 6cm?
		$this->db->where('COL_STATION_CALLSIGN', $station_callsign);
		$this->db->where_in('station_id', $logbooks_locations_array);

		if ($this->db->update($this->config->item('table_name') . ' use index (idx_HRD_COL_CALL_station_id)', $data)) {
			unset($data);
			return "Updated";
		} else {
			unset($data);
			return "Not updated";
		}
	}

	function qrz_update($primarykey, $qsl_date, $qsl_status) {

		$data = array(
			'COL_QRZCOM_QSO_DOWNLOAD_DATE' => $qsl_date,
			'COL_QRZCOM_QSO_DOWNLOAD_STATUS' => $qsl_status,
		);

		$this->db->where('COL_PRIMARY_KEY', $primarykey);

		if ($this->db->update($this->config->item('table_name'), $data)) {
			unset($data);
			return "Updated";
		} else {
			unset($data);
			return "Not updated";
		}
	}

	function lotw_update($datetime, $callsign, $band, $qsl_date, $qsl_status, $state, $qsl_gridsquare, $qsl_vucc_grids, $iota, $cnty, $cqz, $ituz, $station_callsign, $qsoid, $station_ids, $dxcc = null, $country = null, $ant_path = null) {

		$data = array(
			'COL_LOTW_QSLRDATE' => $qsl_date,
			'COL_LOTW_QSL_RCVD' => $qsl_status,
			'COL_LOTW_QSL_SENT' => 'Y'
		);
		if ($state != "") {
			$data['COL_STATE'] = $state;
		}
		if ($iota != "") {
			$data['COL_IOTA'] = $iota;
		}

		if ($cnty != "") {
			$data['COL_CNTY'] = $cnty;
		}

		if ($cqz != "") {
			$data['COL_CQZ'] = $cqz;
		}

		if (($dxcc ?? '') != '') {
			$data['COL_DXCC'] = $dxcc;
		}

		if (($country ?? '') != '') {
			$data['COL_COUNTRY'] = $country;
		}

		if ($ituz != "") {
			$data['COL_ITUZ'] = $ituz;
		}

		// Check if QRZ or ClubLog is already uploaded. If so, set qso to reupload to qrz.com (M) or clublog
		$qsql = "SELECT COL_CLUBLOG_QSO_UPLOAD_STATUS as CL_STATE, COL_QRZCOM_QSO_UPLOAD_STATUS as QRZ_STATE, COL_LOTW_QSL_RCVD as LOTW_STATE FROM " . $this->config->item('table_name') . " WHERE COL_PRIMARY_KEY = ? AND station_id IN (" . $station_ids . ')';
		$query = $this->db->query($qsql, $qsoid);
		$row = $query->row();
		if ((($row->QRZ_STATE ?? '') == 'Y') && (($row->LOTW_STATE ?? '') != $qsl_status)) {	// Set ONLY to Modified if lotw-state differs
			$data['COL_QRZCOM_QSO_UPLOAD_STATUS'] = 'M';
		}
		if ((($row->CL_STATE ?? '') == 'Y')  && (($row->LOTW_STATE ?? '') != $qsl_status)) {	// Set ONLY to Modified if lotw-state differs
			$data['COL_CLUBLOG_QSO_UPLOAD_STATUS'] = 'M';
		}

		if ($qsl_gridsquare != "" || $qsl_vucc_grids != "") {
			$this->db->select('station_profile.station_gridsquare as station_gridsquare');
			$this->db->where('COL_PRIMARY_KEY', $qsoid);
			$this->db->join('station_profile', $this->config->item('table_name') . '.station_id = station_profile.station_id', 'left outer');
			$this->db->where('station_profile.station_id in (' . $station_ids . ')');
			$this->db->limit(1);
			$query = $this->db->get($this->config->item('table_name'));
			$row = $query->row();
			$station_gridsquare = '';
			if (isset($row)) {
				$station_gridsquare = $row->station_gridsquare;
			}
			if (!$this->load->is_loaded('Qra')) {
				$this->load->library('Qra');
			}
			if ($qsl_gridsquare != "") {
				$data['COL_VUCC_GRIDS'] = null;
				$data['COL_GRIDSQUARE'] = $qsl_gridsquare;
				$data['COL_DISTANCE'] = $this->qra->distance($station_gridsquare, $qsl_gridsquare, 'K', $ant_path);
			} elseif ($qsl_vucc_grids != "") {
				$data['COL_GRIDSQUARE'] = null;
				$data['COL_VUCC_GRIDS'] = $qsl_vucc_grids;
				$data['COL_DISTANCE'] = $this->qra->distance($station_gridsquare, $qsl_vucc_grids, 'K', $ant_path);
			}
		}
		$this->db->where('COL_PRIMARY_KEY', $qsoid);
		$this->db->where('station_id in (' . $station_ids . ')');

		$this->db->update($this->config->item('table_name'), $data);

		return "Updated";
	}

	function qrz_last_qsl_date($user_id) {
		$sql = "SELECT date_format(MAX(COALESCE(COL_QRZCOM_QSO_DOWNLOAD_DATE, str_to_date('1900-01-01','%Y-%m-%d'))),'%Y-%m-%d') MAXDATE, COUNT(1) as QSOS
		    FROM " . $this->config->item('table_name') . " INNER JOIN station_profile ON (" . $this->config->item('table_name') . ".station_id = station_profile.station_id)
		    WHERE station_profile.user_id=? and station_profile.qrzapikey is not null and COL_QRZCOM_QSO_DOWNLOAD_DATE is not null";
		$query = $this->db->query($sql, array($user_id));
		$row = $query->row();
		if (isset($row) && (($row->QSOS ?? 0) == 0)) {	// Abort / Set LASTQSO to future if no QSO is in Log to prevent processing QRZ-Data
			return '2999-12-31';
		}
		if (isset($row) && (($row->MAXDATE ?? '') != '')) {
			return $row->MAXDATE;
		} else {
			return '1900-01-01';
		}
	}

	function lotw_last_qsl_date($user_id) {
		$sql = "SELECT MAX(COALESCE(COL_LOTW_QSLRDATE, '1900-01-01 00:00:00')) MAXDATE
		    FROM " . $this->config->item('table_name') . " INNER JOIN station_profile ON (" . $this->config->item('table_name') . ".station_id = station_profile.station_id)
		    WHERE station_profile.user_id=" . $user_id . " and COL_LOTW_QSLRDATE is not null";
		$query = $this->db->query($sql);
		$row = $query->row();

		if ($row->MAXDATE != null) {
			return $row->MAXDATE;
		}

		return '1900-01-01 00:00:00.000';
	}

	function import_bulk($records, $station_id = "0", $skipDuplicate = false, $markClublog = false, $markLotw = false, $dxccAdif = false, $markQrz = false, $markEqsl = false, $markHrd = false, $markDcl = false, $skipexport = false, $operatorName = false, $apicall = false, $skipStationCheck = false) {
		$this->load->model('user_model');
		$custom_errors = '';
		$a_qsos = [];
		$amsat_qsos = [];
		$today = time();
		if (!$this->stations->check_station_is_accessible($station_id) && $apicall == false) {
			return 'Station not accessible<br>';
		}
		$station_id_ok = true;
		$station_profile = $this->stations->profile_clean($station_id);
		$amsat_status_upload = $this->user_model->get_user_amsat_status_upload_by_id($station_profile->user_id);

		$options_object = $this->user_options_model->get_options('eqsl_default_qslmsg', array('option_name' => 'key_station_id', 'option_key' => $station_id), $station_profile->user_id)->result();
		$station_qslmsg = (isset($options_object[0]->option_value)) ? $options_object[0]->option_value : '';

		foreach ($records as $record) {
			$one_error = $this->import($record, $station_id, $skipDuplicate, $markClublog, $markLotw, $dxccAdif, $markQrz, $markEqsl, $markHrd, $markDcl, $skipexport, trim($operatorName), $apicall, $skipStationCheck, true, $station_id_ok, $station_profile, $station_qslmsg);
			if ($one_error['error'] ?? '' != '') {
				$custom_errors .= $one_error['error'] . "<br/>";
			} else {	// No Errors / QSO doesn't exist so far
				array_push($a_qsos, $one_error['raw_qso'] ?? '');
				if (isset($record['prop_mode']) && $record['prop_mode'] == 'SAT' && $amsat_status_upload) {
					$amsat_qsodate = strtotime(($record['qso_date'] ?? '1970-01-01') . ' ' . ($record['time_on'] ?? '00:00:00'));
					$date_diff = $today - $amsat_qsodate;
					if ($date_diff >= -300 && $date_diff <= 518400) { // Five minutes grace time to the future and max 6 days back
						$data = array(
							'COL_TIME_ON' => date('Y-m-d', strtotime($record['qso_date'])) . " " . date('H:i:s', strtotime($record['time_on'])),
							'COL_SAT_NAME' => $record['sat_name'],
							'COL_BAND' => $record['band'],
							'COL_BAND_RX' => $record['band_rx'] ?? '',
							'COL_MODE' => $record['mode'],
							'COL_STATION_CALLSIGN' => trim($station_profile->station_callsign),
							'COL_MY_GRIDSQUARE' => $station_profile->station_gridsquare,
						);
						array_push($amsat_qsos, $data);
					}
				}
			}
		}

		// if there are any static map images for this station, remove them so they can be regenerated
		if (!$this->load->is_loaded('staticmap_model')) {
			$this->load->model('staticmap_model');
		}
		$this->staticmap_model->remove_static_map_image($station_id);

		$records = '';
		gc_collect_cycles();
		if (count($a_qsos) > 0) {
			$this->db->insert_batch($this->config->item('table_name'), $a_qsos);
		}
		foreach ($amsat_qsos as $amsat_qso) {
			$this->upload_amsat_status($data);
		}
		return $custom_errors;
	}


	/*
     * $skipDuplicate - used in ADIF import to skip duplicate checking when importing QSOs
     * $markLoTW - used in ADIF import to mark QSOs as exported to LoTW when importing QSOs
     * $dxccAdif - used in ADIF import to determine if DXCC From ADIF is used, or if Wavelog should try to guess
     * $markQrz - used in ADIF import to mark QSOs as exported to QRZ Logbook when importing QSOs
     * $markHrd - used in ADIF import to mark QSOs as exported to HRDLog.net Logbook when importing QSOs
     * $skipexport - used in ADIF import to skip the realtime upload to QRZ Logbook when importing QSOs from ADIF
     */
	function import($record, $station_id = "0", $skipDuplicate = false, $markClublog = false, $markLotw = false, $dxccAdif = false, $markQrz = false, $markEqsl = false, $markHrd = false, $markDcl = false, $skipexport = false, $operatorName = false, $apicall = false, $skipStationCheck = false, $batchmode = false, $station_id_ok = false, $station_profile = null, $station_qslmsg = null) {
		// be sure that station belongs to user
		$this->load->model('stations');
		if ($station_id_ok == false) {
			if (!$this->stations->check_station_is_accessible($station_id) && $apicall == false) {
				return 'Station not accessible<br>';
			}
		}

		if ($station_profile == null) {
			$station_profile = $this->stations->profile_clean($station_id);
		}
		$station_profile_call = $station_profile->station_callsign;

		if (($station_id != 0) && (!(isset($record['station_callsign'])))) {
			$record['station_callsign'] = $station_profile_call;
		}
		if ((!$skipStationCheck) && ($station_id != 0) && (trim(strtoupper($record['station_callsign'])) != trim(strtoupper($station_profile_call)))) {     // Check if station_call from import matches profile ONLY when submitting via GUI.
			$returner['error'] =sprintf(__("Wrong station callsign %s while importing QSO with %s for %s: SKIPPED") .
				"<br>".__("Check %s for hints about errors in ADIF files."),
				'<b>'.htmlentities($record['station_callsign'] ?? '').'</b>',($record['call'] ?? ''),'<b>'.($station_profile_call ?? '').'</b>',"<a target=\"_blank\" href=\"https://github.com/wavelog/Wavelog/wiki/ADIF-file-can't-be-imported\">Wavelog Wiki</a>");
			return ($returner);
		}

		$my_error = "";

		// Join date+time
		$time_on = date('Y-m-d', strtotime($record['qso_date'] ?? '1970-01-01')) . " " . date('H:i:s', strtotime($record['time_on'] ?? '00:00:00'));

		if (($record['call'] ?? '') == '') {
			log_message("Error", "Trying to import QSO without Call for station_id " . $station_id . ". QSO Date/Time: " . $time_on . " Mode: " . ($record['mode'] ?? '') . " Band: " . ($record['band'] ?? ''));
			$returner['error']=__("QSO on")." ".$time_on.": ".__("You tried to import a QSO without any given CALL. This QSO wasn't imported. It's invalid");
			return($returner);
		}

		if (isset($record['time_off'])) {
			if (isset($record['date_off'])) {
				// date_off and time_off set
				$time_off = date('Y-m-d', strtotime($record['date_off'] ?? '1970-01-01 00:00:00')) . ' ' . date('H:i:s', strtotime($record['time_off'] ?? '1970-01-01 00:00:00'));
			} elseif (strtotime($record['time_off']) < strtotime($record['time_on'] ?? '00:00:00')) {
				// date_off is not set, QSO ends next day
				$time_off = date('Y-m-d', strtotime(($record['qso_date']  ?? '1970-01-01 00:00:00') . ' + 1 day')) . ' ' . date('H:i:s', strtotime($record['time_off'] ?? '1970-01-01 00:00:00'));
			} else {
				// date_off is not set, QSO ends same day
				$time_off = date('Y-m-d', strtotime($record['qso_date'] ?? '1970-01-01 00:00:00')) . ' ' . date('H:i:s', strtotime($record['time_off'] ?? '1970-01-01 00:00:00'));
			}
		} else {
			// date_off and time_off not set, QSO end == QSO start
			$time_off = $time_on;
		}

		// Store Freq
		// Check if 'freq' is defined in the import?
		if (isset($record['freq'])) { // record[freq] in MHz
			$freq = floatval($record['freq']) * 1E6; // store in Hz
		} else {
			$freq = 0;
		}

		// Check for RX Freq
		// Check if 'freq' is defined in the import?
		if (isset($record['freq_rx'])) { // record[freq] in MHz
			$freqRX = floatval($record['freq_rx']) * 1E6; // store in Hz
		} else {
			$freqRX = NULL;
		}

		// Store Band
		if (isset($record['band'])) {
			$band = strtolower($record['band']);
		} else {
			if (isset($record['freq'])) {
				if ($freq != "0") {
					$band = $this->frequency->GetBand($freq) ?? '';
				}
			}
		}

		if (isset($record['band_rx'])) {
			$band_rx = strtolower($record['band_rx']);
		} else {
			if (isset($record['freq_rx'])) {
				if ($freq != "0") {
					$band_rx = $this->frequency->GetBand($freqRX);
				}
			} else {
				$band_rx = "";
			}
		}

		if (isset($record['mode'])) {
			if (strlen($record['mode']) <= 12) { // COL_MODE is VARCHAR(12)
				$input_mode = $record['mode'];
			} else {
				log_message('error', 'ADIF Import: Mode too long: ' . $record['mode'] . ' for QSO with call: ' . $record['call'] . ' at date ' . $record['qso_date']);
				$input_mode = '';
			}
		} else {
			$input_mode = '';
		}

		$mode = $this->get_main_mode_if_submode($input_mode);
		if ($mode == null) {
			$submode = null;
		} else {
			$submode = $input_mode;
			$input_mode = $mode;
		}

		if (empty($submode)) {
			$input_submode = (!empty($record['submode'])) ? $record['submode'] : '';
		} else {
			$input_submode = $submode;
		}

		$input_submode = (($input_submode ?? '') == '') ? null : $input_submode;	// Make Sure submode is NULL if empty
		$input_mode = (($input_mode ?? '') == '') ? null : $input_mode;	// Make Sure mode is NULL if empty


		// Check if QSO is already in the database
		if ($skipDuplicate != NULL) {
			$skip = false;
		} else {
			if (isset($record['call'])) {
				$this->db->where('COL_CALL', $record['call']);
			}
			$this->db->where("DATE_FORMAT(COL_TIME_ON, '%Y-%m-%d %H:%i') = DATE_FORMAT(\"" . $time_on . "\", '%Y-%m-%d %H:%i')");
			$this->db->where('COL_BAND', $band ?? '');
			$this->db->where('COL_MODE', $input_mode);
			$this->db->where('station_id', $station_id);
			$check = $this->db->get($this->config->item('table_name'));

			// If dupe is not found, set variable to add QSO
			if ($check->num_rows() <= 0) {
				$skip = false;
			} else {
				$skip = true;
			}
		}

		if (!($skip)) {
			// DXCC id
			if (isset($record['call'])) {
				if ($dxccAdif != NULL) {
					if (isset($record['dxcc'])) {
						$entity = $this->get_entity($record['dxcc']);
						$dxcc = array($record['dxcc'] ?? '', $entity['name'] ?? '');
					} else {
						$dxcc = $this->check_dxcc_table($record['call'], $time_off);
					}
				} else {
					$dxcc = $this->check_dxcc_table($record['call'], $time_off);
				}
			} else {
				$dxcc = NULL;
			}

			// Store or find country name
			// dxcc has higher priority to be consistent with qso create and edit
			if (isset($dxcc[1])) {
				$country = ucwords(strtolower($dxcc[1]), "- (/");
			} else if (isset($record['country'])) {
				$country = $record['country'];
			}

			// RST recevied
			if (isset($record['rst_rcvd'])) {
				$rst_rx = $record['rst_rcvd'];
			} else {
				$rst_rx = "";
			}

			// RST Sent
			if (isset($record['rst_sent'])) {
				$rst_tx = $record['rst_sent'];
			} else {
				$rst_tx = "";
			}

			if (isset($record['cqz'])) {
				$cq_zone = $record['cqz'];
			} elseif (isset($dxcc[2])) {
				$cq_zone = $dxcc[2];
			} else {
				$cq_zone = NULL;
			}

			// Sanitise gridsquare input to make sure its a gridsquare
			if (isset($record['gridsquare'])) {
				$a_grids = explode(',', $record['gridsquare']);	// Split at , if there are junctions
				foreach ($a_grids as $singlegrid) {
					$singlegrid = strtoupper($singlegrid);
					if (strlen($singlegrid) == 4)  $singlegrid .= "LL";     // Only 4 Chars? Fill with center "LL" as only A-R allowed
					if (strlen($singlegrid) == 6)  $singlegrid .= "55";     // Only 6 Chars? Fill with center "55"
					if (strlen($singlegrid) == 8)  $singlegrid .= "LL";     // Only 8 Chars? Fill with center "LL" as only A-R allowed
					if (strlen($singlegrid) % 2 != 0) {	// Check if grid is structually valid
						$record['gridsquare'] = '';	// If not: Set to ''
					} else {
						if (!preg_match('/^[A-R]{2}[0-9]{2}[A-X]{2}[0-9]{2}[A-X]{2}$/', $singlegrid)) $record['gridsquare'] = '';
					}
				}
				$input_gridsquare = $record['gridsquare'];
			} else {
				$input_gridsquare = NULL;
			}

			// Sanitise vucc-gridsquare input to make sure its a gridsquare
			if (isset($record['vucc_grids'])) {
				$a_grids = explode(',', $record['vucc_grids']);	// Split at , if there are junctions
				foreach ($a_grids as $singlegrid) {
					$singlegrid = strtoupper(trim($singlegrid));
					if (strlen($singlegrid) == 4)  $singlegrid .= "LL";     // Only 4 Chars? Fill with center "LL" as only A-R allowed
					if (strlen($singlegrid) == 6)  $singlegrid .= "55";     // Only 6 Chars? Fill with center "55"
					if (strlen($singlegrid) == 8)  $singlegrid .= "LL";     // Only 8 Chars? Fill with center "LL" as only A-R allowed
					if (strlen($singlegrid) % 2 != 0) {	// Check if grid is structually valid
						$record['vucc_grids'] = '';	// If not: Set to ''
					} else {
						if (!preg_match('/^[A-R]{2}[0-9]{2}[A-X]{2}[0-9]{2}[A-X]{2}$/', $singlegrid)) $record['vucc_grids'] = '';
					}
				}
				$input_vucc_grids = preg_replace('/\s+/', '', $record['vucc_grids']);
			} else {
				$input_vucc_grids = NULL;
			}

			// Sanitise lat input to make sure its 11 chars
			if (isset($record['lat'])) {
				$input_lat = mb_strimwidth($record['lat'], 0, 11);
			} else {
				$input_lat = NULL;
			}

			// Sanitise lon input to make sure its 11 chars
			if (isset($record['lon'])) {
				$input_lon = mb_strimwidth($record['lon'], 0, 11);
			} else {
				$input_lon = NULL;
			}

			// Sanitise my_lat input to make sure its 11 chars
			if (isset($record['my_lat'])) {
				$input_my_lat = mb_strimwidth($record['my_lat'], 0, 11);
			} else {
				$input_my_lat = NULL;
			}

			// Sanitise my_lon input to make sure its 11 chars
			if (isset($record['my_lon'])) {
				$input_my_lon = mb_strimwidth($record['my_lon'], 0, 11);
			} else {
				$input_my_lon = NULL;
			}

			// Sanitise TX_POWER
			if (isset($record['tx_pwr'])) {
				$tx_pwr = filter_var($record['tx_pwr'], FILTER_VALIDATE_FLOAT);
			} else {
				$tx_pwr = $station_profile->station_power ?? NULL;
			}

			// Sanitise RX Power
			if (isset($record['rx_pwr'])) {
				switch (strtoupper($record['rx_pwr'])) {
					case 'K':
						$rx_pwr = 1000;
						break;
					case 'KW':
						$rx_pwr = 1000;
						break;
					case '1TT':
						$rx_pwr = 100;
						break;
					case 'ETT':
						$rx_pwr = 100;
						break;
					case 'NN':
						$rx_pwr = 99;
						break;
					default:
						$rx_pwr = filter_var($record['rx_pwr'], FILTER_VALIDATE_FLOAT);
				}
			} else {
				$rx_pwr = NULL;
			}

			if (isset($record['a_index'])) {
				$input_a_index = filter_var($record['a_index'], FILTER_SANITIZE_NUMBER_INT);
			} else {
				$input_a_index = NULL;
			}

			if (isset($record['age']) && (is_numeric($record['age']))) {
				$input_age = filter_var($record['age'], FILTER_SANITIZE_NUMBER_INT);
			} else {
				$input_age = NULL;
			}

			if (isset($record['ant_az'])) {
				$input_ant_az = filter_var($record['ant_az'], FILTER_VALIDATE_FLOAT);
				$input_ant_az = fmod($input_ant_az, 360);
			} else {
				$input_ant_az = NULL;
			}

			if (isset($record['ant_el'])) {
				$input_ant_el = filter_var($record['ant_el'], FILTER_VALIDATE_FLOAT);
				$input_ant_el = fmod($input_ant_el, 90);
			} else {
				$input_ant_el = NULL;
			}

			if (isset($record['ant_path'])) {
				$input_ant_path = strtoupper(mb_strimwidth($record['ant_path'], 0, 1));
				if ($input_ant_path != 'G' && $input_ant_path != 'O' && $input_ant_path != 'S' && $input_ant_path != 'L') {
					$input_ant_path = NULL;
				}
			} else {
				$input_ant_path = NULL;
			}

			/*
	  Validate QSL Fields
	 qslrdate, qslsdate
	 */

			if (($record['qslrdate'] ?? '') != '') {
				if (validateADIFDate($record['qslrdate']) == true) {
					$input_qslrdate = $record['qslrdate'];
				} else {
					$input_qslrdate = NULL;
					$my_error .= "Error QSO: Date: " . $time_on . " Callsign: " . $record['call'] . " ".__("the qslrdate is invalid (YYYYMMDD)").": " . $record['qslrdate'] . "<br>";
				}
			} else {
				$input_qslrdate = NULL;
			}

			if (($record['qslsdate'] ?? '') != '') {
				if (validateADIFDate($record['qslsdate']) == true) {
					$input_qslsdate = $record['qslsdate'];
				} else {
					$input_qslsdate = NULL;
					$my_error .= "Error QSO: Date: " . $time_on . " Callsign: " . $record['call'] . " ".__("the qslsdate is invalid (YYYYMMDD)").": " . $record['qslsdate'] . "<br>";
				}
			} else {
				$input_qslsdate = NULL;
			}

			if (isset($record['qsl_rcvd'])) {
				$input_qsl_rcvd = mb_strimwidth($record['qsl_rcvd'], 0, 1);
			} else {
				$input_qsl_rcvd = "N";
			}

			if (isset($record['qsl_rcvd_via'])) {
				$input_qsl_rcvd_via = mb_strimwidth($record['qsl_rcvd_via'], 0, 1);
			} else {
				$input_qsl_rcvd_via = "";
			}

			if (isset($record['qsl_sent'])) {
				$input_qsl_sent = mb_strimwidth($record['qsl_sent'], 0, 1);
			} else {
				$input_qsl_sent = "N";
			}

			if (isset($record['qsl_sent_via'])) {
				$input_qsl_sent_via = mb_strimwidth($record['qsl_sent_via'], 0, 1);
			} else {
				$input_qsl_sent_via = "";
			}

			// Try to import the QSL Message from the ADIF file, otherwise use the default message from the station profile
			if (isset($record['qslmsg'])) {
				$qslmsg = $record['qslmsg'];
			} else {
				$qslmsg = $station_qslmsg;
			}

			// Only import SIG_INFO and SIG_INFO_INTL if SIG is set. Discard otherwise as we do not now which activity group the reference belongs to
			if (empty($record['sig'])) {
				$sig_info = $sig_info_intl = '';
			} else {
				$sig_info = $record['sig_info'] ?? '';
				$sig_info_intl = $record['sig_info_intl'] ?? '';
			}

			// Validate Clublog-Fields
			if ($markClublog != NULL) {
				$input_clublog_qsl_sent = "Y";
			} elseif (isset($record['clublog_qso_upload_status'])) {
				$input_clublog_qsl_sent = mb_strimwidth($record['clublog_qso_upload_status'], 0, 1);
			} else {
				$input_clublog_qsl_sent = NULL;
			}

			if ($markClublog != NULL) {
				$input_clublog_qslsdate = $date = date("Y-m-d H:i:s", strtotime("now"));
			} elseif (($record['clublog_qso_upload_date'] ?? '') != '') {
				if (validateADIFDate($record['clublog_qso_upload_date']) == true) {
					$input_clublog_qslsdate = $record['clublog_qso_upload_date'];
				} else {
					$input_clublog_qslsdate = NULL;
					$my_error .= "Error QSO: Date: " . $time_on . " Callsign: " . $record['call'] . " ".__("the clublog_qso_upload_date is invalid (YYYYMMDD)").": " . $record['clublog_qso_upload_date'] . "<br>";
				}
			} else {
				$input_clublog_qslsdate = NULL;
			}

			/*
	  Validate LoTW Fields
	 */
			if (isset($record['lotw_qsl_rcvd'])) {
				$input_lotw_qsl_rcvd = mb_strimwidth($record['lotw_qsl_rcvd'], 0, 1);
			} else {
				$input_lotw_qsl_rcvd = NULL;
			}

			if (($record['lotw_qslrdate'] ?? '') != '') {
				if (validateADIFDate($record['lotw_qslrdate']) == true) {
					$input_lotw_qslrdate = $record['lotw_qslrdate'];
				} else {
					$input_lotw_qslrdate = NULL;
					$my_error .= "Error QSO: Date: " . $time_on . " Callsign: " . $record['call'] . " ".__("the lotw_qslrdate is invalid (YYYYMMDD)").": " . $record['lotw_qslrdate'] . "<br>";
				}
			} else {
				$input_lotw_qslrdate = NULL;
			}

			if ($markLotw != NULL) {
				$input_lotw_qsl_sent = "Y";
			} elseif (isset($record['lotw_qsl_sent'])) {
				$input_lotw_qsl_sent = mb_strimwidth($record['lotw_qsl_sent'], 0, 1);
			} else {
				$input_lotw_qsl_sent = NULL;
			}

			if ($markLotw != NULL) {
				$input_lotw_qslsdate = $date = date("Y-m-d H:i:s", strtotime("now"));
			} elseif (($record['lotw_qslsdate'] ?? '') != '') {
				if (validateADIFDate($record['lotw_qslsdate']) == true) {
					$input_lotw_qslsdate = $record['lotw_qslsdate'];
				} else {
					$input_lotw_qslsdate = NULL;
					$my_error .= "Error QSO: Date: " . $time_on . " Callsign: " . $record['call'] . " ".__("the lotw_qslsdate is invalid (YYYYMMDD)").": " . $record['lotw_qslsdate'] . "<br>";
				}
			} else {
				$input_lotw_qslsdate = NULL;
			}

			// Get active station_id from station profile if one hasn't been provided
			if ($station_id == "" || $station_id == "0") {
				$this->load->model('stations');
				$station_id = $this->stations->find_active();
			}


			if ($operatorName != false) {
				$operatorName = $this->session->userdata('operator_callsign');
			} else {
				$operatorName = (!empty($record['operator'])) ? $record['operator'] : '';
			}

			// If user checked to mark QSOs as uploaded to QRZ or HRDLog Logbook, or else we try to find info in ADIF import.
			if ($markHrd != null) {
				$input_hrdlog_qso_upload_status = 'Y';
				$input_hrdlog_qso_upload_date = $date = date("Y-m-d H:i:s", strtotime("now"));
			} else {
				$input_hrdlog_qso_upload_date = (!empty($record['hrdlog_qso_upload_date'])) ? $record['hrdlog_qso_upload_date'] : null;
				$input_hrdlog_qso_upload_status = (!empty($record['hrdlog_qso_upload_status'])) ? $record['hrdlog_qso_upload_status'] : '';
			}

			if ($markQrz != null) {
				$input_qrzcom_qso_upload_status = 'Y';
				$input_qrzcom_qso_upload_date = $date = date("Y-m-d H:i:s", strtotime("now"));
			} else {
				$input_qrzcom_qso_upload_date = (!empty($record['qrzcom_qso_upload_date'])) ? $record['qrzcom_qso_upload_date'] : null;
				$input_qrzcom_qso_upload_status = (!empty($record['qrzcom_qso_upload_status'])) ? $record['qrzcom_qso_upload_status'] : '';
			}

			if ($markEqsl != null) {
				$input_eqsl_qso_upload_status = 'Y';
				$input_eqsl_qso_upload_date = $date = date("Y-m-d H:i:s", strtotime("now"));
			} else {
				$input_eqsl_qso_upload_date = (!empty($record['eqsl_qslsdate'])) ? $record['eqsl_qslsdate'] : null;
				$input_eqsl_qso_upload_status = (!empty($record['eqsl_qsl_sent'])) ? $record['eqsl_qsl_sent'] : '';
			}

			if ($markDcl != null) {
				$input_dcl_qso_upload_status = 'Y';
				$input_dcl_qso_upload_date = $date = date("Y-m-d H:i:s", strtotime("now"));
			} else {
				$input_dcl_qso_upload_date = (!empty($record['dcl_qslsdate'])) ? $record['dcl_qslsdate'] : null;
				$input_dcl_qso_upload_status = (!empty($record['dcl_qsl_sent'])) ? $record['dcl_qsl_sent'] : '';
			}

			$distance=null;
			if ((!empty($record['distance'])) && (is_numeric($record['distance']))) {
				$distance=$record['distance'];
			} else {
				$distance=null;
			}

			// Create array with QSO Data use ?:
			$data = array(
				'COL_A_INDEX' => is_numeric($input_a_index) ? $input_a_index : null,
				'COL_ADDRESS' => (!empty($record['address'])) ? $record['address'] : '',
				'COL_ADDRESS_INTL' => (!empty($record['address_intl'])) ? $record['address_intl'] : '',
				'COL_AGE' => $input_age,
				'COL_ANT_AZ' => $input_ant_az,
				'COL_ANT_EL' => $input_ant_el,
				'COL_ANT_PATH' => $input_ant_path,
				'COL_ARRL_SECT' => (!empty($record['arrl_sect'])) ? $record['arrl_sect'] : '',
				'COL_AWARD_GRANTED' => (!empty($record['award_granted'])) ? $record['award_granted'] : '',
				'COL_AWARD_SUBMITTED' => (!empty($record['award_submitted'])) ? $record['award_submitted'] : '',
				'COL_BAND' => $band ?? '',
				'COL_BAND_RX' => $band_rx ?? '',
				'COL_BIOGRAPHY' => (!empty($record['biography'])) ? $record['biography'] : '',
				'COL_CALL' => trim((!empty($record['call'])) ? strtoupper($record['call']) : ''),
				'COL_CHECK' => (!empty($record['check'])) ? $record['check'] : '',
				'COL_CLASS' => (!empty($record['class'])) ? $record['class'] : '',
				'COL_CLUBLOG_QSO_UPLOAD_DATE' => $input_clublog_qslsdate,
				'COL_CLUBLOG_QSO_UPLOAD_STATUS' => $input_clublog_qsl_sent,
				'COL_CNTY' => (!empty($record['cnty'])) ? $record['cnty'] : '',
				'COL_CNTY_ALT' => (!empty($record['cnty_alt'])) ? $record['cnty_alt'] : '',
				'COL_COMMENT' => (!empty($record['comment'])) ? $record['comment'] : '',
				'COL_COMMENT_INTL' => (!empty($record['comment_intl'])) ? $record['comment_intl'] : '',
				'COL_CONT' => (!empty($record['cont'])) ? $record['cont'] : '',
				'COL_CONTACTED_OP' => (!empty($record['contacted_op'])) ? $record['contacted_op'] : '',
				'COL_CONTEST_ID' => (!empty($record['contest_id'])) ? $record['contest_id'] : '',
				'COL_COUNTRY' => $country ?? '',
				'COL_COUNTRY_INTL' => (!empty($record['country_intl'])) ? $record['country_intl'] : '',
				'COL_CQZ' => $cq_zone,
				'COL_CREDIT_GRANTED' => (!empty($record['credit_granted'])) ? $record['credit_granted'] : '',
				'COL_CREDIT_SUBMITTED' => (!empty($record['credit_submitted'])) ? $record['credit_submitted'] : '',
				'COL_DARC_DOK' => (!empty($record['darc_dok'])) ? strtoupper($record['darc_dok']) : '',
				'COL_DISTANCE' => $distance,
				'COL_DXCC' => $dxcc[0],
				'COL_EMAIL' => (!empty($record['email'])) ? $record['email'] : '',
				'COL_EQ_CALL' => (!empty($record['eq_call'])) ? $record['eq_call'] : '',
				'COL_EQSL_QSL_RCVD' => (!empty($record['eqsl_qsl_rcvd'])) ? $record['eqsl_qsl_rcvd'] : null,
				'COL_EQSL_QSL_SENT' => $input_eqsl_qso_upload_status,
				'COL_EQSL_QSLRDATE' => (!empty($record['eqsl_qslrdate'])) ? $record['eqsl_qslrdate'] : null,
				'COL_EQSL_QSLSDATE' => $input_eqsl_qso_upload_date,
				'COL_EQSL_STATUS' => (!empty($record['eqsl_status'])) ? $record['eqsl_status'] : '',
				'COL_FISTS' => (!empty($record['fists'])) ? $record['fists'] : null,
				'COL_FISTS_CC' => (!empty($record['fists_cc'])) ? $record['fists_cc'] : null,
				'COL_FORCE_INIT' => (!empty($record['force_init'])) ? $record['force_init'] : null,
				'COL_FREQ' => $freq,
				'COL_FREQ_RX' => (!empty($record['freq_rx'])) ? $freqRX : null,
				'COL_GRIDSQUARE' => $input_gridsquare,
				'COL_HEADING' => (!empty($record['heading'])) ? $record['heading'] : null,
				'COL_HRDLOG_QSO_UPLOAD_DATE' => (!empty($record['hrdlog_qso_upload_date'])) ? $record['hrdlog_qso_upload_date'] : null,
				'COL_HRDLOG_QSO_UPLOAD_STATUS' => (!empty($record['hrdlog_qso_upload_status'])) ? $record['hrdlog_qso_upload_status'] : '',
				'COL_IOTA' => (!empty($record['iota'])) ? $record['iota'] : '',
				'COL_ITUZ' => (!empty($record['ituz'])) ? $record['ituz'] : null,
				'COL_K_INDEX' => (!empty($record['k_index'])) ? $record['k_index'] : null,
				'COL_LAT' => $input_lat,
				'COL_LON' => $input_lon,
				'COL_LOTW_QSL_RCVD' => $input_lotw_qsl_rcvd,
				'COL_LOTW_QSL_SENT' => $input_lotw_qsl_sent,
				'COL_LOTW_QSLRDATE' => $input_lotw_qslrdate,
				'COL_LOTW_QSLSDATE' => $input_lotw_qslsdate,
				'COL_LOTW_STATUS' => (!empty($record['lotw_status'])) ? $record['lotw_status'] : '',
				'COL_MAX_BURSTS' => (!empty($record['max_bursts'])) ? $record['max_bursts'] : null,
				'COL_MODE' => $input_mode,
				'COL_MS_SHOWER' => (!empty($record['ms_shower'])) ? $record['ms_shower'] : '',
				'COL_MY_ANTENNA' => (!empty($record['my_antenna'])) ? $record['my_antenna'] : '',
				'COL_MY_ANTENNA_INTL' => (!empty($record['my_antenna_intl'])) ? $record['my_antenna_intl'] : '',
				'COL_MY_CITY' => (!empty($record['my_city'])) ? $record['my_city'] : '',
				'COL_MY_CITY_INTL' => (!empty($record['my_city_intl'])) ? $record['my_city_intl'] : '',
				'COL_MY_CNTY' => (!empty($record['my_cnty'])) ? $record['my_cnty'] : '',
				'COL_MY_CNTY_ALT' => (!empty($record['my_cnty_alt'])) ? $record['my_cnty_alt'] : '',
				'COL_MY_COUNTRY' => (!empty($record['my_country'])) ? $record['my_country'] : '',
				'COL_MY_COUNTRY_INTL' => (!empty($record['my_country_intl'])) ? $record['my_country_intl'] : null,
				'COL_MY_CQ_ZONE' => (!empty($record['my_dxcc'])) ? $record['my_dxcc'] : null,
				'COL_MY_DARC_DOK' => (!empty($record['my_darc_dok'])) ? strtoupper($record['my_darc_dok']) : '',
				'COL_MY_DXCC' => (!empty($record['my_dxcc'])) ? $record['my_dxcc'] : null,
				'COL_MY_FISTS' => (!empty($record['my_fists'])) ? $record['my_fists'] : null,
				'COL_MY_GRIDSQUARE' => (!empty($record['my_gridsquare'])) ? $record['my_gridsquare'] : '',
				'COL_MY_IOTA' => (!empty($record['my_iota'])) ? $record['my_iota'] : '',
				'COL_MY_IOTA_ISLAND_ID' => (!empty($record['my_iota_island_id'])) ? $record['my_iota_island_id'] : '',
				'COL_MY_ITU_ZONE' => (!empty($record['my_itu_zone'])) ? $record['my_itu_zone'] : null,
				'COL_MY_LAT' => $input_my_lat,
				'COL_MY_LON' => $input_my_lon,
				'COL_MY_NAME' => (!empty($record['my_name'])) ? $record['my_name'] : '',
				'COL_MY_NAME_INTL' => (!empty($record['my_name_intl'])) ? $record['my_name_intl'] : '',
				'COL_MY_POSTAL_CODE' => (!empty($record['my_postal_code'])) ? $record['my_postal_code'] : '',
				'COL_MY_POSTCODE_INTL' => (!empty($record['my_postcode_intl'])) ? $record['my_postcode_intl'] : '',
				'COL_MY_RIG' => (!empty($record['my_rig'])) ? $record['my_rig'] : '',
				'COL_MY_RIG_INTL' => (!empty($record['my_rig_intl'])) ? $record['my_rig_intl'] : '',
				'COL_MY_SIG' => (!empty($record['my_sig'])) ? $record['my_sig'] : '',
				'COL_MY_SIG_INFO' => (!empty($record['my_sig_info'])) ? $record['my_sig_info'] : '',
				'COL_MY_SIG_INFO_INTL' => (!empty($record['my_sig_info_intl'])) ? $record['my_sig_info_intl'] : '',
				'COL_MY_SIG_INTL' => (!empty($record['my_sig_intl'])) ? $record['my_sig_intl'] : '',
				'COL_MY_SOTA_REF' => (!empty($record['my_sota_ref'])) ? $record['my_sota_ref'] : '',
				'COL_MY_WWFF_REF' => (!empty($record['my_wwff_ref'])) ? $record['my_wwff_ref'] : '',
				'COL_MY_POTA_REF' => (!empty($record['my_pota_ref'])) ? $record['my_pota_ref'] : '',
				'COL_MY_STATE' => (!empty($record['my_state'])) ? $record['my_state'] : '',
				'COL_MY_STREET' => (!empty($record['my_street'])) ? $record['my_street'] : '',
				'COL_MY_STREET_INTL' => (!empty($record['my_street_intl'])) ? $record['my_street_intl'] : '',
				'COL_MY_USACA_COUNTIES' => (!empty($record['my_usaca_counties'])) ? $record['my_usaca_counties'] : '',
				'COL_MY_VUCC_GRIDS' => (!empty($record['my_vucc_grids'])) ? $record['my_vucc_grids'] : '',
				'COL_NAME' => (!empty($record['name'])) ? $record['name'] : '',
				'COL_NAME_INTL' => (!empty($record['name_intl'])) ? $record['name_intl'] : '',
				'COL_NOTES' => (!empty($record['notes'])) ? $record['notes'] : '',
				'COL_NOTES_INTL' => (!empty($record['notes_intl'])) ? $record['notes_intl'] : '',
				'COL_NR_BURSTS' => (!empty($record['nr_bursts'])) ? $record['nr_bursts'] : null,
				'COL_NR_PINGS' => (!empty($record['nr_pings'])) ? $record['nr_pings'] : null,
				'COL_OPERATOR' => $operatorName,
				'COL_OWNER_CALLSIGN' => (!empty($record['owner_callsign'])) ? $record['owner_callsign'] : '',
				'COL_PFX' => (!empty($record['pfx'])) ? $record['pfx'] : '',
				'COL_PRECEDENCE' => (!empty($record['precedence'])) ? $record['precedence'] : '',
				'COL_PROP_MODE' => (!empty($record['prop_mode'])) ? $record['prop_mode'] : '',
				'COL_PUBLIC_KEY' => (!empty($record['public_key'])) ? $record['public_key'] : '',
				'COL_HRDLOG_QSO_UPLOAD_DATE' => $input_hrdlog_qso_upload_date,
				'COL_HRDLOG_QSO_UPLOAD_STATUS' => $input_hrdlog_qso_upload_status,
				'COL_QRZCOM_QSO_UPLOAD_DATE' => $input_qrzcom_qso_upload_date,
				'COL_QRZCOM_QSO_UPLOAD_STATUS' => $input_qrzcom_qso_upload_status,
				'COL_DCL_QSLSDATE' => $input_dcl_qso_upload_date,
				'COL_DCL_QSL_SENT' => $input_dcl_qso_upload_status,
				'COL_DCL_QSLRDATE' => (!empty($record['dcl_qslrdate'])) ? $record['dcl_qslrdate'] : null,
				'COL_DCL_QSL_RCVD' => (!empty($record['dcl_qsl_rcvd'])) ? $record['dcl_qsl_rcvd'] : null,
				'COL_QSL_RCVD' => $input_qsl_rcvd,
				'COL_QSL_RCVD_VIA' => $input_qsl_rcvd_via,
				'COL_QSL_SENT' => $input_qsl_sent,
				'COL_QSL_SENT_VIA' => $input_qsl_sent_via,
				'COL_QSL_VIA' => (!empty($record['qsl_via'])) ? $record['qsl_via'] : '',
				'COL_QSLMSG' => $qslmsg,
				'COL_QSLMSG_RCVD' => (!empty($record['qslmsg_rcvd'])) ? $record['qslmsg_rcvd'] : '',
				'COL_QSLRDATE' => $input_qslrdate,
				'COL_QSLSDATE' => $input_qslsdate,
				'COL_QSO_COMPLETE' => (!empty($record['qso_complete'])) ? $record['qso_complete'] : '',
				'COL_QSO_DATE' => (!empty($record['qso_date'])) ? $record['qso_date'] : null,
				'COL_QSO_DATE_OFF' => (!empty($record['qso_date_off'])) ? $record['qso_date_off'] : null,
				'COL_QTH' => (!empty($record['qth'])) ? $record['qth'] : '',
				'COL_QTH_INTL' => (!empty($record['qth_intl'])) ? $record['qth_intl'] : '',
				'COL_REGION' => (!empty($record['region'])) ? $record['region'] : '',
				'COL_RIG' => (!empty($record['rig'])) ? $record['rig'] : '',
				'COL_RIG_INTL' => (!empty($record['rig_intl'])) ? $record['rig_intl'] : '',
				'COL_RST_RCVD' => $rst_rx,
				'COL_RST_SENT' => $rst_tx,
				'COL_RX_PWR' => (is_numeric($rx_pwr) ? $rx_pwr : null),
				'COL_SAT_MODE' => (!empty($record['sat_mode'])) ? $record['sat_mode'] : '',
				'COL_SAT_NAME' => (!empty($record['sat_name'])) ? $record['sat_name'] : '',
				'COL_SFI' => (!empty($record['sfi'])) ? $record['sfi'] : null,
				'COL_SIG' => (!empty($record['sig'])) ? $record['sig'] : '',
				'COL_SIG_INFO' => $sig_info,
				'COL_SIG_INFO_INTL' => $sig_info_intl,
				'COL_SIG_INTL' => (!empty($record['sig_intl'])) ? $record['sig_intl'] : '',
				'COL_SILENT_KEY' => (!empty($record['silent_key'])) ? $record['silent_key'] : '',
				'COL_SKCC' => (!empty($record['skcc'])) ? $record['skcc'] : '',
				'COL_SOTA_REF' => (!empty($record['sota_ref'])) ? $record['sota_ref'] : '',
				'COL_WWFF_REF' => (!empty($record['wwff_ref'])) ? $record['wwff_ref'] : '',
				'COL_POTA_REF' => (!empty($record['pota_ref'])) ? $record['pota_ref'] : '',
				'COL_SRX' => (!empty($record['srx'])) ? (int)$record['srx'] : null,
				//convert to integer to make sure no invalid entries are imported
				'COL_SRX_STRING' => (!empty($record['srx_string'])) ? $record['srx_string'] : '',
				'COL_STATE' => (!empty($record['state'])) ? strtoupper($record['state']) : '',
				'COL_STATION_CALLSIGN' => trim((!empty($record['station_callsign'])) ? $record['station_callsign'] : ''),
				//convert to integer to make sure no invalid entries are imported
				'COL_STX' => (!empty($record['stx'])) ? (int)$record['stx'] : null,
				'COL_STX_STRING' => (!empty($record['stx_string'])) ? $record['stx_string'] : '',
				'COL_SUBMODE' => $input_submode,
				'COL_SWL' => (!empty($record['swl'])) ? $record['swl'] : null,
				'COL_TEN_TEN' => (!empty($record['ten_ten'])) ? $record['ten_ten'] : null,
				'COL_TIME_ON' => $time_on,
				'COL_TIME_OFF' => $time_off,
				'COL_TX_PWR' => (!empty($tx_pwr)) ? $tx_pwr : null,
				'COL_UKSMG' => (!empty($record['uksmg'])) ? $record['uksmg'] : '',
				'COL_USACA_COUNTIES' => (!empty($record['usaca_counties'])) ? $record['usaca_counties'] : '',
				'COL_VUCC_GRIDS' => $input_vucc_grids,
				'COL_WEB' => (!empty($record['web'])) ? $record['web'] : '',
				'COL_MORSE_KEY_INFO' => (!empty($record['morse_key_info'])) ? $record['morse_key_info'] : '',
				'COL_MORSE_KEY_TYPE' => (!empty($record['morse_key_type'])) ? $record['morse_key_type'] : '',
			);

			// Collect field information from the station profile table thats required for the QSO.
			if ($station_id != "0") {
				if (!(array_key_exists($station_id, $this->station_result))) {
					$this->db->select('station_profile.*, dxcc_entities.name as station_country');
					$this->db->where('station_id', $station_id);
					$this->db->join('dxcc_entities', 'station_profile.station_dxcc = dxcc_entities.adif', 'left outer');
					$this->station_result[$station_id] = $this->db->get('station_profile');
				}

				if ($this->station_result[$station_id]->num_rows() > 0) {
					$data['station_id'] = $station_id;

					$row = $this->station_result[$station_id]->row_array();

					if (strpos(trim($row['station_gridsquare']), ',') !== false) {
						$data['COL_MY_VUCC_GRIDS'] = strtoupper(trim($row['station_gridsquare']));
					} else {
						$data['COL_MY_GRIDSQUARE'] = strtoupper(trim($row['station_gridsquare']));
					}

					$data['COL_MY_CITY'] = trim($row['station_city']);
					$data['COL_MY_IOTA'] = strtoupper(trim($row['station_iota'] ?? ''));
					$data['COL_MY_SOTA_REF'] = strtoupper(trim($row['station_sota'] ?? ''));
					$data['COL_MY_WWFF_REF'] = strtoupper(trim($row['station_wwff'] ?? ''));
					$data['COL_MY_POTA_REF'] = strtoupper(trim($row['station_pota'] ?? ''));
					$data['COL_MY_SIG'] = strtoupper(trim($row['station_sig'] ?? ''));
					$data['COL_MY_SIG_INFO'] = strtoupper(trim($row['station_sig_info'] ?? ''));

					$data['COL_STATION_CALLSIGN'] = strtoupper(trim($row['station_callsign']));
					$data['COL_MY_DXCC'] = strtoupper(trim($row['station_dxcc']));
					$data['COL_MY_COUNTRY'] = strtoupper(trim($row['station_country']));
					$data['COL_MY_CNTY'] = strtoupper(trim($row['station_cnty']));
					$data['COL_MY_CQ_ZONE'] = strtoupper(trim($row['station_cq']));
					$data['COL_MY_ITU_ZONE'] = strtoupper(trim($row['station_itu']));
				}
			}

			if ($apicall && (($this->config->item('mqtt_server') ?? '') != '')) {
				$this->load->model('stations');
				$this->load->library('Mh');
				$h_user=$this->stations->get_user_from_station($station_id);
				$event_data=$data;
				$event_data['user_name']=($h_user->user_name ?? '');
				$event_data['user_id']=($h_user->user_id ?? '');
				$this->mh->wl_event('qso/logged/api/'.($h_user->user_id ?? ''), json_encode($event_data));
				unset($event_data);
				unset($h_user);
			}
			// Save QSO
			if ($batchmode) {
				$raw_qso = $this->add_qso($data, $skipexport, $batchmode);
				$returner['raw_qso'] = $raw_qso;
				$data = '';
				$raw_qso = '';
			} else {
				$this->add_qso($data, $skipexport);
			}

		} else {
			$my_error .= "Date/Time: " . ($time_on ?? 'N/A') . " Callsign: " . ($record['call'] ?? 'N/A') . " Band: " . ($band ?? 'N/A') . " ".__("Duplicate for")." ". ($station_profile_call ?? 'N/A') . "<br>";
		}

		if ($batchmode) {
			$returner['error'] = $my_error ?? '';
		} else {
			$returner = $my_error;
		}
		$record = [];
		return $returner;
	}

	function update_dok($record, $ignoreAmbiguous, $onlyConfirmed, $overwriteDok) {
		$this->load->model('logbooks_model');
		$custom_date_format = $this->session->userdata('user_date_format');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		if (isset($record['call'])) {
			$call = strtoupper($record['call']);
		} else {
			return array(3, 'Callsign not found');
		}

		// Join date+time
		$time_on = date('Y-m-d', strtotime($record['qso_date'])) . " " . date('H:i', strtotime($record['time_on']));

		// Store Band
		if (isset($record['band'])) {
			$band = strtolower($record['band']);
		} else {
			if (isset($record['freq'])) {
				if ($record['freq'] != "0") {
					$band = $this->frequency->GetBand($record['freq']);
				}
			}
		}

		if (isset($record['mode'])) {
			$mode = $record['mode'];
		} else {
			$mode = '';
		}

		if (isset($record['darc_dok'])) {
			$darc_dok = $record['darc_dok'];
		} else {
			$darc_dok = '';
		}

		if ($darc_dok != '') {
			$this->db->select('COL_PRIMARY_KEY, COL_DARC_DOK');
			$this->db->where('COL_CALL', $call);
			$this->db->like('COL_TIME_ON', $time_on, 'after');
			$this->db->where('COL_BAND', $band);
			$this->db->where('COL_MODE', $mode);
			$this->db->where_in('station_id', $logbooks_locations_array);
			$check = $this->db->get($this->config->item('table_name'));
			if ($check->num_rows() != 1) {
				if ($ignoreAmbiguous == '1') {
					return array();
				} else {
					return array(2, $result['message'] = "<tr><td>" . date($custom_date_format, strtotime($record['qso_date'])) . "</td><td>" . date('H:i', strtotime($record['time_on'])) . "</td><td>" . str_replace('0', 'Ø', $call) . "</td><td>" . $band . "</td><td>" . $mode . "</td><td></td><td>" . (preg_match('/^[A-Y]\d{2}$/', $darc_dok) ? '<a href="https://www.darc.de/' . $darc_dok . '" target="_blank">' . $darc_dok . '</a>' : (preg_match('/^Z\d{2}$/', $darc_dok) ? '<a href="https://' . $darc_dok . '.vfdb.org" target="_blank">' . $darc_dok . '</a>' : $darc_dok)) . "</td><td>" . __("QSO could not be matched") . "</td></tr>");
				}
			} else {
				$dcl_qsl_status = '';
				switch ($record['app_dcl_status']) {
					case 'c':
						$dcl_qsl_status = __("confirmed by LoTW/Clublog/eQSL/Contest");
						break;
					case 'm':
					case 'n':
					case 'o':
						$dcl_qsl_status = __("confirmed by award manager");
						break;
					case 'i':
						$dcl_qsl_status = __("confirmed by cross-check of DCL data");
						break;
					case 'w':
						$dcl_qsl_status = __("confirmation pending");
						break;
					case 'x':
						$dcl_qsl_status = __("unconfirmed");
						break;
					default:
						$dcl_qsl_status = __("unknown");
				}
				if ($check->row()->COL_DARC_DOK != $darc_dok) {
					$dcl_cnfm = array('c', 'm', 'n', 'o', 'i');
					// Ref https://confluence.darc.de/pages/viewpage.action?pageId=21037270
					if ($onlyConfirmed == '1') {
						if (in_array($record['app_dcl_status'], $dcl_cnfm)) {
							if ($check->row()->COL_DARC_DOK == '' || $overwriteDok == '1') {
								$this->set_dok($check->row()->COL_PRIMARY_KEY, $darc_dok);
								return array(0, '');
							} else {
								return array(1, $result['message'] = "<tr><td>" . date($custom_date_format, strtotime($record['qso_date'])) . "</td><td>" . date('H:i', strtotime($record['time_on'])) . "</td><td><a id=\"edit_qso\" href=\"javascript:displayQso(" . $check->row()->COL_PRIMARY_KEY . ")\">" . str_replace('0', 'Ø', $call) . "</a></td><td>" . $band . "</td><td>" . $mode . "</td><td>" . ($check->row()->COL_DARC_DOK == '' ? 'n/a' : (preg_match('/^[A-Y]\d{2}$/', $check->row()->COL_DARC_DOK) ? '<a href="https://www.darc.de/' . $check->row()->COL_DARC_DOK . '" target="_blank">' . $check->row()->COL_DARC_DOK . '</a>' : (preg_match('/^Z\d{2}$/', $check->row()->COL_DARC_DOK) ? '<a href="https://' . $check->row()->COL_DARC_DOK . '.vfdb.org" target="_blank">' . $check->row()->COL_DARC_DOK . '</a>' : $check->row()->COL_DARC_DOK))) . "</td><td>" . (preg_match('/^[A-Y]\d{2}$/', $darc_dok) ? '<a href="https://www.darc.de/' . $darc_dok . '" target="_blank">' . $darc_dok . '</a>' : (preg_match('/^Z\d{2}$/', $darc_dok) ? '<a href="https://' . $darc_dok . '.vfdb.org" target="_blank">' . $darc_dok . '</a>' : $darc_dok)) . "</td><td>" . $dcl_qsl_status . "</td></tr>");
							}
						} else {
							return array(1, $result['message'] = "<tr><td>" . date($custom_date_format, strtotime($record['qso_date'])) . "</td><td>" . date('H:i', strtotime($record['time_on'])) . "</td><td><a id=\"edit_qso\" href=\"javascript:displayQso(" . $check->row()->COL_PRIMARY_KEY . ")\">" . str_replace('0', 'Ø', $call) . "</a></td><td>" . $band . "</td><td>" . $mode . "</td><td>" . ($check->row()->COL_DARC_DOK == '' ? 'n/a' : (preg_match('/^[A-Y]\d{2}$/', $check->row()->COL_DARC_DOK) ? '<a href="https://www.darc.de/' . $check->row()->COL_DARC_DOK . '" target="_blank">' . $check->row()->COL_DARC_DOK . '</a>' : (preg_match('/^Z\d{2}$/', $check->row()->COL_DARC_DOK) ? '<a href="https://' . $check->row()->COL_DARC_DOK . '.vfdb.org" target="_blank">' . $check->row()->COL_DARC_DOK . '</a>' : $check->row()->COL_DARC_DOK))) . "</td><td>" . (preg_match('/^[A-Y]\d{2}$/', $darc_dok) ? '<a href="https://www.darc.de/' . $darc_dok . '" target="_blank">' . $darc_dok . '</a>' : (preg_match('/^Z\d{2}$/', $darc_dok) ? '<a href="https://' . $darc_dok . '.vfdb.org" target="_blank">' . $darc_dok . '</a>' : $darc_dok)) . "</td><td>" . $dcl_qsl_status . "</td></tr>");
						}
					} else {
						if ($check->row()->COL_DARC_DOK == '' || $overwriteDok == '1') {
							$this->set_dok($check->row()->COL_PRIMARY_KEY, $darc_dok);
							return array(0, '');
						} else {
							return array(1, $result['message'] = "<tr><td>" . date($custom_date_format, strtotime($record['qso_date'])) . "</td><td>" . date('H:i', strtotime($record['time_on'])) . "</td><td><a id=\"edit_qso\" href=\"javascript:displayQso(" . $check->row()->COL_PRIMARY_KEY . ")\">" . str_replace('0', 'Ø', $call) . "</a></td><td>" . $band . "</td><td>" . $mode . "</td><td>" . ($check->row()->COL_DARC_DOK == '' ? 'n/a' : (preg_match('/^[A-Y]\d{2}$/', $check->row()->COL_DARC_DOK) ? '<a href="https://www.darc.de/' . $check->row()->COL_DARC_DOK . '" target="_blank">' . $check->row()->COL_DARC_DOK . '</a>' : (preg_match('/^Z\d{2}$/', $check->row()->COL_DARC_DOK) ? '<a href="https://' . $check->row()->COL_DARC_DOK . '.vfdb.org" target="_blank">' . $check->row()->COL_DARC_DOK . '</a>' : $check->row()->COL_DARC_DOK))) . "</td><td>" . (preg_match('/^[A-Y]\d{2}$/', $darc_dok) ? '<a href="https://www.darc.de/' . $darc_dok . '" target="_blank">' . $darc_dok . '</a>' : (preg_match('/^Z\d{2}$/', $darc_dok) ? '<a href="https://' . $darc_dok . '.vfdb.org" target="_blank">' . $darc_dok . '</a>' : $darc_dok)) . "</td><td>" . $dcl_qsl_status . "</td></tr>");
						}
					}
				}
			}
		}
	}

	function set_dok($key, $dok) {
		$data = array(
			'COL_DARC_DOK' => $dok,
		);

		$this->db->where(array('COL_PRIMARY_KEY' => $key));
		$this->db->update($this->config->item('table_name'), $data);
		return;
	}

	function get_main_mode_from_mode($mode) {
		return ($this->get_main_mode_if_submode($mode) == null ? $mode : $this->get_main_mode_if_submode($mode));
	}

	function get_main_mode_if_submode($mode) {
		if (array_key_exists($mode, $this->oop_modes)) {
			return ($this->oop_modes[$mode][0]);
		} else {
			return null;
		}
	}

	/*
     * Check the dxxc_prefixes table and return (dxcc, country)
     */
	public function check_dxcc_table($call, $date) {

		$date = date("Y-m-d", strtotime($date));
		$csadditions = '/^X$|^D$|^T$|^P$|^R$|^B$|^A$|^M$/';

		$dxcc_exceptions = $this->db->select('`entity`, `adif`, `cqz`, `cont`')
			->where('`call`', $call)
			->where('(start <= ', $date)
			->or_where('start is null)', NULL, false)
			->where('(end >= ', $date)
			->or_where('end is null)', NULL, false)
			->get('dxcc_exceptions');

		if ($dxcc_exceptions->num_rows() > 0) {
			$row = $dxcc_exceptions->row_array();
			return array($row['adif'], $row['entity'], $row['cqz'], $row['cont']);
		}
		if (preg_match('/(^KG4)[A-Z09]{3}/', $call)) {      // KG4/ and KG4 5 char calls are Guantanamo Bay. If 4 or 6 char, it is USA
			$call = "K";
		} elseif (preg_match('/(^OH\/)|(\/OH[1-9]?$)/', $call)) {   # non-Aland prefix!
			$call = "OH";                                             # make callsign OH = finland
		} elseif (preg_match('/(^CX\/)|(\/CX[1-9]?$)/', $call)) {   # non-Antarctica prefix!
			$call = "CX";                                             # make callsign CX = Uruguay
		} elseif (preg_match('/(^3D2R)|(^3D2.+\/R)/', $call)) {     # seems to be from Rotuma
			$call = "3D2/R";                                          # will match with Rotuma
		} elseif (preg_match('/^3D2C/', $call)) {                   # seems to be from Conway Reef
			$call = "3D2/C";                                          # will match with Conway
		} elseif (preg_match('/(^LZ\/)|(\/LZ[1-9]?$)/', $call)) {   # LZ/ is LZ0 by DXCC but this is VP8h
			$call = "LZ";
		} elseif (preg_match('/(^KG4)[A-Z09]{2}/', $call)) {
			$call = "KG4";
		} elseif (preg_match('/(^KG4)[A-Z09]{1}/', $call)) {
			$call = "K";
		} elseif (preg_match('/\w\/\w/', $call)) {
			if (preg_match_all('/^((\d|[A-Z])+\/)?((\d|[A-Z]){3,})(\/(\d|[A-Z])+)?(\/(\d|[A-Z])+)?$/', $call, $matches)) {
				$prefix = $matches[1][0];
				$callsign = $matches[3][0];
				$suffix = $matches[5][0];
				if ($prefix) {
					$prefix = substr($prefix, 0, -1); # Remove the / at the end
				}
				if ($suffix) {
					$suffix = substr($suffix, 1); # Remove the / at the beginning
				};
				if (preg_match($csadditions, $suffix)) {
					if ($prefix) {
						$call = $prefix;
					} else {
						$call = $callsign;
					}
				} else {
					$result = $this->wpx($call, 1);                       # use the wpx prefix instead
					if ($result == '') {
						$row['adif'] = 0;
						$row['entity'] = '- NONE -';
						$row['cqz'] = 0;
						$row['cont'] = '';
						return array($row['adif'], $row['entity'], $row['cqz'], $row['cont']);
					} else {
						$call = $result . "AA";
					}
				}
			}
		}

		$len = strlen($call);
		$dxcc_array = [];
		// Fetch all candidates in one shot instead of looping
		$dxcc_result = $this->db->query("SELECT `call`, `entity`, `adif`, `cqz`, `cont`
		    FROM `dxcc_prefixes`
		    WHERE ? like concat(`call`,'%')
		    and `call` like ?
		    AND (`start` <= ?  OR start is null)
		    AND (`end` >= ?  OR end is null) order by length(`call`) desc limit 1", array($call, substr($call, 0, 1) . '%', $date, $date));

		foreach ($dxcc_result->result_array() as $row) {
			$dxcc_array[$row['call']] = $row;
		}

		// query the table, removing a character from the right until a match
		for ($i = $len; $i > 0; $i--) {
			//printf("searching for %s\n", substr($call, 0, $i));
			if (array_key_exists(substr($call, 0, $i), $dxcc_array)) {
				$row = $dxcc_array[substr($call, 0, $i)];
				// $row = $dxcc_result->row_array();
				return array($row['adif'], $row['entity'], $row['cqz'], $row['cont']);
			}
		}

		return array("Not Found", "Not Found");
	}

	public function dxcc_lookup($call, $date) {

		$date = date("Y-m-d", strtotime($date));
		$csadditions = '/^X$|^D$|^T$|^P$|^R$|^B$|^A$|^M$|^LH$/';

		$dxcc_exceptions = $this->db->select('`entity`, `adif`, `cqz`,`cont`,`long`,`lat`')
			->where('`call`', $call)
			->where('(start <= ', $date)
			->or_where('start is null)', NULL, false)
			->where('(end >= ', $date)
			->or_where('end is null)', NULL, false)
			->get('dxcc_exceptions');
		if ($dxcc_exceptions->num_rows() > 0) {
			$row = $dxcc_exceptions->row_array();
			return $row;
		} else {

			if (preg_match('/(^KG4)[A-Z09]{3}/', $call)) {       // KG4/ and KG4 5 char calls are Guantanamo Bay. If 4 or 6 char, it is USA
				$call = "K";
			} elseif (preg_match('/(^OH\/)|(\/OH[1-9]?$)/', $call)) {   # non-Aland prefix!
				$call = "OH";                                             # make callsign OH = finland
			} elseif (preg_match('/(^CX\/)|(\/CX[1-9]?$)/', $call)) {   # non-Antarctica prefix!
				$call = "CX";                                             # make callsign CX = Uruguay
			} elseif (preg_match('/(^3D2R)|(^3D2.+\/R)/', $call)) {     # seems to be from Rotuma
				$call = "3D2/R";                                          # will match with Rotuma
			} elseif (preg_match('/^3D2C/', $call)) {                   # seems to be from Conway Reef
				$call = "3D2/C";                                          # will match with Conway
			} elseif (preg_match('/(^LZ\/)|(\/LZ[1-9]?$)/', $call)) {   # LZ/ is LZ0 by DXCC but this is VP8h
				$call = "LZ";
			} elseif (preg_match('/(^KG4)[A-Z09]{2}/', $call)) {
				$call = "KG4";
			} elseif (preg_match('/(^KG4)[A-Z09]{1}/', $call)) {
				$call = "K";
			} elseif (preg_match('/\w\/\w/', $call)) {
				if (preg_match_all('/^((\d|[A-Z])+\/)?((\d|[A-Z]){3,})(\/(\d|[A-Z])+)?(\/(\d|[A-Z])+)?$/', $call, $matches)) {
					$prefix = $matches[1][0];
					$callsign = $matches[3][0];
					$suffix = $matches[5][0];
					if ($prefix) {
						$prefix = substr($prefix, 0, -1); # Remove the / at the end
					}
					if ($suffix) {
						$suffix = substr($suffix, 1); # Remove the / at the beginning
					};
					if (preg_match($csadditions, $suffix)) {
						if ($prefix) {
							$call = $prefix;
						} else {
							$call = $callsign;
						}
					} else {
						$result = $this->wpx($call, 1);                       # use the wpx prefix instead
						if ($result == '') {
							$row['adif'] = 0;
							$row['cont'] = '';
							$row['entity'] = '- NONE -';
							$row['ituz'] = 0;
							$row['cqz'] = 0;
							$row['long'] = '0';
							$row['lat'] = '0';
							return $row;
						} else {
							$call = $result . "AA";
						}
					}
				}
			}

			$len = strlen($call);
			$dxcc_array = [];

			// Fetch all candidates in one shot instead of looping
			$dxcc_result = $this->db->query("SELECT `dxcc_prefixes`.`record`, `dxcc_prefixes`.`call`, `dxcc_prefixes`.`entity`, `dxcc_prefixes`.`adif`, `dxcc_prefixes`.`cqz`, `dxcc_entities`.`ituz`, `dxcc_prefixes`.`cont`, `dxcc_prefixes`.`long`, `dxcc_prefixes`.`lat`, `dxcc_prefixes`.`start`, `dxcc_prefixes`.`end`
			    FROM `dxcc_prefixes`
			    LEFT JOIN `dxcc_entities` ON `dxcc_entities`.`adif` = `dxcc_prefixes`.`adif`
			    WHERE ? like concat(`call`,'%')
			    and `dxcc_prefixes`.`call` like ?
			    AND (`dxcc_prefixes`.`start` <= ?  OR `dxcc_prefixes`.`start` is null)
			    AND (`dxcc_prefixes`.`end` >= ?  OR `dxcc_prefixes`.`end` is null) order by length(`call`) desc limit 1", array($call, substr($call, 0, 1) . '%', $date, $date));

			foreach ($dxcc_result->result_array() as $row) {
				$dxcc_array[$row['call']] = $row;
			}

			// query the table, removing a character from the right until a match
			for ($i = $len; $i > 0; $i--) {
				//printf("searching for %s\n", substr($call, 0, $i));
				if (array_key_exists(substr($call, 0, $i), $dxcc_array)) {
					$row = $dxcc_array[substr($call, 0, $i)];
					// $row = $dxcc_result->row_array();
					return $row;
				}
			}
		}

		return array(
			'adif' => 0,
			'cqz' => 0,
			'ituz' => 0,
			'long' => '',
			'lat' => '',
			'entity' => 'None',
		);
	}

	function wpx($testcall, $i) {
		$prefix = '';
		$a = '';
		$b = '';
		$c = '';

		$lidadditions = '/^QRP$|^LGT$/';
		$csadditions = '/^X$|^D$|^T$|^P$|^R$|^B$|^A$|^M$|^LH$/';
		$noneadditions = '/^MM$|^AM$/';

		# First check if the call is in the proper format, A/B/C where A and C
		# are optional (prefix of guest country and P, MM, AM etc) and B is the
		# callsign. Only letters, figures and "/" is accepted, no further check if the
		# callsign "makes sense".
		# 23.Apr.06: Added another "/X" to the regex, for calls like RV0AL/0/P
		# as used by RDA-DXpeditions....

		if (preg_match_all('/^((\d|[A-Z])+\/)?((\d|[A-Z]){3,})(\/(\d|[A-Z])+)?(\/(\d|[A-Z])+)?$/', $testcall, $matches)) {

			# Now $1 holds A (incl /), $3 holds the callsign B and $5 has C
			# We save them to $a, $b and $c respectively to ensure they won't get
			# lost in further Regex evaluations.
			$a = $matches[1][0];
			$b = $matches[3][0];
			$c = $matches[5][0];

			if ($a) {
				$a = substr($a, 0, -1); # Remove the / at the end
			}
			if ($c) {
				$c = substr($c, 1); # Remove the / at the beginning
			};

			# In some cases when there is no part A but B and C, and C is longer than 2
			# letters, it happens that $a and $b get the values that $b and $c should
			# have. This often happens with liddish callsign-additions like /QRP and
			# /LGT, but also with calls like DJ1YFK/KP5. ~/.yfklog has a line called
			# "lidadditions", which has QRP and LGT as defaults. This sorts out half of
			# the problem, but not calls like DJ1YFK/KH5. This is tested in a second
			# try: $a looks like a call (.\d[A-Z]) and $b doesn't (.\d), they are
			# swapped. This still does not properly handle calls like DJ1YFK/KH7K where
			# only the OP's experience says that it's DJ1YFK on KH7K.
			if (!$c && $a && $b) {                          # $a and $b exist, no $c
				if (preg_match($lidadditions, $b)) {        # check if $b is a lid-addition
					$b = $a;
					$a = null;                              # $a goes to $b, delete lid-add
				} elseif ((preg_match('/\d[A-Z]+$/', $a)) && (preg_match('/\d$/', $b))) {   # check for call in $a
					$temp = $b;
					$b = $a;
					$a = $temp;
				}
			}

			# *** Added later ***  The check didn't make sure that the callsign
			# contains a letter. there are letter-only callsigns like RAEM, but not
			# figure-only calls.

			if (preg_match('/^[0-9]+$/', $b)) {            # Callsign only consists of numbers. Bad!
				return null;            # exit, undef
			}

			# Depending on these values we have to determine the prefix.
			# Following cases are possible:
			#
			# 1.    $a and $c undef --> only callsign, subcases
			# 1.1   $b contains a number -> everything from start to number
			# 1.2   $b contains no number -> first two letters plus 0
			# 2.    $a undef, subcases:
			# 2.1   $c is only a number -> $a with changed number
			# 2.2   $c is /P,/M,/MM,/AM -> 1.
			# 2.3   $c is something else and will be interpreted as a Prefix
			# 3.    $a is defined, will be taken as PFX, regardless of $c

			if (($a == null) && ($c == null)) {                     # Case 1
				if (preg_match('/\d/', $b)) {                       # Case 1.1, contains number
					preg_match('/(.+\d)[A-Z]*/', $b, $matches);     # Prefix is all but the last
					$prefix = $matches[1];                          # Letters
				} else {                                            # Case 1.2, no number
					$prefix = substr($b, 0, 2) . "0";               # first two + 0
				}
			} elseif (($a == null) && (isset($c))) {                # Case 2, CALL/X
				if (preg_match('/^(\d)/', $c)) {                    # Case 2.1, number
					preg_match('/(.+\d)[A-Z]*/', $b, $matches);     # regular Prefix in $1
					# Here we need to find out how many digits there are in the
					# prefix, because for example A45XR/0 is A40. If there are 2
					# numbers, the first is not deleted. If course in exotic cases
					# like N66A/7 -> N7 this brings the wrong result of N67, but I
					# think that's rather irrelevant cos such calls rarely appear
					# and if they do, it's very unlikely for them to have a number
					# attached.   You can still edit it by hand anyway..
					if (preg_match('/^([A-Z]\d)\d$/', $matches[1])) {        # e.g. A45   $c = 0
						$prefix = $matches[1] . $c;  # ->   A40
					} else {                         # Otherwise cut all numbers
						preg_match('/(.*[A-Z])\d+/', $matches[1], $match); # Prefix w/o number in $1
						$prefix = $match[1] . $c; # Add attached number
					}
				} elseif (preg_match($csadditions, $c)) {
					preg_match('/(.+\d)[A-Z]*/', $b, $matches);     # Known attachment -> like Case 1.1
					$prefix = $matches[1];
				} elseif (preg_match($noneadditions, $c)) {
					return '';
				} elseif (preg_match('/^\d\d+$/', $c)) {            # more than 2 numbers -> ignore
					preg_match('/(.+\d)[A-Z]* /', $b, $matches);    # see above
					$prefix = $matches[1][0];
				} else {                                            # Must be a Prefix!
					if (preg_match('/\d$/', $c)) {                  # ends in number -> good prefix
						$prefix = $c;
					} else {                                        # Add Zero at the end
						$prefix = $c . "0";
					}
				}
			} elseif (($a) && (preg_match($noneadditions, $c))) {                # Case 2.1, X/CALL/X ie TF/DL2NWK/MM - DXCC none
				return '';
			} elseif ($a) {
				# $a contains the prefix we want
				if (preg_match('/\d$/', $a)) {                      # ends in number -> good prefix
					$prefix = $a;
				} else {                                            # add zero if no number
					$prefix = $a . "0";
				}
			}
			# In very rare cases (right now I can only think of KH5K and KH7K and FRxG/T
			# etc), the prefix is wrong, for example KH5K/DJ1YFK would be KH5K0. In this
			# case, the superfluous part will be cropped. Since this, however, changes the
			# DXCC of the prefix, this will NOT happen when invoked from with an
			# extra parameter $_[1]; this will happen when invoking it from &dxcc.

			if (preg_match('/(\w+\d)[A-Z]+\d/', $prefix, $matches) && $i == null) {
				$prefix = $matches[1][0];
			}
			return $prefix;
		} else {
			return '';
		}
	}

	public function get_entity($dxcc) {
		$sql = "select name, cqz, lat, 'long' from dxcc_entities where adif = ?";
		$query = $this->db->query($sql, $dxcc);

		if ($query->result() > 0) {
			$row = $query->row_array();
			return $row;
		}
		return '';
	}


	public function check_missing_dxcc_id($all) {
		ini_set('memory_limit', '-1');	// This consumes a much of Memory!
		$this->db->trans_start();	// Transaction has to be started here, because otherwise we're trying to update rows which are locked by the select
		$this->db->select("COL_PRIMARY_KEY, COL_CALL, COL_TIME_ON, COL_TIME_OFF"); // get all records with no COL_DXCC

		if (!$all) { // check which to update - records with no dxcc or all records
			$this->db->where("COL_DXCC is NULL");
		}

		$r = $this->db->get($this->config->item('table_name'));

		$count = 0;
		if ($r->num_rows() > 0) { //query dxcc_prefixes
			$sql = "update " . $this->config->item('table_name') . " set COL_COUNTRY = ?, COL_DXCC=? where COL_PRIMARY_KEY=?";
			$q = $this->db->conn_id->prepare($sql);	// PREPARE this statement. For DB this means: No parsing overhead, parse once use many (see execute query below)
			foreach ($r->result_array() as $row) {
				$qso_date = $row['COL_TIME_OFF'] == '' ? $row['COL_TIME_ON'] : $row['COL_TIME_OFF'];
				$qso_date = date("Y-m-d", strtotime($qso_date));
				$d = $this->check_dxcc_table($row['COL_CALL'], $qso_date);
				if ($d[0] != 'Not Found') {
					$q->execute(array(addslashes(ucwords(strtolower($d[1]), "- (/")), $d[0], $row['COL_PRIMARY_KEY']));
					$count++;
				}
			}
		}
		$this->db->trans_complete();
		print("$count updated\n");
	}

	public function check_missing_continent() {
		// get all records with no COL_CONT
		$this->db->trans_start();
		$sql = "UPDATE " . $this->config->item('table_name') . " JOIN dxcc_entities ON " . $this->config->item('table_name') . ".col_dxcc = dxcc_entities.adif SET col_cont = dxcc_entities.cont WHERE COALESCE(" . $this->config->item('table_name') . ".col_cont, '') = ''";

		$query = $this->db->query($sql);
		print($this->db->affected_rows() . " updated\n");
		$this->db->trans_complete();
	}

	public function check_missing_grid_id($all) {
		// get all records with no COL_GRIDSQUARE
		$this->db->select("COL_PRIMARY_KEY, COL_CALL, COL_TIME_ON, COL_TIME_OFF");

		$this->db->where("(COL_GRIDSQUARE is NULL or COL_GRIDSQUARE = '') AND (COL_VUCC_GRIDS is NULL or COL_VUCC_GRIDS = '')");

		$r = $this->db->get($this->config->item('table_name'));

		$count = 0;
		$this->db->trans_start();
		if ($r->num_rows() > 0) {
			foreach ($r->result_array() as $row) {
				$callsign = $row['COL_CALL'];
				if (!$this->load->is_loaded('callbook')) {
					$this->load->library('callbook');
				}

				$callbook = $this->callbook->getCallbookData($callsign);

				if (isset($callbook)) {
					if (isset($callbook['error'])) {
						printf("Error: " . $callbook['error'] . "<br />");
					} else {
						$return['callsign_qra'] = $callbook['gridsquare'];
						if ($return['callsign_qra'] != '') {
							$sql = sprintf(
								"update %s set COL_GRIDSQUARE = '%s' where COL_PRIMARY_KEY=%d",
								$this->config->item('table_name'),
								$return['callsign_qra'],
								$row['COL_PRIMARY_KEY']
							);
							$this->db->query($sql);
							printf("Updating %s to %s\n<br/>", $row['COL_PRIMARY_KEY'], $return['callsign_qra']);
							$count++;
						}
					}
				}
			}
		}
		$this->db->trans_complete();

		print("$count updated\n");
	}

	public function update_distances($all) {
		ini_set('memory_limit', '-1');	// This consumes a much of Memory!
		$this->db->trans_start();	// Transaction has to be started here, because otherwise we're trying to update rows which are locked by the select
		$this->db->select("COL_PRIMARY_KEY, COL_GRIDSQUARE, COL_ANT_PATH, station_gridsquare");
		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		if (!$all) {
			$this->db->where("((COL_DISTANCE is NULL) or (COL_DISTANCE = 0))");
		}
		$this->db->where("COL_GRIDSQUARE is NOT NULL");
		$this->db->where("COL_GRIDSQUARE != ''");
		$this->db->where("COL_GRIDSQUARE != station_gridsquare");
		$query = $this->db->get($this->config->item('table_name'));

		$count = 0;
		if ($query->num_rows() > 0) {
			print("Affected QSOs: " . $this->db->affected_rows() . " <br />");
			if (!$this->load->is_loaded('Qra')) {
				$this->load->library('Qra');
			}
			foreach ($query->result() as $row) {
				$ant_path = $row->COL_ANT_PATH ?? null;
				$distance = $this->qra->distance($row->station_gridsquare, $row->COL_GRIDSQUARE, 'K', $ant_path);
				$data = array(
					'COL_DISTANCE' => $distance,
				);

				$this->db->where(array('COL_PRIMARY_KEY' => $row->COL_PRIMARY_KEY));
				$this->db->update($this->config->item('table_name'), $data);
				$count++;
			}
			print("QSOs updated: " . $count);
		} else {
			print "No QSOs affected.";
		}
		$this->db->trans_complete();
	}

	public function check_for_station_id() {
		$this->db->select('COL_PRIMARY_KEY, COL_TIME_ON, COL_CALL, COL_MODE, COL_BAND, COL_STATION_CALLSIGN');
		$this->db->where('station_id =', NULL);
		$this->db->or_where("station_id", 0);
		$query = $this->db->get($this->config->item('table_name'));
		if ($query->num_rows() >= 1) {
			return $query->result();
		} else {
			return 0;
		}
	}

	function get_plaincall($callsign) {
		$split_callsign = explode('/', $callsign);
		if (count($split_callsign) == 1) {				// case F0ABC --> return cel 0 //
			$lookupcall = $split_callsign[0];
		} else if (count($split_callsign) == 3) {			// case EA/F0ABC/P --> return cel 1 //
			$lookupcall = $split_callsign[1];
		} else {										// case F0ABC/P --> return cel 0 OR  case EA/FOABC --> retunr 1  (normaly not exist) //
			if (in_array(strtoupper($split_callsign[1]), array('P', 'M', 'MM', 'QRP', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'))) {
				$lookupcall = $split_callsign[0];
			} else if (strlen($split_callsign[1]) > 3) {	// Last Element longer than 3 chars? Take that as call
				$lookupcall = $split_callsign[1];
			} else {									// Last Element up to 3 Chars? Take first element as Call
				$lookupcall = $split_callsign[0];
			}
		}
		return $lookupcall;
	}

	public function loadCallBook($callsign, $use_fullname = false) {
		$callbook = null;
		try {
			if (!$this->load->is_loaded('callbook')) {
				$this->load->library('callbook');
			}

			$callbook = $this->callbook->getCallbookData($callsign);

		} finally {
			return $callbook;
		}
	}

	public function update_station_ids($station_id, $station_callsign, $qsoids) {

		if (! empty($qsoids)) {
			$data = array(
				'station_id' => $station_id,
			);

			$this->db->where_in('COL_PRIMARY_KEY', $qsoids);
			$this->db->group_start();
			$this->db->where(array('station_id' => NULL));
			$this->db->or_where(array('station_id' => 0));	// 0 is also unassigned, compare mig_185
			$this->db->group_end();
			if ($station_callsign == '') {
				$this->db->where(array('col_station_callsign' => NULL));
			} else {
				$this->db->where('col_station_callsign', trim($station_callsign));
			}
			$this->db->update($this->config->item('table_name'), $data);
			if ($this->db->affected_rows() > 0) {
				return TRUE;
			} else {
				return FALSE;
			}
		} else {
			return FALSE;
		}
	}

	public function parse_frequency($frequency) {
		if (is_int($frequency))
			return $frequency;

		if (is_string($frequency)) {
			$frequency = strtoupper($frequency);
			$frequency = str_replace(" ", "", $frequency);
			$frequency = str_replace("HZ", "", $frequency);
			$frequency = str_replace(["K", "M", "G", "T"], ["E3", "E6", "E9", "E12"], $frequency);

			// this double conversion will take a string like "3700e3" and convert it into 3700000
			return (int)(float) $frequency;
		}

		return 0;
	}

	/*
     * This function returns the the whole list of dxcc_entities used in various places
     */
	function fetchDxcc() {
		$sql = "select adif, prefix, name, date(end) Enddate, date(start) Startdate from dxcc_entities";

		$sql .= ' order by prefix';
		$query = $this->db->query($sql);

		return $query->result();
	}

	/*
     * This function returns the whole list of iotas used in various places
     */
	function fetchIota() {
		$sql = "select tag, name from iota";

		$sql .= ' order by tag';
		$query = $this->db->query($sql);

		return $query->result();
	}

	function get_lotw_qsos_to_upload($station_id, $start_date, $end_date) {

		$this->db->select('COL_PRIMARY_KEY,COL_CALL, COL_BAND, COL_BAND_RX, COL_TIME_ON, COL_RST_RCVD, COL_RST_SENT, COL_MODE, COL_SUBMODE, COL_FREQ, COL_FREQ_RX, COL_GRIDSQUARE, COL_SAT_NAME, COL_PROP_MODE, COL_LOTW_QSL_SENT, station_id');

		$this->db->where("station_id", $station_id);
		$this->db->group_start();
		$this->db->where('COL_LOTW_QSL_SENT', NULL);
		$this->db->or_where_not_in('COL_LOTW_QSL_SENT', array("Y", "I"));
		$this->db->group_end();
		// Only add check for unsupported modes if not empty. Otherwise SQL will fail
		if (!empty($this->config->item('lotw_unsupported_prop_modes'))) {
			$this->db->where_not_in('COL_PROP_MODE', $this->config->item('lotw_unsupported_prop_modes'));
		}
		$this->db->where('COL_TIME_ON >=', $start_date);
		$this->db->where('COL_TIME_ON <=', $end_date);
		$this->db->order_by("COL_TIME_ON", "desc");

		$query = $this->db->get($this->config->item('table_name'));

		return $query;
	}

	function mark_lotw_sent($qso_id) {

		$data = array(
			'COL_LOTW_QSLSDATE' => date("Y-m-d H:i:s"),
			'COL_LOTW_QSL_SENT' => 'Y',
		);


		$this->db->where('COL_PRIMARY_KEY', $qso_id);

		$this->db->update($this->config->item('table_name'), $data);

		return "Updated";
	}

	function lotw_invalid_sats() {
		$sats = array();
		$this->db->select('COALESCE(NULLIF(name, \'\'), NULLIF(displayname, \'\')) AS satname');
		$this->db->where('lotw', 'N');
		$this->db->having('satname !=', null);
		$query = $this->db->get('satellite');
		if ($query->num_rows() > 0){
			foreach ($query->result() as $row) {
				array_push($sats, $row->satname);
			}
		}
		return $sats;
	}

	function mark_lotw_ignore($station_id) {
		$invalid_sats = $this->lotw_invalid_sats();
		$data = array(
			'COL_LOTW_QSLSDATE' => null,
			'COL_LOTW_QSL_SENT' => 'I',
			'COL_LOTW_QSLRDATE' => null,
			'COL_LOTW_QSL_RCVD' => 'I',
		);
		$this->db->where("station_id", $station_id);
		$this->db->group_start();
			$this->db->where('COL_LOTW_QSL_SENT !=', 'I');
			$this->db->or_where('COL_LOTW_QSL_SENT', null);
		$this->db->group_end();
		$this->db->group_start();
			$this->db->group_start();
				$this->db->where('COL_PROP_MODE', 'SAT');
				$this->db->group_start();
					$this->db->where('COL_SAT_NAME', '');
					$this->db->or_where('COL_SAT_NAME', null);
				$this->db->group_end();
			$this->db->group_end();
			// Only add check for unsupported SATs if not empty. Otherwise SQL will fail
			if (!empty($invalid_sats)) {
				$this->db->or_group_start();
					$this->db->where('COL_PROP_MODE', 'SAT');
					$this->db->where_in('COL_SAT_NAME', $invalid_sats);
				$this->db->group_end();
			}
			// Only add check for unsupported modes if not empty. Otherwise SQL will fail
			if (!empty($this->config->item('lotw_unsupported_prop_modes'))) {
				$this->db->or_group_start();
					$this->db->where_in('COL_PROP_MODE', $this->config->item('lotw_unsupported_prop_modes'));
				$this->db->group_end();
			}
		$this->db->group_end();
		$this->db->update($this->config->item('table_name'), $data);
	}

	function county_qso_details($state, $county) {
		$this->load->model('logbooks_model');
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));

		$this->db->join('station_profile', 'station_profile.station_id = ' . $this->config->item('table_name') . '.station_id');
		$this->db->join('lotw_users', 'lotw_users.callsign = ' . $this->config->item('table_name') . '.col_call', 'left outer');
		$this->db->where_in($this->config->item('table_name') . '.station_id', $logbooks_locations_array);
		$this->db->where('COL_STATE', $state);
		$this->db->where('COL_CNTY', $county);
		$this->db->where('COL_PROP_MODE !=', 'SAT');

		return $this->db->get($this->config->item('table_name'));
	}

	public function check_qso_is_accessible($id) {
		// check if qso belongs to user
		$this->db->select($this->config->item('table_name') . '.COL_PRIMARY_KEY');
		$this->db->join('station_profile', $this->config->item('table_name') . '.station_id = station_profile.station_id');
		$this->db->where('station_profile.user_id', $this->session->userdata('user_id'));
		$this->db->where($this->config->item('table_name') . '.COL_PRIMARY_KEY', $id);
		$query = $this->db->get($this->config->item('table_name'));
		if ($query->num_rows() == 1) {
			return true;
		}
		return false;
	}

	// [JSON PLOT] return array for plot qso for map //
	public function get_plot_array_for_map($qsos_result, $isVisitor = false) {
		if (!$this->load->is_loaded('Qra')) {
			$this->load->library('Qra');
		}

		$json["markers"] = array();

		foreach ($qsos_result as $row) {
			$plot = array('lat' => 0, 'lng' => 0, 'html' => '', 'label' => '', 'confirmed' => 'N');

			$plot['label'] = str_replace('0', '&Oslash;', $row->COL_CALL);

			$plot['html'] = "";
			if ($row->COL_NAME != null) {
				$plot['html'] .= "Name: " . $row->COL_NAME . "<br />";
			}
			$date_cat = "Date";

			// Get Date format
			if ($this->session->userdata('user_date_format')) {
				// If Logged in and session exists
				$user_date_format = $this->session->userdata('user_date_format');
			} else {
				// Get Default date format from /config/wavelog.php
				$user_date_format = $this->config->item('qso_date_format');
			}

			$qso_time_on = new DateTime($row->COL_TIME_ON);

			if ($this->uri->segment(1) == 'visitor') {
				$visitor_date_format = $this->config->item('qso_date_format');
				if ($this->config->item('show_time')) {
					$visitor_date_format .= ' H:i';
					$date_cat .= "/Time";
				}
				$qso_time_on = $qso_time_on->format($visitor_date_format);
			} else {
				$qso_time_on = $qso_time_on->format($user_date_format . ' H:i');
				$date_cat .= "/Time";
			}

			$plot['html'] .= $date_cat . ": " . $qso_time_on . "<br />";
			$plot['html'] .= ($row->COL_SAT_NAME != null) ? ("SAT: " . $row->COL_SAT_NAME . "<br />") : ("Band: " . $row->COL_BAND . "<br />");
			$plot['html'] .= "Mode: " . ($row->COL_SUBMODE == null ? $row->COL_MODE : $row->COL_SUBMODE) . "<br />";

			// check if qso is confirmed //
			if (!$isVisitor) {
				$plot['confirmed'] = ($this->qso_is_confirmed($row) == true) ? "Y" : "N";
			}
			// check lat / lng (depend info source) //
			if ($row->COL_GRIDSQUARE != null) {
				$stn_loc = $this->qra->qra2latlong($row->COL_GRIDSQUARE);
				if (($this->session->userdata('user_locator') ?? '') != '') {
					$xbearing = $this->qra->get_bearing($this->session->userdata('user_locator'),$row->COL_GRIDSQUARE);
				}
			} elseif ($row->COL_VUCC_GRIDS != null) {
				$coords = array();
				$grids = explode(",", $row->COL_VUCC_GRIDS);
				if (count($grids) == 2) {
					$grid1 = $this->qra->qra2latlong(trim($grids[0]));
					$grid2 = $this->qra->qra2latlong(trim($grids[1]));

					$coords[] = array('lat' => $grid1[0], 'lng' => $grid1[1]);
					$coords[] = array('lat' => $grid2[0], 'lng' => $grid2[1]);

					$stn_loc = $this->qra->get_midpoint($coords);
				}
				if (count($grids) == 4) {
					$grid1 = $this->qra->qra2latlong(trim($grids[0]));
					$grid2 = $this->qra->qra2latlong(trim($grids[1]));
					$grid3 = $this->qra->qra2latlong(trim($grids[2]));
					$grid4 = $this->qra->qra2latlong(trim($grids[3]));

					$coords[] = array('lat' => $grid1[0], 'lng' => $grid1[1]);
					$coords[] = array('lat' => $grid2[0], 'lng' => $grid2[1]);
					$coords[] = array('lat' => $grid3[0], 'lng' => $grid3[1]);
					$coords[] = array('lat' => $grid4[0], 'lng' => $grid4[1]);

					$stn_loc = $this->qra->get_midpoint($coords);
				}
				if (($this->session->userdata('user_locator') ?? '') != '') {
					$xbearing = $this->qra->get_bearing($this->session->userdata('user_locator'),$row->COL_VUCC_GRIDS);
				}
			} else {
				if (isset($row->lat) && isset($row->long)) {
					$stn_loc = array($row->lat, $row->long);
				}
			}
			if (isset($xbearing)) {
				$plot['html'].=__("Bearing").': '.$xbearing."&deg;<br/>";
			}
			if (isset($stn_loc)) {
				list($plot['lat'], $plot['lng']) = $stn_loc;
			}
			// add plot //
			$json["markers"][] = $plot;
		}
		return $json;
	}

	public function get_states_by_dxcc($dxcc) {
		$this->db->where('adif', $dxcc);
		$this->db->order_by('subdivision', 'ASC');
		return $this->db->get('primary_subdivisions');
	}

	// return if qso is confirmed (depend user option "qsl method") //
	public function qso_is_confirmed($qso) {
		$confirmed = false;
		$user_default_confirmation = $this->session->userdata('user_default_confirmation');
		if (isset($user_default_confirmation)) {
			$qso = (array) $qso;
			if (strpos($user_default_confirmation, 'Q') !== false) {        // QSL
				if ($qso['COL_QSL_RCVD'] == 'Y') {
					$confirmed = true;
				}
			}
			if (strpos($user_default_confirmation, 'L') !== false) { // LoTW
				if ($qso['COL_LOTW_QSL_RCVD'] == 'Y') {
					$confirmed = true;
				}
			}
			if (strpos($user_default_confirmation, 'E') !== false) { // eQsl
				if ($qso['COL_EQSL_QSL_RCVD'] == 'Y') {
					$confirmed = true;
				}
			}
			if (strpos($user_default_confirmation, 'Z') !== false) { // QRZ
				if ($qso['COL_QRZCOM_QSO_DOWNLOAD_STATUS'] == 'Y') {
					$confirmed = true;
				}
			}
		}
		return $confirmed;
	}

	public function get_user_id_from_qso($qso_id) {

		$clean_qsoid = $this->security->xss_clean($qso_id);

		$sql =    'SELECT station_profile.user_id
                FROM ' . $this->config->item('table_name') . '
                INNER JOIN station_profile ON (' . $this->config->item('table_name') . '.station_id = station_profile.station_id)
                WHERE ' . $this->config->item('table_name') . '.COL_PRIMARY_KEY = ?';

		$result = $this->db->query($sql, $clean_qsoid);
		$row = $result->row();

		return $row->user_id;
	}
	function getLongRegion($region = '') {
		switch($region) {
		case 'AI':
			return 'African Italy';
			break;
		case 'BI':
			return 'Bear Island';
			break;
		case 'ET':
			return 'European Turkey';
			break;
		case 'IV':
			return 'ITU Vienna';
			break;
		case 'KO':
			return 'Kosovo';
			break;
		case 'SY':
			return 'Sicily';
			break;
		case 'SI':
			return 'Shetland Islands';
			break;
		default:
			return $region;
			break;
		}
	}


	function getContinent($dxcc) {
		$sql = "SELECT cont FROM dxcc_entities WHERE adif = ?";
		$query = $this->db->query($sql, $dxcc);

		if ($query->num_rows() == 1) {
			return $query->row()->cont;
		}
		return '';
	}

	function getContestQSO(array $station_ids, string $station_callsign, string $contest_id, string $callsign, string $band, string $mode, string $date, string $time)
	{

		//load QSO table
		$this->db->select('*');
		$this->db->from($this->config->item('table_name'));

		//load only for given station_ids
		$this->db->where_in('station_id', $station_ids);

		//load only for the station_callsign given
		$this->db->where('COL_STATION_CALLSIGN', trim(xss_clean($station_callsign)));

		//load only for the given contest id
		$this->db->where('COL_CONTEST_ID', xss_clean($contest_id));

		//load only for this qso partners callsign
		$this->db->where('COL_CALL', xss_clean($callsign));

		//load only for given band (no cleaning necessary because provided by wavelog itself)
		$this->db->where('COL_BAND', $band);

		//load only for specific mode if the mode is determinate. If not, omit it. In most cases, that should be fine. Also provided by wavelog itself, so no cleaning.
		if($mode != '') {
			$this->db->where('COL_MODE', $mode);
		}

		//prepare datetime from format '2099-12-31 13:47' to be usable in a performant query
		$datetime_raw = $date . ' ' . substr($time, 0, 2) . ':' . substr($time, 2, 2);
		$datetime = new DateTime($datetime_raw,new DateTimeZone('UTC'));
		$from_datetime = $datetime->format('Y-m-d H:i:s');
		$datetime->add(new DateInterval('PT1M'));
		$to_datetime = $datetime->format('Y-m-d H:i:s');

		//load only QSOs during this minute
		$this->db->where('COL_TIME_ON >=', $from_datetime);
		$this->db->where('COL_TIME_ON <', $to_datetime);

		//return whatever is left
		return $this->db->get();
	}

	function set_contest_fields($qso_primary_key, ?int $stx, ?string $stxstring, ?int $srx, ?string $srxstring) {

		//assemble data fields from input
		$data = $data = array(
			'COL_STX' => $stx,
			'COL_STX_STRING' => $stxstring == null ? null : substr($stxstring, 0, 32),
			'COL_SRX' => $srx,
			'COL_SRX_STRING' => $srxstring == null ? null : substr($srxstring, 0, 32)
		);

		//narrow db operation down to 1 QSO
		$this->db->where(array('COL_PRIMARY_KEY' => $qso_primary_key));

		//update data and return
		$this->db->update($this->config->item('table_name'), $data);
		return;
	}

	function get_sat_qso_count() {
		$sats = array();
		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
		$location_list = "'" . implode("','", $logbooks_locations_array) . "'";
		$sql = "SELECT COL_SAT_NAME, COUNT(COL_CALL) AS qsocount FROM ".$this->config->item('table_name')." WHERE station_id IN (".$location_list.") AND COL_PROP_MODE = 'SAT' AND COL_SAT_NAME != '' GROUP BY COL_SAT_NAME ORDER BY COL_SAT_NAME ASC;";
		foreach ($this->db->query($sql)->result() as $row) {
			$sats[$row->COL_SAT_NAME] = $row->qsocount;
		}
		return $sats;
	}
}

function validateADIFDate($date, $format = 'Ymd') {
	$d = DateTime::createFromFormat($format, $date);
	return $d && $d->format($format) == $date;
}
