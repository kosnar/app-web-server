<?php

/**
 * Web server site view.
 *
 * @category   ClearOS
 * @package    Web_Server
 * @subpackage Views
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
$this->lang->load('web_server');

///////////////////////////////////////////////////////////////////////////////
// Form handler
///////////////////////////////////////////////////////////////////////////////

if ($form_type === 'edit') {
    $read_only = FALSE;
    $buttons = array( 
        form_submit_update('submit'),
        anchor_cancel('/app/web_server/sites')
    );
} else if ($form_type === 'add') {
    $read_only = FALSE;
    $buttons = array( 
        form_submit_add('submit'),
        anchor_cancel('/app/web_server/sites')
    );
} else {
    $read_only = TRUE;
    $buttons = array( 
        anchor_cancel('/app/web_server/sites')
    );
}

///////////////////////////////////////////////////////////////////////////////
// Form
///////////////////////////////////////////////////////////////////////////////

echo form_open('web_server/settings/edit');
echo form_header(lang('base_settings'));

echo field_input('site', $site, lang('web_server_web_site'), $read_only);
echo field_input('aliases', $aliases, lang('web_server_aliases'), $read_only);
echo field_toggle_enable_disable('ftp', $ftp, lang('web_server_ftp_upload'), $read_only);
echo field_toggle_enable_disable('file', $file, lang('web_server_file_server_upload'), $read_only);
echo field_dropdown('group', $groups, $group, lang('web_server_upload_access'), $read_only);

echo field_button_set($buttons);

echo form_footer();
echo form_close();
