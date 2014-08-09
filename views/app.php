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
$this->lang->load('flexshare');
$this->lang->load('network');
$this->lang->load('web_server');

///////////////////////////////////////////////////////////////////////////////
// Form handler
///////////////////////////////////////////////////////////////////////////////

if ($form_type === 'edit') {
    $read_only = FALSE;
    $form = $app_name . '/webapp/edit/' . $site;
    $buttons = array( 
        form_submit_update('submit'),
        anchor_cancel('/app/' . $app_name)
    );
} else {
    $read_only = TRUE;
    $form = $app_name . '/webapp/';
    $buttons = array( 
        anchor_edit('/app/' . $app_name . '/webapp/edit')
    );
}

///////////////////////////////////////////////////////////////////////////////
// Form
///////////////////////////////////////////////////////////////////////////////

echo form_open($form);
echo form_header(lang('web_server_web_application_settings'));

// General information 
//--------------------

echo fieldset_header(lang('web_server_live_site'));
echo field_input('server_name', $info['WebServerName'], lang('network_hostname'), $read_only);
echo field_input('server_alias', $info['WebServerAlias'], lang('web_server_hostname_aliases'), $read_only);
echo field_input('directory_alias', $info['WebDirectoryAlias'], lang('web_server_directory_alias'), $read_only);
echo fieldset_footer();

echo fieldset_header(lang('web_server_test_site'));
echo field_input('server_name_alternate', $info['WebServerNameAlternate'], lang('network_hostname'), $read_only);
echo field_input('server_alias_alternate', $info['WebServerAliasAlternate'], lang('web_server_hostname_aliases'), $read_only);
echo field_input('directory_alias_alternate', $info['WebDirectoryAliasAlternate'], lang('web_server_directory_alias'), $read_only);
echo fieldset_footer();


// Upload information 
//-------------------

echo fieldset_header(lang('web_server_upload_access'));
echo field_dropdown('group', $groups, $info['ShareGroup'], lang('groups_group'), $read_only);

if ($ftp_available)
    echo field_toggle_enable_disable('ftp', $info['FtpEnabled'], lang('web_server_ftp_upload'), $read_only);

if ($file_available)
    echo field_toggle_enable_disable('file', $info['FileEnabled'], lang('web_server_file_server_upload'), $read_only);

echo fieldset_footer();

// options
//--------

echo fieldset_header(lang('flexshare_options'));
echo field_dropdown('web_access', $accessibility_options, $info['WebAccess'], lang('flexshare_web_accessibility'), $read_only);
echo field_toggle_enable_disable('require_authentication', $info['WebReqAuth'], lang('flexshare_web_require_authentication'), $read_only);
echo field_toggle_enable_disable('show_index', $info['WebShowIndex'], lang('flexshare_web_show_index'), $read_only);
echo field_toggle_enable_disable('follow_symlinks', $info['WebFollowSymLinks'], lang('flexshare_web_follow_symlinks'), $read_only);
echo field_toggle_enable_disable('ssi', $info['WebAllowSSI'], lang('flexshare_web_allow_ssi'), $read_only);
echo field_toggle_enable_disable('htaccess', $info['WebHtaccessOverride'], lang('flexshare_web_allow_htaccess'), $read_only);
echo field_toggle_enable_disable('php', $info['WebPhp'], lang('flexshare_web_enable_php'), $read_only);
echo field_toggle_enable_disable('cgi', $info['WebCgi'], lang('flexshare_web_enable_cgi'), $read_only);
echo fieldset_footer();

// Footer
//-------

echo field_button_set($buttons);

echo form_footer();
echo form_close();
