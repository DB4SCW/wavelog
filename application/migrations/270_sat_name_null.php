<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_sat_name_null extends CI_Migration {

	public function up() {
			$this->dbtry("UPDATE ".$this->config->item('table_name')." SET COL_SAT_NAME = null WHERE COL_SAT_NAME = '';");
	}

	public function down(){
			$this->dbtry("UPDATE ".$this->config->item('table_name')." SET COL_SAT_NAME = '' WHERE COL_SAT_NAME IS null;");
	}

	function dbtry($what) {
		try {
			$this->db->query($what);
		} catch (Exception $e) {
			log_message("error", "Something gone wrong while altering the QSO table: ".$e." // Executing: ".$this->db->last_query());
		}
	}
}
