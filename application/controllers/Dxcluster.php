<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use Wavelog\Dxcc\Dxcc;

require_once APPPATH . '../src/Dxcc/Dxcc.php';

class Dxcluster extends CI_Controller {

	function __construct()
	{
		parent::__construct();
		$this->load->is_loaded('user_model') ?: $this->load->model('user_model');
		if(!$this->user_model->authorize(2)) { $this->session->set_flashdata('error', __("You're not allowed to do that!")); redirect('dashboard'); }
		$this->load->is_loaded('dxcluster_model') ?: $this->load->model('dxcluster_model');
	}


	function spots($band, $age = '', $de = '', $mode = 'All') {
		// Sanitize inputs
		$band = $this->security->xss_clean($band);
		$mode = $this->security->xss_clean($mode);

		if ($age == '') {
			$age = $this->optionslib->get_option('dxcluster_maxage') ?? 60;
		} else {
			$age = (int)$age;
		}

		if ($de == '') {
			$de = $this->optionslib->get_option('dxcluster_decont') ?? 'EU';
		} else {
			$de = $this->security->xss_clean($de);
		}
		$calls_found = $this->dxcluster_model->dxc_spotlist($band, $age, $de, $mode);

		header('Content-Type: application/json');
		if ($calls_found && !empty($calls_found)) {
			http_response_code(200);
			echo json_encode($calls_found, JSON_PRETTY_PRINT);
		} else {
			$this->_return_not_found();
		}
	}

	function qrg_lookup($qrg) {
		$call_found = $this->dxcluster_model->dxc_qrg_lookup($this->security->xss_clean($qrg));
		header('Content-Type: application/json');
		if ($call_found) {
			http_response_code(200);
			echo json_encode($call_found, JSON_PRETTY_PRINT);
		} else {
			$this->_return_not_found();
		}
	}

	function call($call) {
		$date = date('Y-m-d', time());
		$dxccobj = new Dxcc($date);

		$dxcc = $dxccobj->dxcc_lookup($call, $date);

		header('Content-Type: application/json');
		if ($dxcc) {
			http_response_code(200);
			echo json_encode($dxcc, JSON_PRETTY_PRINT);
		} else {
			$this->_return_not_found();
		}
	}

	private function _return_not_found() {
		header('Content-Type: application/json');
		http_response_code(404);
		echo json_encode(['error' => 'not found'], JSON_PRETTY_PRINT);
	}
}
