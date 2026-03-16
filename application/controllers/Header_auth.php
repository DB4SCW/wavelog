<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/*

	Handles header based authentication

*/
class Header_auth extends CI_Controller {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Authenticate using a trusted request header/JWT token.
     */
    public function login() {
        // Guard: feature must be enabled
        if (!$this->config->item('auth_header_enable')) {
            $this->_sso_error(__("SSO Authentication is not enabled. Check your configuration."));
        }

        // Decode JWT access token forwarded by idp
        $accesstoken_path = $this->config->item('auth_headers_accesstoken') ?? false;
        if (!$accesstoken_path) {
            log_message('error', 'SSO Authentication: Access Token Path not configured in config.php.');
            $this->_sso_error();
        }
        $token = $this->input->server($accesstoken_path, true);
        if (empty($token)) {
            log_message('error', 'SSO Authentication: Missing access token header.');
            $this->_sso_error();
        }

        $claims = $this->_decode_jwt_payload($token);
        if (empty($claims)) {
            log_message('error', 'SSO Authentication: Invalid access token format. Failed to decode JWT token.');
            $this->_sso_error();
        }

        if (!$this->_verify_jwtdata($claims)) {
            log_message('error', 'SSO Authentication: Token validation failed.');
            $this->_sso_error();
        }

        $callsign_claim = $this->config->item('auth_headers_callsign_claim') ?? 'callsign';

        $username  = $claims['preferred_username'] ?? '';
        $email     = $claims['email']              ?? '';
        $callsign  = $claims[$callsign_claim]      ?? '';
        $firstname = $claims['given_name']         ?? '';
        $lastname  = $claims['family_name']        ?? '';

        if (empty($username)) {
            log_message('error', 'SSO Authentication: Missing username claim in access token.');
            $this->_sso_error();
        }

        // Look up user by the header value
        $this->load->model('user_model');
        $query = $this->user_model->get($username);

        if (!$query || $query->num_rows() !== 1) {
            // Config check if create user
            if ($this->config->item('auth_header_create')) {
                $this->_create_user($username, $email, $callsign, $firstname, $lastname);
            } else {
                $this->_sso_error(__("User not found."));
            }
        }

        $user = $query->row();

        // Prevent clubstation direct login via header (mirrors User::login)  
        if (!empty($user->clubstation) && $user->clubstation == 1) {
            $this->_sso_error(__("You can't login to a clubstation directly. Use your personal account instead."));
        }

        // Maintenance mode check (admin only allowed)  
        if (ENVIRONMENT === 'maintenance' && (int)$user->user_type !== 99) {
            $this->_sso_error(__("Sorry. This instance is currently in maintenance mode. Only administrators are currently allowed to log in."));
        }

        // Establish session  
        $this->user_model->update_session($user->user_id);
        $this->user_model->set_last_seen($user->user_id);

        // Set language cookie (mirrors User::login)  
        $cookie = [
            'name'   => $this->config->item('gettext_cookie', 'gettext'),
            'value'  => $user->user_language,
            'expire' => 1000,
            'secure' => $this->config->item('cookie_secure'),
        ];
        $this->input->set_cookie($cookie);

        log_message('info', "User ID [{$user->user_id}] logged in via SSO.");
        redirect('dashboard');
    }

    /**
     * Decode a JWT token
     * 
     * @param string $token
     * 
     * @return array|null
     */
    private function _decode_jwt_payload(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $decode = function (string $part): ?array {
            $json = base64_decode(str_pad(strtr($part, '-_', '+/'), strlen($part) % 4, '=', STR_PAD_RIGHT));
            if ($json === false) return null;
            $data = json_decode($json, true);
            return is_array($data) ? $data : null;
        };

        $header  = $decode($parts[0]);
        $payload = $decode($parts[1]);
        if ($payload === null) {
            return null;
        }

        // Merge alg from header into payload so _verify_jwtdata can check it
        if (isset($header['alg'])) {
            $payload['alg'] = $header['alg'];
        }

        return $payload;
    }

