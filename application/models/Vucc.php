<?php

class VUCC extends CI_Model
{

	private $logbooks_locations_array;
	public function __construct()
	{
		$this->load->model('logbooks_model');
		$this->logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($this->session->userdata('active_station_logbook'));
	}

    /*
     *  Fetches worked and confirmed gridsquare on each band and total
     */
    function get_vucc_array($data) {
        $vuccArray = $this->fetchVucc($data);

        if (isset($vuccArray)) {
            return $vuccArray;
        } else {
            return 0;
        }
    }

    /*
     * Builds the array to display worked/confirmed vucc on award page
     */
    function fetchVucc($data) {
        $totalGridConfirmed = array();
        $totalGridWorked = array();

        foreach($data['worked_bands'] as $band) {

            // Getting all the worked grids
            $col_gridsquare_worked = $this->get_vucc_summary($band, 'none');

            $workedGridArray = array();
            foreach ($col_gridsquare_worked as $workedgrid) {
                array_push($workedGridArray, $workedgrid['gridsquare']);
                if(!in_array($workedgrid['gridsquare'], $totalGridWorked)){
                    array_push($totalGridWorked, $workedgrid['gridsquare']);
                }
            }

            $col_vucc_grids_worked = $this->get_vucc_summary_col_vucc($band, 'none');

            foreach ($col_vucc_grids_worked as $gridSplit) {
                $grids = explode(",", $gridSplit['col_vucc_grids']);
                foreach($grids as $key) {
                    $grid_four = strtoupper(substr(trim($key),0,4));

                    if(!in_array($grid_four, $workedGridArray)){
                        array_push($workedGridArray, $grid_four);
                    }

                    if(!in_array($grid_four, $totalGridWorked)){
                        array_push($totalGridWorked, $grid_four);
                    }
                }
            }

            // Getting all the confirmed grids
            $col_gridsquare_confirmed = $this->get_vucc_summary($band, 'both');

            $confirmedGridArray = array();
            foreach ($col_gridsquare_confirmed as $confirmedgrid) {
                array_push($confirmedGridArray, $confirmedgrid['gridsquare']);
                if(!in_array($confirmedgrid['gridsquare'], $totalGridConfirmed)){
                    array_push($totalGridConfirmed, $confirmedgrid['gridsquare']);
                }
            }

            $col_vucc_grids_confirmed = $this->get_vucc_summary_col_vucc($band, 'both');

            foreach ($col_vucc_grids_confirmed as $gridSplit) {
                $grids = explode(",", $gridSplit['col_vucc_grids']);
                foreach($grids as $key) {
                    $grid_four = strtoupper(substr(trim($key),0,4));

                    if(!in_array($grid_four, $confirmedGridArray)){
                        array_push($confirmedGridArray, $grid_four);
                    }

                    if(!in_array($grid_four, $totalGridConfirmed)){
                        array_push($totalGridConfirmed, $grid_four);
                    }
                }
            }

            $vuccArray[$band]['worked'] = count($workedGridArray);
            $vuccArray[$band]['confirmed'] = count($confirmedGridArray);
        }

        $vuccArray['All']['worked'] = count($totalGridWorked);
        $vuccArray['All']['confirmed'] = count($totalGridConfirmed);

        if ($vuccArray['All']['worked'] == 0) {
            return null;
        }

        return $vuccArray;
    }

    /*
     *  Gets the grid from col_vucc_grids
     * $band = the band chosen
     * $confirmationMethod - qsl, lotw or both, use anything else to skip confirmed
     */
    function get_vucc_summary_col_vucc($band, $confirmationMethod) {

        if (!$this->logbooks_locations_array) {
            return null;
        }

		$location_list = "'".implode("','",$this->logbooks_locations_array)."'";

        $sql = "select distinct col_vucc_grids
            from " . $this->config->item('table_name') .
            " where station_id in (" . $location_list . ")" .
            " and col_vucc_grids <> '' ";

        if ($confirmationMethod == 'both') {
            $sql .= " and (col_qsl_rcvd='Y' or col_lotw_qsl_rcvd='Y')";
        }
        else if ($confirmationMethod == 'qsl') {
            $sql .= " and col_qsl_rcvd='Y'";
        }
        else if ($confirmationMethod == 'lotw') {
            $sql .= " and col_lotw_qsl_rcvd='Y'";
        }

        if ($band != 'All') {
            if ($band == 'SAT') {
                $sql .= " and col_prop_mode ='" . $band . "'";
            } else {
                $sql .= " and col_prop_mode !='SAT'";
                $sql .= " and col_band ='" . $band . "'";
            }
        }

        $query = $this->db->query($sql);
        return $query->result_array();
    }

