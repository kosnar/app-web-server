<?php

/**
 * Web sites summary view.
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

$anchors = array(anchor_add('/app/web_server/sites/add/'));

///////////////////////////////////////////////////////////////////////////////
// Items
///////////////////////////////////////////////////////////////////////////////

foreach ($sites as $site => $info) {

    ///////////////////////////////////////////////////////////////////////////
    // Tweak buttons for default site
    ///////////////////////////////////////////////////////////////////////////

    if ($site === 'default') {
        $detail_buttons = button_set(
            array(
                anchor_edit('/app/web_server/sites/edit/' . $site, 'high'),
            )
        );
    } else {
        $detail_buttons = button_set(
            array(
                anchor_edit('/app/web_server/sites/edit/' . $site, 'high'),
                anchor_delete('/app/web_server/sites/delete/' . $site, 'high')
            )
        );
    }

    ///////////////////////////////////////////////////////////////////////////
    // Item details
    ///////////////////////////////////////////////////////////////////////////

    // Order sites with default first.
    $order_site = "<span style='display: none'>1</span>$site";

    $access = '';

    if ($info['ftp'])
        $access .= "<img src='" . clearos_app_htdocs('flexshare') . "/icon_ftp.png' alt='FTP'>";

    if ($info['file'])
        $access .= "<img src='" . clearos_app_htdocs('flexshare') . "/icon_samba.png' alt='" . lang('base_file') . "'>";


    $item['title'] = $site;
    $item['action'] = '/app/web_server/sites/edit/' . $site;
    $item['anchors'] = $detail_buttons;
    $item['details'] = array(
        $site,
        $access,
        $info['group'],
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
