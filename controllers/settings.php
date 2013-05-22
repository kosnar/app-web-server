<?php

/**
 * Web server general settings controller.
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
 * Web server general settings controller.
 *
 * @category   apps
 * @package    web-server
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/web_server/
 */

class Settings extends ClearOS_Controller
{
    /**
     * Web server summary view.
     *
     * @return view
     */

    function index()
    {
        $this->_form('view');
    }

    /**
     * Edit view.
     *
     * @return view
     */

    function edit()
    {
        $this->_form('edit');
    }

    /**
     * View view.
     *
     * @return view
     */

    function view()
    {
        $this->_form('view');
    }

    /**
     * Common form.
     *
     * @param string $form_type form type
     *
     * @return view
     */

    function _form($form_type)
    {
        // Load libraries
        //---------------

        $this->lang->load('web_server');
        $this->load->library('web_server/Httpd');

        // Set validation rules
        //---------------------

        $this->form_validation->set_policy('server_name', 'web_server/Httpd', 'validate_server_name', TRUE);

        $form_ok = $this->form_validation->run();

        // Handle form submit
        //-------------------

        if ($this->input->post('submit') && ($form_ok === TRUE)) {

            try {
                $this->httpd->set_server_name($this->input->post('server_name'));

                $this->page->set_status_updated();
                redirect('/web_server/settings');
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        // Load the view data 
        //------------------- 

        try {
            $data['form_type'] = $form_type;
            $data['server_name'] = $this->httpd->get_server_name();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load the views
        //---------------

        $this->page->view_form('web_server/settings', $data, lang('base_settings'));
    }
}