    /*
     * Gets the grid from col_gridsquare
     * $band = the band chosen
     * $confirmationMethod - qsl, lotw or both, use anything else to skip confirmed
     */
    function get_vucc_summary($band, $confirmationMethod) {
	    if (!$this->logbooks_locations_array) {
		    return null;
	    }

	    $location_list = "'".implode("','",$this->logbooks_locations_array)."'";

	    $sql = "select distinct upper(substring(log.col_gridsquare, 1, 4)) gridsquare
		    from " . $this->config->item('table_name') . " log".
		    " inner join bands b on (b.band = log.col_band) ".
		    " where log.station_id in (" . $location_list . ")" .
		    " and log.col_gridsquare <> ''";

	    if (($band == 'SAT') || ($band == 'All')) {
		$sql.=" and b.bandgroup in ('vhf','uhf','shf','sat')";
	    }

	    if ($confirmationMethod == 'both') {
		    $sql .= " and (log.col_qsl_rcvd='Y' or log.col_lotw_qsl_rcvd='Y')";
	    } else if ($confirmationMethod == 'qsl') {
		    $sql .= " and log.col_qsl_rcvd='Y'";
	    } else if ($confirmationMethod == 'lotw') {
		    $sql .= " and log.col_lotw_qsl_rcvd='Y'";
	    }

	    if ($band != 'All') {
		    if ($band == 'SAT') {
			    $sql .= " and log.col_prop_mode ='" . $band . "'";
		    } else {
			    $sql .= " and log.col_prop_mode !='SAT'";
			    $sql .= " and log.col_band ='" . $band . "'";
		    }
	    } else {
		    $sql .= " and log.col_prop_mode !='SAT'";
	    }
	    $query = $this->db->query($sql);

	    return $query->result_array();
    }

    /*
     * Makes a list of all gridsquares on chosen band with info about lotw and qsl
     */
    function vucc_details($band, $type) {

        if ($type == 'worked') {
            $workedGridArray = $this->getWorkedGridsList($band, 'none');
            $vuccBand = $this->removeConfirmedGrids($band, $workedGridArray);
        } else if ($type == 'confirmed') {
            $workedGridArray = $this->getWorkedGridsList($band, 'both');
            $vuccBand = $this->markConfirmedGrids($band, $workedGridArray);
        } else {
            $workedGridArray = $this->getWorkedGridsList($band, 'none');
            $vuccBand = $this->markConfirmedGrids($band, $workedGridArray);
        }

        if (!isset($vuccBand)) {
            return 0;
        } else {
            ksort($vuccBand);
            return $vuccBand;
        }
    }

    function removeConfirmedGrids($band, $workedGridArray) {
        $vuccDataQsl = $this->get_vucc_summary($band, 'qsl');

        foreach ($vuccDataQsl as $grid) {
            if (($key = array_search($grid['gridsquare'], $workedGridArray)) !== false) {
                unset($workedGridArray[$key]);
            }
        }

        $vuccDataLotw = $this->get_vucc_summary($band, 'lotw');

        foreach ($vuccDataLotw as $grid) {
            if (($key = array_search($grid['gridsquare'], $workedGridArray)) !== false) {
                unset($workedGridArray[$key]);
            }
        }

        $col_vucc_grids_confirmed_qsl = $this->get_vucc_summary_col_vucc($band, 'lotw');

        foreach ($col_vucc_grids_confirmed_qsl as $gridSplit) {
            $grids = explode(",", $gridSplit['col_vucc_grids']);
            foreach($grids as $key) {
                $grid_four = strtoupper(substr(trim($key),0,4));
                if (($key = array_search($grid_four, $workedGridArray)) !== false) {
                    unset($workedGridArray[$key]);
                }
            }
        }

        $col_vucc_grids_confirmed_lotw = $this->get_vucc_summary_col_vucc($band, 'qsl');

        foreach ($col_vucc_grids_confirmed_lotw as $gridSplit) {
            $grids = explode(",", $gridSplit['col_vucc_grids']);
            foreach($grids as $key) {
                $grid_four = strtoupper(substr(trim($key),0,4));
                if (($key = array_search($grid_four, $workedGridArray)) !== false) {
                    unset($workedGridArray[$key]);
                }
            }
        }
        foreach ($workedGridArray as $grid) {
            $result = $this->grid_detail($grid, $band);
            $callsignlist = '';
            foreach($result->result() as $call) {
                $callsignlist .= $call->COL_CALL . '<br/>';
            }
            $vuccBand[$grid]['call'] = $callsignlist;
        }

        if (isset($vuccBand)) {
            return $vuccBand;
        } else {
            return null;
        }
    }

