<?php

/**
 * Web app controller.
 *
 * @category   apps
 * @package    web-server
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012-2013 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/web_server/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

use \Exception as Exception;

use \clearos\apps\web_server\Httpd as Httpd;
use \clearos\apps\flexshare\Flexshare as Flexshare;

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Web app controller.
 *
 * @category   apps
 * @package    web-server
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012-2013 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/web_server/
 */

class Apps extends ClearOS_Controller
{
    protected $web_app = NULL;
    protected $app_name = NULL;
    protected $description = NULL;

    /**
     * Web app constructor.
     *
     * @param string $web_app     web app name
     * @param string $app_name    app name
     * @param string $description web app description
     *
     * @return view
     */

    function __construct($web_app, $app_name, $description)
    {
        $this->web_app = $web_app;
        $this->app_name = $app_name;
        $this->description = $description;
    }

    /**
     * Apps summary view.
     *
     * @return view
     */

    function index()
    {
        $this->_item('view');
    }

    /**
     * Edit view.
     *
     * @return view
     */

    function edit()
    {
        $this->_item('edit');
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Common form.
     *
     * @param string $form_type form type
     *
     * @return view
     */

    function _item($form_type)
    {
        // Load libraries
        //---------------

        $this->lang->load('web_server');
        $this->load->library('web_server/Httpd');
        $this->load->library('flexshare/Flexshare');
        $this->load->factory('groups/Group_Manager_Factory');

        // Set validation rules
        //---------------------

        $group = ($this->input->post('group')) ? $this->input->post('group') : '';
        $ftp_state = ($this->input->post('ftp')) ? $this->input->post('ftp') : FALSE;
        $file_state = ($this->input->post('file')) ? $this->input->post('file') : FALSE;

        $this->form_validation->set_policy('server_name', 'web_server/Httpd', 'validate_server_name', TRUE);
        $this->form_validation->set_policy('server_alias', 'web_server/Httpd', 'validate_aliases');
        $this->form_validation->set_policy('directory_alias', 'flexshare/Flexshare', 'validate_web_directory_alias');
        $this->form_validation->set_policy('server_name_alternate', 'web_server/Httpd', 'validate_server_name', TRUE);
        $this->form_validation->set_policy('server_alias_alternate', 'web_server/Httpd', 'validate_aliases');
        $this->form_validation->set_policy('directory_alias_alternate', 'flexshare/Flexshare', 'validate_web_directory_alias');
        $this->form_validation->set_policy('ftp', 'web_server/Httpd', 'validate_ftp_state', TRUE);
        $this->form_validation->set_policy('file', 'web_server/Httpd', 'validate_file_state', TRUE);
        $this->form_validation->set_policy('group', 'web_server/Httpd', 'validate_group', TRUE);

        $this->form_validation->set_policy('web_access', 'flexshare/Flexshare', 'validate_web_access', TRUE);
        $this->form_validation->set_policy('require_authentication', 'flexshare/Flexshare', 'validate_web_require_authentication', TRUE);
        $this->form_validation->set_policy('show_index', 'flexshare/Flexshare', 'validate_web_show_index', TRUE);
        $this->form_validation->set_policy('follow_symlinks', 'flexshare/Flexshare', 'validate_web_follow_symlinks', TRUE);
        $this->form_validation->set_policy('ssi', 'flexshare/Flexshare', 'validate_web_allow_ssi', TRUE);
        $this->form_validation->set_policy('htaccess', 'flexshare/Flexshare', 'validate_web_htaccess_override', TRUE);
        $this->form_validation->set_policy('php', 'flexshare/Flexshare', 'validate_web_php', TRUE);
        $this->form_validation->set_policy('cgi', 'flexshare/Flexshare', 'validate_web_cgi', TRUE);

        $form_ok = $this->form_validation->run();

        // Handle form submit
        //-------------------

        if ($this->input->post('submit') && ($form_ok === TRUE)) {

            $options['server_name'] = $this->input->post('server_name');
            $options['server_name_alternate'] = $this->input->post('server_name_alternate');
            $options['server_alias_alternate'] = $this->input->post('server_alias_alternate');
            $options['directory_alias'] = $this->input->post('directory_alias');
            $options['directory_alias_alternate'] = $this->input->post('directory_alias_alternate');

            $options['web_access'] = $this->input->post('web_access');
            $options['require_authentication'] = $this->input->post('require_authentication');
            $options['require_ssl'] = FALSE;
            $options['show_index'] = $this->input->post('show_index');
            $options['follow_symlinks'] = $this->input->post('follow_symlinks');
            $options['ssi'] = $this->input->post('ssi');
            $options['htaccess'] = $this->input->post('htaccess');
            $options['php'] = $this->input->post('php');
            $options['cgi'] = $this->input->post('cgi');
            $options['comment'] = $this->description;

            try {
                $this->httpd->set_site(
                    $this->web_app,
                    $this->input->post('server_alias'),
                    $group,
                    $ftp_state,
                    $file_state,
                    $type,
                    $options
                );

                $this->page->set_status_updated();

                redirect('/' . $this->app_name . '/');
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        // Load the view data 
        //------------------- 

        try {
            $data['form_type'] = $form_type;
            $data['app_name'] = $this->app_name;
            $data['ftp_available'] = clearos_app_installed('ftp');
            $data['file_available'] = clearos_app_installed('samba');
            $data['accessibility_options'] = $this->flexshare->get_web_access_options();

            $data['info'] = $this->httpd->get_site($this->web_app);

            $groups = $this->group_manager->get_details();

            foreach ($groups as $group => $details)
                $data['groups'][$group] = $group . ' - ' . $details['core']['description'];
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Defaults
        $data['info']['WebEnabled'] = TRUE;

        if (! isset($data['info']['WebAccess']))
            $data['info']['WebAccess'] = Flexshare::ACCESS_ALL;

        if (! isset($data['info']['WebHtaccessOverride']))
            $data['info']['WebHtaccessOverride'] = TRUE;

        if (! isset($data['info']['WebReqSsl']))
            $data['info']['WebReqSsl'] = TRUE;

        if (! isset($data['info']['WebReqAuth']))
            $data['info']['WebReqAuth'] = FALSE;

        if (! isset($data['info']['WebShowIndex']))
            $data['info']['WebShowIndex'] = TRUE;

        if (! isset($data['info']['WebPhp']))
            $data['info']['WebPhp'] = TRUE;

        if (! isset($data['info']['WebCgi']))
            $data['info']['WebCgi'] = FALSE;

        // Load the views
        //---------------

        $this->page->view_form('web_server/app', $data, $this->description);
    }
}
