<?php

/**
 * Web server site view.
 *
 * @category   apps
 * @package    web-server
 * @subpackage views
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
// Load dependencies
///////////////////////////////////////////////////////////////////////////////

$this->lang->load('base');
$this->lang->load('groups');
$this->lang->load('web_server');

///////////////////////////////////////////////////////////////////////////////
// Form handler
///////////////////////////////////////////////////////////////////////////////

$form = 'web_server/sites/edit_custom/' . $site;
$buttons = array( 
    form_submit_update('submit'),
    anchor_cancel('/app/web_server/sites')
);

///////////////////////////////////////////////////////////////////////////////
// Warning
///////////////////////////////////////////////////////////////////////////////

echo infobox_warning(
    lang('web_server_custom_configuration'),
    lang('web_server_custom_configuration_warning') .
    '<p align="center">' .  anchor_custom('/app/web_server/sites/upgrade/' . $site, lang('web_server_review_standard_configuration')) . '</p>'
);


///////////////////////////////////////////////////////////////////////////////
// Form
///////////////////////////////////////////////////////////////////////////////

echo form_open($form);
echo "<input type='hidden' name='is_default' value='$is_default'>\n"; 
echo form_header(lang('base_settings'));
echo field_input('site', $info['Name'], lang('web_server_web_site_hostname'), TRUE);

// Upload information 
//-------------------

echo field_dropdown('group', $groups, $info['ShareGroup'], lang('groups_group'));

if ($ftp_available)
    echo field_toggle_enable_disable('ftp', $info['FtpEnabled'], lang('web_server_ftp_upload'));

if ($file_available)
    echo field_toggle_enable_disable('file', $info['FileEnabled'], lang('web_server_file_server_upload'));

// Footer
//-------

echo field_button_set($buttons);

echo form_footer();
echo form_close();
