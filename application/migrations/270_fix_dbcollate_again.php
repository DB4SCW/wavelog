<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_fix_dbcollate_again extends CI_Migration
{
	public function up()
	{
		$tables = array(
			'club_permissions',
			'dxcc_master',
			'themes',
			'tle',
			'user_options',
			'vuccgrids'
		);
		foreach ($tables as $table) {
			$this->db->query('ALTER TABLE ' . $table . ' CONVERT TO CHARACTER SET ' . $this->db->char_set . ' COLLATE ' . $this->db->dbcollat); // fix existing tables that haven't already been fixed
		}
		$this->db->query('ALTER DATABASE `' . $this->db->database . '` CHARACTER SET ' . $this->db->char_set . ' COLLATE ' . $this->db->dbcollat); // fix the database default
	}

	public function down()
	{
		// Not Possible
	}
}
?>