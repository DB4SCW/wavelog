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

        $headerName = $this->config->item('auth_header_value') ?: '';
        if (empty($headerName)) {
            $this->session->set_flashdata('error', __('Missing header setting.'));
            redirect('user/login');
        }
        $username = $this->input->server($headerName, true);

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
                $club_id = $this->config->item('auth_header_club_id');
                $result = $this->user_model->add_minimal(username: $username, club_id: $club_id);
                
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
