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

$this->lang->load('web_server');
$this->lang->load('groups');

///////////////////////////////////////////////////////////////////////////////
// Headers
///////////////////////////////////////////////////////////////////////////////

$headers = array(
    lang('web_server_web_site'),
    lang('web_server_access'),
    lang('groups_group'),
);

///////////////////////////////////////////////////////////////////////////////
// Anchors
///////////////////////////////////////////////////////////////////////////////

$anchors = array(anchor_add('/app/web_server/sites/add/'));

///////////////////////////////////////////////////////////////////////////////
// Items
///////////////////////////////////////////////////////////////////////////////

//echo "<pre>";
//print_r($sites);
foreach ($sites as $site => $info) {

    $ip = $entry['ip'];
    $hostname = $entry['hostname'];
    $alias = (count($entry['aliases']) > 0) ? $entry['aliases'][0] : '';
    
    // Add '...' to indicate more aliases exist
    if (count($entry['aliases']) > 1)
        $alias .= " ..."; 

    ///////////////////////////////////////////////////////////////////////////
    // Tweak buttons for default site
    ///////////////////////////////////////////////////////////////////////////

    $detail_buttons = button_set(
        array(
            anchor_edit('/app/web_server/sites/edit/' . $domain, 'high'),
            anchor_delete('/app/web_server/sites/delete/' . $domain, 'high')
        )
    );

    ///////////////////////////////////////////////////////////////////////////
    // Item details
    ///////////////////////////////////////////////////////////////////////////

    // Order sites with default first.
    $order_site = "<span style='display: none'>1</span>$site";

    $item['title'] = $site;
    $item['action'] = '/app/web_server/sites/edit/' . $site;
    $item['anchors'] = $detail_buttons;
    $item['details'] = array(
        $order_ip,
        $hostname,
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