    /**
     * Helper to verify some long hangig fruits. We are not verifying the JWT token against the issuer at this point.
     * Reason is the need for a crypto lib which is not necessary at this point. An administrator is responsible
     * for the proper isolation of Wavelog and needs to make sure that Wavelog is not exposed directly.
     * 
     * Additonal verificarion steps can be added at a later point.
     * 
     * @param array claim data
     * 
     * @return bool
     */
    private function _verify_jwtdata(?array $claims = null): bool {
        // No claim, no verificiation
        if (!$claims) {
            log_message('error', 'JWT Verification: No claim data received.');
            return false;
        }

        // Check expire date
        if (($claims['exp'] ?? 0) < time()) {
            log_message('error', 'JWT Verification: JWT Token is expired.');
            return false;
        }

        // Is the token already valid
        if (isset($claims['nbf']) && $claims['nbf'] > time()) {
            log_message('error', 'JWT Verification: JWT Token is not valid yet.');
            return false;
        }

        // The token should not be older then 24 hours which would be absurd old for an JWT token
        if (isset($claims['iat']) && $claims['iat'] < (time() - 86400)) {
            log_message('error', 'JWT Verification: Token is older then 24 hours. This is very unusual. Verification failed.');
            return false;
        }

        // Is it a bearer token?
        if (isset($claims['typ']) && $claims['typ'] !== 'Bearer') {
            log_message('error', 'JWT Verification: JWT Token is no Bearer Token.');
            return false;
        }

        // prevent alg: none attacks
        if (!in_array($claims['alg'], ['RS256', 'RS384', 'RS512', 'ES256', 'ES384'], true)) {
            log_message('error', 'JWT Verification: Algorithm ' . ($claims['alg'] ?? '???') . ' is not allowed. Create an issue on github https://github.com/wavelog/wavelog.');
            return false;
        }

        return true;
    }

    /**
     * Helper to create a user if it does not exist.
     * 
     * @param string $username
     * @param string $email
     * @param string $callsign
     * @param string $firstname
     * @param string $lastname
     * 
     * @return void
     */
    private function _create_user($username, $email, $callsign, $firstname, $lastname) {
        if (empty($email) || empty($callsign)) {
            log_message('error', 'SSO Authentication: Missing email or callsign claim in access token.');
            $this->_sso_error();
        }

        // $club_id = $this->config->item('auth_header_club_id') ?: ''; // TODO: Add support to add a user to a clubstation
        $this->load->model('user_model');
        $result = $this->user_model->add(
            $username,
            bin2hex(random_bytes(64)),  // password
            $email,
            3,                      // $data['user_type'], we don't create admins for security reasons
            $firstname,
            $lastname,
            $callsign,
            "",                     // locator
            24,                     // user_timezone is default UTC
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
            "english",              // user_language
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
            "",                     // user_qso_show_map
            0,                      // clubstation
            true,                   // external_account
        );

        switch ($result) {
            case EUSERNAMEEXISTS:
                log_message('error', 'SSO Authentication: The SSO Integration tried to create a new User because the Username was not found. But the Username already exists. This should not happen as the user should be looked up by the same username before. Check your user provisioning and claims mapping configuration. Otherwise create an issue on https://github.com/wavelog/wavelog');
                $this->_sso_error(__("Something went terribly wrong. Check the error log."));
                break;
            case EEMAILEXISTS:
                log_message('error', 'SSO Authentication: The SSO Integration tried to create a new User because the Username was not found. But the E-mail for the new User already exists for an existing user. Check for existing Wavelog users with the same e-mail address as the one provided by your IdP.');
                $this->_sso_error(__("Something went terribly wrong. Check the error log."));
                break;
            case OK:
                return;
        }
    }

    /**
     * Helper to set flashdata and redirect to login with an error message. We use this a lot in the SSO login process, so we need a helper for this.
     * 
     * @param string|null $message
     * 
     * @return void
     */
    private function _sso_error($message = null) {
        if ($message === null) {
            $message = __("SSO Config Error. Check error log.");
        }
        $this->session->set_flashdata('error', $message);
        redirect('user/login');
        die;
    }
}
