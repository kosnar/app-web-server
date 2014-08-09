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

$anchors = array();

///////////////////////////////////////////////////////////////////////////////
// Items
///////////////////////////////////////////////////////////////////////////////

foreach ($webapps as $site => $info) {

    $access = '';

    if ($info['FtpEnabled'])
        $access .= "<img src='" . clearos_app_htdocs('web_server') . "/icon_ftp.png' alt='FTP'>";

    if ($info['FileEnabled'])
        $access .= "<img src='" . clearos_app_htdocs('web_server') . "/icon_samba.png' alt='" . lang('base_file') . "'>";

    $item['title'] = $site;
    $item['action'] = '/app/web_server/sites/edit/' . $site;
    $item['anchors'] = $detail_buttons;
    $item['details'] = array(
        $info['ShareDescription'],
        $access,
        $info['ShareGroup'],
    );

    $items[] = $item;
}

///////////////////////////////////////////////////////////////////////////////
// Summary table
///////////////////////////////////////////////////////////////////////////////

$options['no_action'] = TRUE;

echo summary_table(
    lang('web_server_web_applications'),
    $anchors,
    $headers,
    $items,
    $options
);
