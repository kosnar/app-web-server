<?php

/**
 * Web server sites controller.
 *
 * @category   apps
 * @package    web-server
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012 ClearFoundation
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
 * @copyright  2012 ClearFoundation
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
        $this->_item($site, 'add');
    }

    /**
     * Add view.
     *
     * @param string $site site
     *
     * @return view
     */

    function add_default($site = NULL)
    {
        $this->_item($site, 'add_default', TRUE);
    }

    /**
     * Delete view.
     *
     * @param string $site site
     *
     * @return view
     */

    function delete($site = NULL)
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

    function edit($site = NULL)
    {
        $this->_item($site, 'edit');
    }

    /**
     * Edit view.
     *
     * @return view
     */

    function edit_default()
    {
        $this->_item('default', 'edit_default', TRUE);
    }

    /**
     * Destroys site.
     *
     * @param string $site site
     *
     * @return view
     */

    function destroy($site = NULL)
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
     * @param string  $site       site
     * @param string  $form_type  form type
     * @param boolean $is_default set to TRUE if this is the default site
     *
     * @return view
     */

    function _item($site, $form_type, $is_default = FALSE)
    {
        // Load libraries
        //---------------

        $this->lang->load('web_server');
        $this->load->library('web_server/Httpd');
        $this->load->factory('groups/Group_Manager_Factory');

        // Set validation rules
        //---------------------

        $check_exists = ($form_type === 'add') ? TRUE : FALSE;

        $group = ($this->input->post('group')) ? $this->input->post('group') : '';
        $ftp_state = ($this->input->post('ftp')) ? $this->input->post('ftp') : FALSE;
        $file_state = ($this->input->post('file')) ? $this->input->post('file') : FALSE;

        $this->form_validation->set_policy('site', 'web_server/Httpd', 'validate_site', TRUE, $check_exists);
        $this->form_validation->set_policy('aliases', 'web_server/Httpd', 'validate_aliases');
        $this->form_validation->set_policy('ftp', 'web_server/Httpd', 'validate_ftp_state');
        $this->form_validation->set_policy('file', 'web_server/Httpd', 'validate_file_state');
        $this->form_validation->set_policy('group', 'web_server/Httpd', 'validate_group');

        $form_ok = $this->form_validation->run();

        // Handle form submit
        //-------------------

        if ($this->input->post('submit') && ($form_ok === TRUE)) {
            try {
                if (($form_type === 'edit') || ($form_type === 'edit_default')) {
                    $this->httpd->set_site(
                        $this->input->post('site'),
                        $this->input->post('aliases'),
                        $group,
                        $ftp_state,
                        $file_state,
                        $is_default
                    );

                    $this->page->set_status_updated();
                } else {
                    $this->httpd->add_site(
                        $this->input->post('site'),
                        $this->input->post('aliases'),
                        $group,
                        $ftp_state,
                        $file_state,
                        $is_default
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
            $data['default_set'] = $this->httpd->is_default_set();

            $data['ftp_available'] = clearos_app_installed('ftp');
            $data['file_available'] = clearos_app_installed('samba');

            if (($form_type === 'edit') || ($form_type === 'edit_default'))
                $info = $this->httpd->get_site_info($site);

            $data['site'] = empty($info['server_name']) ? '' :  $info['server_name'];
            $data['aliases'] = empty($info['aliases']) ? '' :  $info['aliases'];
            $data['ftp'] = empty($info['ftp']) ? TRUE :  $info['ftp'];
            $data['file'] = empty($info['file']) ? TRUE :  $info['file'];
            $data['group'] = empty($info['group']) ? '' :  $info['group'];

            $groups = $this->group_manager->get_details();

            foreach ($groups as $group => $details)
                $data['groups'][$group] = $group . ' - ' . $details['core']['description'];

        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load the views
        //---------------

        $this->page->view_form('web_server/site', $data, lang('web_server_web_site'));
    }
}
