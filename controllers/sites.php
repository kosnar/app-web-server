<?php

/**
 * Web server sites controller.
 *
 * @category   Apps
 * @package    Web_Server
 * @subpackage Controllers
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
 * @category   Apps
 * @package    Web_Server
 * @subpackage Controllers
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
     * @param string $domain domain
     *
     * @return view
     */

    function add($domain = NULL)
    {
        $this->_item($domain, 'add');
    }

    /**
     * Delete view.
     *
     * @param string $domain domain
     *
     * @return view
     */

    function delete($domain = NULL)
    {
        $confirm_uri = '/app/web_server/sites/destroy/' . $domain;
        $cancel_uri = '/app/web_server/sites';
        $items = array($domain);

        $this->page->view_confirm_delete($confirm_uri, $cancel_uri, $items);
    }

    /**
     * Edit view.
     *
     * @param string $domain domain
     *
     * @return view
     */

    function edit($domain = NULL)
    {
        $this->_item($domain, 'edit');
    }

    /**
     * Destroys domain.
     *
     * @param string $domain domain
     *
     * @return view
     */

    function destroy($domain = NULL)
    {
        // Load libraries
        //---------------

        $this->load->library('web_server/Httpd');

        // Handle delete
        //--------------

        try {
            $this->httpd->delete_virtual_host($domain);

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
     * @param string $domain    domain
     * @param string $form_type form type
     *
     * @return view
     */

    function _item($domain, $form_type)
    {
        // Load libraries
        //---------------

        $this->lang->load('web_server');
        $this->load->library('web_server/Httpd');
        $this->load->factory('groups/Group_Manager_Factory');

        // Set validation rules
        //---------------------

        $check_exists = ($form_type === 'add') ? TRUE : FALSE;

        $this->form_validation->set_policy('ip', 'network/Hosts', 'validate_ip', TRUE, $check_exists);
        $this->form_validation->set_policy('hostname', 'network/Hosts', 'validate_hostname', TRUE);

        foreach ($_POST as $key => $value) {
            if (preg_match('/^alias([0-9])+$/', $key))
                $this->form_validation->set_policy($key, 'network/Hosts', 'validate_alias');
        }

        $form_ok = $this->form_validation->run();

        // Handle form submit
        //-------------------

        if ($this->input->post('submit') && ($form_ok === TRUE)) {

            $ip = $this->input->post('ip');
            $hostname = $this->input->post('hostname');
            $aliases = array();

            foreach ($_POST as $key => $value) {
                if (preg_match('/^alias([0-9])+$/', $key) && !(empty($value)))
                    $aliases[] = $this->input->post($key);
            }

            try {
                if ($form_type === 'edit') 
                    $this->hosts->edit_entry($ip, $hostname, $aliases);
                else
                    $this->hosts->add_entry($ip, $hostname, $aliases);

                $this->dnsmasq->reset();

                // Return to summary page with status message
                $this->page->set_status_added();
                redirect('/dns');
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        // Load the view data 
        //------------------- 

        try {
            if ($form_type === 'edit') 
                $info = $this->httpd->get_site_info($site);

            // FIXME: move this logic to group manager... it's used a lot.
            $normal_groups = $this->group_manager->get_details();
            $builtin_groups = $this->group_manager->get_details('builtin');

            $groups = array_merge($builtin_groups, $normal_groups);

            foreach ($groups as $group => $details)
                $data['groups'][$group] = $group . ' - ' . $details['description'];
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        $data['form_type'] = $form_type;

        // Load the views
        //---------------

        $this->page->view_form('web_server/site', $data, lang('web_server_web_site'));
    }
}
