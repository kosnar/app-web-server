<?php

/**
 * Web server sites controller.
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
 * Web server sites controller.
 *
 * @category   apps
 * @package    web-server
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012-2013 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/web_server/
 */

class Sites extends ClearOS_Controller
{
    /**
     * Sites summary view.
     *
     * @return view
     */

    function index()
    {
        // Show account status widget if we're not in a happy state
        //---------------------------------------------------------

        $this->load->module('accounts/status');

        if ($this->status->unhappy()) {
            $this->status->widget('web_server');
            return;
        }

        // Load libraries
        //---------------

        $this->lang->load('web_server');
        $this->load->library('web_server/Httpd');

        // Load view data
        //---------------

        try {
            $data['sites'] = $this->httpd->get_sites();
            $data['default_set'] = $this->httpd->is_default_set();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
 
        // Load views
        //-----------

        $this->page->view_form('web_server/sites', $data, lang('web_server_web_sites'));
    }

    /**
     * Add view.
     *
     * @param string $site site
     *
     * @return view
     */

    function add($site = NULL)
    {
        $this->_item('add', $site);
    }

    /**
     * Delete view.
     *
     * @param string $site site
     *
     * @return view
     */

    function delete($site)
    {
        $confirm_uri = '/app/web_server/sites/destroy/' . $site;
        $cancel_uri = '/app/web_server/sites';
        $items = array($site);

        $this->page->view_confirm_delete($confirm_uri, $cancel_uri, $items);
    }

    /**
     * Edit view.
     *
     * @param string $site site
     *
     * @return view
     */

    function edit($site)
    {
        $this->_item('edit', $site);
    }

    /**
     * Destroys site.
     *
     * @param string $site site
     *
     * @return view
     */

    function destroy($site)
    {
        // Load libraries
        //---------------

        $this->load->library('web_server/Httpd');

        // Handle delete
        //--------------

        try {
            $this->httpd->delete_site($site);

            $this->page->set_status_deleted();
            redirect('/web_server/sites');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Common form.
     *
     * @param string $form_type form type
     * @param string $site      site
     *
     * @return view
     */

    function _item($form_type, $site)
    {
        // Load libraries
        //---------------

        $this->lang->load('web_server');
        $this->load->library('web_server/Httpd');
        $this->load->library('flexshare/Flexshare');
        $this->load->factory('groups/Group_Manager_Factory');

        // Set validation rules
        //---------------------

        $check_exists = ($form_type === 'add') ? TRUE : FALSE;
        $is_adding_default = ($site === 'default') ? TRUE : FALSE;

        $group = ($this->input->post('group')) ? $this->input->post('group') : '';
        $ftp_state = ($this->input->post('ftp')) ? $this->input->post('ftp') : FALSE;
        $file_state = ($this->input->post('file')) ? $this->input->post('file') : FALSE;
        $is_default = ($this->input->post('is_default')) ? $this->input->post('is_default') : $is_adding_default;

        $this->form_validation->set_policy('site', 'web_server/Httpd', 'validate_site', TRUE, $check_exists);
        $this->form_validation->set_policy('aliases', 'web_server/Httpd', 'validate_aliases');

        if (clearos_app_installed('ftp'))
            $this->form_validation->set_policy('ftp', 'web_server/Httpd', 'validate_ftp_state', TRUE);

        if (clearos_app_installed('samba'))
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
            $type = ($is_default) ? Httpd::TYPE_WEB_SITE_DEFAULT : Httpd::TYPE_WEB_SITE;

            $options['web_access'] = $this->input->post('web_access');
            $options['require_authentication'] = $this->input->post('require_authentication');
            $options['show_index'] = $this->input->post('show_index');
            $options['follow_symlinks'] = $this->input->post('follow_symlinks');
            $options['ssi'] = $this->input->post('ssi');
            $options['htaccess'] = $this->input->post('htaccess');
            $options['php'] = $this->input->post('php');
            $options['cgi'] = $this->input->post('cgi');
            $options['require_ssl'] = FALSE; // Hard code this for now

            try {
                if ($form_type === 'edit') {
                    $this->httpd->set_site(
                        $this->input->post('site'),
                        $this->input->post('aliases'),
                        $group,
                        $ftp_state,
                        $file_state,
                        $type,
                        $options
                    );

                    $this->page->set_status_updated();
                } else {
                    $this->httpd->add_site(
                        $this->input->post('site'),
                        $this->input->post('aliases'),
                        $group,
                        $ftp_state,
                        $file_state,
                        $type,
                        $options
                    );

                    $this->page->set_status_added();
                }

                redirect('/web_server/sites');
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        // Load the view data 
        //------------------- 

        try {
            $data['form_type'] = $form_type;
            $data['ftp_available'] = clearos_app_installed('ftp');
            $data['file_available'] = clearos_app_installed('samba');
            $data['accessibility_options'] = $this->flexshare->get_web_access_options();

            $data['site'] = $site;

            if ($form_type === 'add') {
                $data['is_default'] = ($is_default) ? TRUE : FALSE;
            } else {
                $data['info'] = $this->httpd->get_site($site);
                $data['is_default'] = $this->httpd->is_default($site) ? TRUE : FALSE;
            }

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
            $data['info']['WebReqSsl'] = FALSE;

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

        $this->page->view_form('web_server/site', $data, lang('web_server_web_site'));
    }
}
