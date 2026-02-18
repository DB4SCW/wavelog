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
            $this->session->set_flashdata('error', __('User not found.'));
            redirect('user/login');
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

        log_message('info', "User ID [{$user->user_id}] logged in via header auth.");
        redirect('dashboard');
    }
}
