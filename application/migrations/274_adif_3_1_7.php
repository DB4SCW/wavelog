<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Migration_adif_3_1_7 extends CI_Migration {

	public function up() {

		// Adds new modes from ADIF 3.1.7 specification
		$modes = array(
			array('mode' => "MFSK", 'submode' => "FT2", 'qrgmode' => "DATA", 'active' => 1),
			array('mode' => "DYNAMIC", 'submode' => "FREEDATA", 'qrgmode' => "DATA", 'active' => 1),
			array('mode' => "OFDM", 'submode' => "RIBBIT_PIX", 'qrgmode' => "DATA", 'active' => 1),
			array('mode' => "OFDM", 'submode' => "RIBBIT_SMS", 'qrgmode' => "DATA", 'active' => 1),
		);

		foreach ($modes as $mode) {
			$exists = $this->db->where('submode', $mode['submode'])
							->get('adif_modes')
							->num_rows() > 0;

			if (!$exists) {
				$this->db->insert('adif_modes', $mode);
			}
		}

	}

	public function down() {
		// remove the modes that were added in this migration
		$mode_names = array(
			'FT2',
			'FREEDATA',
			'RIBBIT_PIX',
			'RIBBIT_SMS'
		);

		$this->db->where_in('submode', $mode_names);
		$this->db->delete('adif_modes');

	}
}
