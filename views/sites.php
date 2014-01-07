<?php

/**
 * Web sites summary view.
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
// Headers
///////////////////////////////////////////////////////////////////////////////

$headers = array(
    lang('web_server_web_site'),
    lang('web_server_upload_access'),
    lang('groups_group'),
);

///////////////////////////////////////////////////////////////////////////////
// Anchors
///////////////////////////////////////////////////////////////////////////////

if ($default_set)
    $anchors = array(anchor_add('/app/web_server/sites/add/'));
else
    $anchors = array(anchor_custom('/app/web_server/sites/add/default', lang('web_server_configure_default_web_site')));

///////////////////////////////////////////////////////////////////////////////
// Items
///////////////////////////////////////////////////////////////////////////////

foreach ($sites as $site => $info) {

    ///////////////////////////////////////////////////////////////////////////
    // Tweak buttons for default site
    ///////////////////////////////////////////////////////////////////////////

    $edit_link = $info['WebCustomConfiguration'] ? 'edit_custom' : 'edit';

    if ($info['WebDefaultSite']) {
        $detail_buttons = button_set(
            array(
                anchor_edit('/app/web_server/sites/' . $edit_link . '/' . $site, 'high'),
            )
        );
    } else {
        $detail_buttons = button_set(
            array(
                anchor_edit('/app/web_server/sites/' . $edit_link . '/' . $site, 'high'),
                anchor_delete('/app/web_server/sites/delete/' . $site, 'high')
            )
        );
    }

    ///////////////////////////////////////////////////////////////////////////
    // Item details
    ///////////////////////////////////////////////////////////////////////////

    // Add a default string
    $web_site = $info['Name'];

    if ($info['WebDefaultSite'])
        $web_site .= ' - ' . lang('base_default');

    $access = '';

    if ($info['FtpEnabled'])
        $access .= "<img src='" . clearos_app_htdocs('web_server') . "/icon_ftp.png' alt='FTP'>";

    if ($info['FileEnabled'])
        $access .= "<img src='" . clearos_app_htdocs('web_server') . "/icon_samba.png' alt='" . lang('base_file') . "'>";

    $item['title'] = $site;
    $item['action'] = '/app/web_server/sites/edit/' . $site;
    $item['anchors'] = $detail_buttons;
    $item['details'] = array(
        $web_site,
        $access,
        $info['ShareGroup'],
    );

    $items[] = $item;
}

///////////////////////////////////////////////////////////////////////////////
// Summary table
///////////////////////////////////////////////////////////////////////////////

echo summary_table(
    lang('web_server_web_sites'),
    $anchors,
    $headers,
    $items
);