	function grid_detail($gridsquare, $band) {
		$location_list = "'".implode("','",$this->logbooks_locations_array)."'";
        $sql = "select COL_CALL from " . $this->config->item('table_name') .
                " where station_id in (" . $location_list . ")" .
                " and (col_gridsquare like '" . $gridsquare. "%'
                    or col_vucc_grids like '%" . $gridsquare. "%')";

        if ($band != 'All') {
            if ($band == 'SAT') {
                $sql .= " and col_prop_mode ='" . $band . "'";
            } else {
                $sql .= " and col_prop_mode !='SAT'";
                $sql .= " and col_band ='" . $band . "'";
            }
        }

        return $this->db->query($sql);
    }

    function markConfirmedGrids($band, $workedGridArray) {
        foreach ($workedGridArray as $grid) {
            $vuccBand[$grid]['qsl'] = '';
            $vuccBand[$grid]['lotw'] = '';
        }

        $vuccDataQsl = $this->get_vucc_summary($band, 'qsl');

        foreach ($vuccDataQsl as $grid) {
            $vuccBand[$grid['gridsquare']]['qsl'] = 'Y';
        }

        $vuccDataLotw = $this->get_vucc_summary($band, 'lotw');

        foreach ($vuccDataLotw as $grid) {
            $vuccBand[$grid['gridsquare']]['lotw'] = 'Y';
        }

        $col_vucc_grids_confirmed_qsl = $this->get_vucc_summary_col_vucc($band, 'lotw');

        foreach ($col_vucc_grids_confirmed_qsl as $gridSplit) {
            $grids = explode(",", $gridSplit['col_vucc_grids']);
            foreach($grids as $key) {
                $grid_four = strtoupper(substr(trim($key),0,4));
                $vuccBand[$grid_four]['lotw'] = 'Y';
            }
        }

        $col_vucc_grids_confirmed_lotw = $this->get_vucc_summary_col_vucc($band, 'qsl');

        foreach ($col_vucc_grids_confirmed_lotw as $gridSplit) {
            $grids = explode(",", $gridSplit['col_vucc_grids']);
            foreach($grids as $key) {
                $grid_four = strtoupper(substr(trim($key),0,4));
                $vuccBand[$grid_four]['qsl'] = 'Y';
            }
        }

        return $vuccBand;
    }

    function getWorkedGridsList($band, $confirmationMethod) {

        $col_gridsquare_worked = $this->get_vucc_summary($band, $confirmationMethod);

        $workedGridArray = array();
        foreach ($col_gridsquare_worked as $workedgrid) {
            array_push($workedGridArray, $workedgrid['gridsquare']);
        }

        $col_vucc_grids_worked = $this->get_vucc_summary_col_vucc($band, $confirmationMethod);

        foreach ($col_vucc_grids_worked as $gridSplit) {
            $grids = explode(",", $gridSplit['col_vucc_grids']);
            foreach($grids as $key) {
                $grid_four = strtoupper(substr(trim($key),0,4));

                if(!in_array($grid_four, $workedGridArray)){
                    array_push($workedGridArray, $grid_four);
                }
            }
        }

        return $workedGridArray;
    }

    private function get_vucc_combined_data($band = 'All') {
        if (!$this->logbooks_locations_array) {
            return ['gridsquare' => [], 'vucc_grids' => []];
        }

        $results = ['gridsquare' => [], 'vucc_grids' => []];

        $inPlaceholders = str_repeat('?,', count($this->logbooks_locations_array) - 1) . '?';

        // Query 1: Get col_gridsquare data with worked/confirmed status
        $bindings1 = array_merge($this->logbooks_locations_array);
        $bandCondition1 = '';

        if ($band != 'All') {
            if ($band == 'SAT') {
                $bandCondition1 = " and log.col_prop_mode = ?";
                $bindings1[] = $band;
            } else {
                $bandCondition1 = " and log.col_prop_mode != ? and log.col_band = ?";
                $bindings1[] = 'SAT';
                $bindings1[] = $band;
            }
        } else {
            $bandCondition1 = " and log.col_prop_mode != ?";
            $bindings1[] = 'SAT';
        }

        $sql1 = "SELECT
            DISTINCT UPPER(SUBSTRING(col_gridsquare, 1, 4)) as gridsquare,
            MAX(CASE WHEN (col_qsl_rcvd='Y' OR col_lotw_qsl_rcvd='Y') THEN 1 ELSE 0 END) as confirmed
            FROM " . $this->config->item('table_name') . " log
            INNER JOIN bands b ON (b.band = log.col_band)
            WHERE log.station_id IN (" . $inPlaceholders . ")
                AND log.col_gridsquare <> ''
                AND b.bandgroup IN ('vhf','uhf','shf','sat')"
            . $bandCondition1 . "
            GROUP BY UPPER(SUBSTRING(col_gridsquare, 1, 4))";

        $query1 = $this->db->query($sql1, $bindings1);
        if ($query1->num_rows() > 0) {
            $results['gridsquare'] = $query1->result_array();
        }

        // Query 2: Get col_vucc_grids data with worked/confirmed status
        // Note: col_vucc_grids has NO band filter when band='All' (includes SAT)
        $bindings2 = array_merge($this->logbooks_locations_array);
        $bandCondition2 = '';

        if ($band != 'All') {
            if ($band == 'SAT') {
                $bandCondition2 = " and col_prop_mode = ?";
                $bindings2[] = $band;
            } else {
                $bandCondition2 = " and col_prop_mode != ? and col_band = ?";
                $bindings2[] = 'SAT';
                $bindings2[] = $band;
            }
        }
        // When band='All', NO band filter is added (includes all prop_mode including SAT)

        $sql2 = "SELECT
            DISTINCT col_vucc_grids,
            MAX(CASE WHEN (col_qsl_rcvd='Y' OR col_lotw_qsl_rcvd='Y') THEN 1 ELSE 0 END) as confirmed
            FROM " . $this->config->item('table_name') . "
            WHERE station_id IN (" . $inPlaceholders . ")
                AND col_vucc_grids <> ''"
            . $bandCondition2 . "
            GROUP BY col_vucc_grids";

        $query2 = $this->db->query($sql2, $bindings2);
        if ($query2->num_rows() > 0) {
            $results['vucc_grids'] = $query2->result_array();
        }

        return $results;
    }

    /*
    * Builds the array to display worked/confirmed vucc on dashboard page
    */
    function fetchVuccSummary($band = 'All') {
        // Use associative arrays for O(1) lookups instead of O(n) in_array()
        $totalGridWorked = [];
        $totalGridConfirmed = [];

        // Get combined data (2 queries instead of 4)
        $data = $this->get_vucc_combined_data($band);

        // Process col_gridsquare data
        if (!empty($data['gridsquare'])) {
            foreach ($data['gridsquare'] as $row) {
                $grid = $row['gridsquare'];
                // Always add to worked
                $totalGridWorked[$grid] = true;
                // Add to confirmed if flagged
                if ($row['confirmed']) {
                    $totalGridConfirmed[$grid] = true;
                }
            }
        }

        // Process col_vucc_grids data
        if (!empty($data['vucc_grids'])) {
            foreach ($data['vucc_grids'] as $row) {
                $grids = explode(",", $row['col_vucc_grids']);
                foreach ($grids as $key) {
                    $grid_four = strtoupper(substr(trim($key), 0, 4));
                    // Always add to worked
                    $totalGridWorked[$grid_four] = true;
                    // Add to confirmed if flagged
                    if ($row['confirmed']) {
                        $totalGridConfirmed[$grid_four] = true;
                    }
                }
            }
        }

        $vuccArray[$band]['worked'] = count($totalGridWorked);
        $vuccArray[$band]['confirmed'] = count($totalGridConfirmed);

        return $vuccArray;
    }
}
?>
