<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/*

	Handles header based authentication

*/
class Header_auth extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('user_model');
        $this->load->library('session');
        $this->load->helper('url');
    }

    /**  
     * Authenticate using a trusted request header.  
     * Expected to be called from a login-screen button.  
     */
    public function login()
    {
        // Guard: feature must be enabled  
        if (!$this->config->item('auth_header_enable')) {
            $this->session->set_flashdata('error', __('Header authentication is disabled.'));
            redirect('user/login');
        }


        // Get username from header
        $headerUsername = $this->config->item('auth_headers_username') ?: '';
        if (empty($headerUsername)) {
            $this->session->set_flashdata('error', __('Missing header setting.'));
            redirect('user/login');
        }
        $username = $this->input->server($headerUsername, true);

        if (empty($username)) {
            $this->session->set_flashdata('error', __('Missing username header.'));
            redirect('user/login');
        }

        // Look up user by the header value  
        $query = $this->user_model->get($username);
        if (!$query || $query->num_rows() !== 1) {

            // Config check if create user
            if ($this->config->item('auth_header_create')) {
                $this->load->model('user_model');
                $firstnameHeader = $this->config->item('auth_headers_firstname') ?: '';
                if (!empty($firstnameHeader)) {
                    $firstname = $this->input->server($firstnameHeader, true);
                } else {
                    $firstname = '';
                }
                $lastnameHeader = $this->config->item('auth_headers_lastname') ?: '';
                if (!empty($lastnameHeader)) {
                    $lastname = $this->input->server($lastnameHeader, true);
                } else {
                    $lastname = '';
                }
                $callsignHeader = $this->config->item('auth_headers_callsign') ?: '';
                if (!empty($callsignHeader)) {
                    $callsign = $this->input->server($callsignHeader, true);
                } else {
                    $callsign = '';
                }
                $emailHeader = $this->config->item('auth_headers_email') ?: '';
                if (!empty($emailHeader)) {
                    $email = $this->input->server($emailHeader, true);
                } else {
                    $email = '';
                }

                $club_id = $this->config->item('auth_header_club_id') ?: '';

                $result = $this->user_model->add(
                    $username,
                    bin2hex(random_bytes(64)),  // password
                    $email,
                    3,	// $data['user_type'], Anlage auf 3
                    $firstname,
                    $lastname,
                    $callsign,
                    "",                     // locator
                    102,                    // user_timezone
                    "M",                    // measurement
                    "Y",                    // dashboard_map
                    "Y-m-d",                // user_date_format
                    'darkly',               // user_stylesheet
                    '0',                    // user_qth_lookup
                    '0',                    // user_sota_lookup
                    '0',                    // user_wwff_lookup
                    '0',                    // user_pota_lookup
                    1,                      // user_show_notes
                    'Mode',                 // user_column1
                    'RSTS',                 // user_column2
                    'RSTR',                 // user_column3
                    'Band',                 // user_column4
                    'Country',              // user_column5
                    '0',                    // user_show_profile_image
                    '0',                    // user_previous_qsl_type
                    '0',                    // user_amsat_status_upload
                    '',                     // user_mastodon_url
                    'ALL',                  // user_default_band
                    'QL',                   // user_default_confirmation
                    '0',                    // user_qso_end_times
                    "Y",                    // user_qso_db_search_priority
                    '0',                    // user_quicklog
                    '0',                    // user_quicklog_enter
                    "en",                   // user_language
                    '',                     // user_hamsat_key
                    '',                     // user_hamsat_workable_only
                    '',                     // user_iota_to_qso_tab
                    '',                     // user_sota_to_qso_tab
                    '',                     // user_wwff_to_qso_tab
                    '',                     // user_pota_to_qso_tab
                    '',                     // user_sig_to_qso_tab
                    '',                     // user_dok_to_qso_tab
                    0,                      // user_station_to_qso_tab
                    '',                     // user_lotw_name
                    '',                     // user_lotw_password
                    '',                     // user_eqsl_name
                    '',                     // user_eqsl_password
                    '',                     // user_clublog_name
                    '',                     // user_clublog_password
                    '0',                    // user_winkey
                    "",                     // on_air_widget_enabled
                    "",                     // on_air_widget_display_last_seen
                    "",                     // on_air_widget_show_only_most_recent_radio
                    "",                     // qso_widget_display_qso_time
                    "",                     // dashboard_banner
                    "",                     // dashboard_solar
                    "",                     // global_oqrs_text
                    "",                     // oqrs_grouped_search
                    "",                     // oqrs_grouped_search_show_station_name
                    "",                     // oqrs_auto_matching
                    "",                     // oqrs_direct_auto_matching
                    "",                     // user_dxwaterfall_enable
                );
                
                switch ($result) {
                    case EUSERNAMEEXISTS:
                        $data['username_error'] = sprintf(__("Username %s already in use!"), '<b>' . $this->input->post('user_name') . '</b>');
                        break;
                    case EEMAILEXISTS:
                        $data['email_error'] = sprintf(__("E-mail %s already in use!"), '<b>' . $this->input->post('user_email') . '</b>');
                        break;
                    case EPASSWORDINVALID:
                        $data['password_error'] = __("Invalid Password!");
                        break;
                    case OK:
                        redirect('header_auth/login');
                        return;
                }
            } else {
                $this->session->set_flashdata('error', __('User not found.'));
                redirect('user/login');
            }
        }


        $user = $query->row();

        // Prevent clubstation direct login via header (mirrors User::login)  
        if (!empty($user->clubstation) && $user->clubstation == 1) {
            $this->session->set_flashdata('error', __("You can't login to a clubstation directly. Use your personal account instead."));
            redirect('user/login');
        }

        // Maintenance mode check (admin only allowed)  
        if (ENVIRONMENT === 'maintenance' && (int)$user->user_type !== 99) {
            $this->session->set_flashdata('error', __("Sorry. This instance is currently in maintenance mode. Only administrators are currently allowed to log in."));
            redirect('user/login');
        }

        // Establish session  
        $this->user_model->update_session($user->user_id);
        $this->user_model->set_last_seen($user->user_id);

        // Set language cookie (mirrors User::login)  
        $cookie = [
            'name'   => $this->config->item('gettext_cookie', 'gettext'),
            'value'  => $user->user_language,
            'expire' => 1000,
            'secure' => false,
        ];
        $this->input->set_cookie($cookie);

        $this->load->model('user_model');  
        // Get full user record  
        $user = $this->user_model->get($username)->row();  
        
        // Critical: Update session data  
        $this->user_model->update_session($user->user_id);  
        $this->user_model->set_last_seen($user->user_id);  

        // Set essential session data  
        $this->session->set_userdata(array(  
            'user_id' => $user->user_id,  
            'user_name' => $user->user_name,  
            'user_type' => $user->user_type,  
            'user_stylesheet' => $user->user_stylesheet ?? 'bootstrap',  
            'user_column1' => $user->user_column1 ?? 'Mode',  
            'user_column2' => $user->user_column2 ?? 'RSTS',  
            'user_column3' => $user->user_column3 ?? 'RSTR',  
            'user_column4' => $user->user_column4 ?? 'Band',  
            'user_column5' => $user->user_column5 ?? 'Country',  
            // Add other preferences as needed  
        ));

        log_message('info', "User ID [{$user->user_id}] logged in via header auth.");
        redirect('dashboard');
    }
}
