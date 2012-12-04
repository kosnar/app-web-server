<?php

/**
 * Httpd class.
 *
 * @category   Apps
 * @package    Web_Server
 * @subpackage Libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2012 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/httpd/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\web_server;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('web_server');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\base\Daemon as Daemon;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\flexshare\Flexshare as Flexshare;
use \clearos\apps\groups\Group_Factory as Group_Factory;

clearos_load_library('base/Daemon');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('flexshare/Flexshare');
clearos_load_library('groups/Group_Factory');

// Exceptions
//-----------

use \Exception as Exception;
use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\File_No_Match_Exception as File_No_Match_Exception;
use \clearos\apps\base\Validation_Exception as Validation_Exception;
use \clearos\apps\flexshare\Flexshare_Not_Found_Exception as Flexshare_Not_Found_Exception;

clearos_load_library('base/Engine_Exception');
clearos_load_library('base/File_No_Match_Exception');
clearos_load_library('base/Validation_Exception');
clearos_load_library('flexshare/Flexshare_Not_Found_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Httpd class.
 *
 * @category   Apps
 * @package    Web_Server
 * @subpackage Libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2012 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/httpd/
 */

class Httpd extends Daemon
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const PATH_CONFD  = '/etc/httpd/conf.d';
    const PATH_DEFAULT = '/var/www/html';
    const PATH_VIRTUAL = '/var/www/virtual';
    const FILE_CONFIG = '/etc/httpd/conf/httpd.conf';
    const FILE_DEFAULT = 'clearos.default.conf';
    const FILE_PREFIX = 'virtual.';

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Httpd constructor.
     */

    function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);

        parent::__construct('httpd');
    }

    /**
     * Adds web site.
     *
     * @param string $site       web site
     * @param string $aliases    aliases
     * @param string $group      group owner
     * @param string $ftp        FTP enabled status
     * @param string $samba      file (Samba) enabled status
     * @param string $is_default set to TRUE if this is the default site
     *
     * @return void
     * @throws Validation_Exception, Engine_Exception
     */

    function add_site($site, $aliases, $group, $ftp, $samba, $is_default)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_site($site));

        if ($is_default) {
            $confd = self::FILE_DEFAULT;
            $docroot = self::PATH_DEFAULT;
            $log_prefix = '';
        } else {
            $confd = self::FILE_PREFIX . $site . '.conf';
            $docroot = self::PATH_VIRTUAL . '/' . $site;
            $log_prefix = $site . '_';
        }

        $configlet = new File(self::PATH_CONFD . '/' . $confd);

        if ($configlet->exists())
            throw new Validation_Exception(lang('web_server_web_site_exists'));

        // Create configlet
        //-----------------

        $entry = "<VirtualHost *:80>\n";
        $entry .= "\tServerName $site\n";
        $entry .= "\tServerAlias *.$site\n";
        $entry .= "\tDocumentRoot $docroot\n";
        $entry .= "\tErrorLog /var/log/httpd/" . $log_prefix . "error_log\n";
        $entry .= "\tCustomLog /var/log/httpd/" . $log_prefix . "access_log combined\n";
        $entry .= "</VirtualHost>\n";

        $configlet->create('root', 'root', '0644');
        $configlet->add_lines($entry);

        // Create docroot folder
        //----------------------

        $docroot_folder = new Folder($docroot);

        if (! $docroot_folder->exists())
            $docroot_folder->create('root', 'root', '0775');

        // Tweak httpd.conf for virtual site support
        //------------------------------------------

        $config = new File(self::FILE_CONFIG);
        $match = $config->replace_lines("/^[#\s]*NameVirtualHost.*\*/", "NameVirtualHost *:80\n");

        if (! $match)
            $config->add_lines("NameVirtualHost *:80\n");

        // Use set_site to do the rest
        //----------------------------

        $this->set_site($site, $aliases, $group, $ftp, $samba, $is_default);
    }

    /**
     * Deletes a web site.
     *
     * @param string $site site
     *
     * @return void
     *
     * @throws Validation_Exception, Engine_Exception
     */

    function delete_site($site)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_site($site));

        $flexshare = new Flexshare();

        try {
            $share = $flexshare->get_share($site);
            $conf = $this->get_site_info($site);
        } catch (Flexshare_Not_Found_Exception $e) {
            // Not fatal
        }

        try {
            // Check to see if Directory == docroot
            if (trim($conf['document_root']) === trim($share['ShareDir']))
                $flexshare->delete_share($site, FALSE);
        } catch (Exception $e) {
            // Keep going with cleanup
        }

        $config = new File(self::PATH_CONFD . '/' . self::FILE_PREFIX . $site . '.conf');
        $config->delete();
    }

    /**
     * Gets server name (ServerName).
     *
     * @return string server name
     *
     * @throws Engine_Exception
     */

    function get_server_name()
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $file = new File(self::FILE_CONFIG);
            $retval = $file->lookup_value("/^ServerName\s+/i");
        } catch (File_No_Match_Exception $e) {
            return "";
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
        return $retval;
    }

    /**
     * Returns configuration information for a given site.
     *
     * @param string $site web site
     *
     * @return array settings for a given host
     * @throws Engine_Exception
     */

    function get_site_info($site)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Get Apache configuration settings
        //----------------------------------

        $confd = ($site === 'default') ? self::FILE_DEFAULT : self::FILE_PREFIX . $site . '.conf';

        $info = array();

        $file = new File(self::PATH_CONFD . '/' . $confd);
        $lines = $file->get_contents_as_array();
        $count = 0;

        foreach ($lines as $line) {
            $result = preg_split('/\s+/', trim($line), 2);

            if ($result[0] == 'ServerAlias')
                $info['aliases'] = $result[1];
            else if ($result[0] == 'DocumentRoot')
                $info['document_root'] = $result[1];
            else if ($result[0] == 'ServerName')
                $info['server_name'] = $result[1];
            else if ($result[0] == 'ErrorLog')
                $info['error_log'] = $result[1];
            else if ($result[0] == 'CustomLog')
                $info['custom_log'] = $result[1];
        }

        // Get Flexshare access
        //---------------------

        $flexshare = new Flexshare();

        $flexshare_name = ($site === 'default') ? $info['server_name'] : $site;

        try {
            $share = $flexshare->get_share($flexshare_name);
            $info['ftp'] = $flexshare->get_ftp_state($flexshare_name);
            $info['file'] = $flexshare->get_file_state($flexshare_name);
            $info['group'] = $flexshare->get_group($flexshare_name);
        } catch (Flexshare_Not_Found_Exception $e) {
            $info['ftp'] = FALSE;
            $info['file'] = FALSE;
            $info['group'] = '';
        }

        return $info;
    }

    /**
     * Returns a list of configured sites.
     *
     * The default site is keyed as 'default' in the array.
     *
     * @return array list of sites
     *
     * @throws Engine_Exception
     */

    function get_sites()
    {
        clearos_profile(__METHOD__, __LINE__);

        $folder = new Folder(self::PATH_CONFD);
        $files = $folder->get_listing();
        $sites = array();

        foreach ($files as $file) {
            if ($file === self::FILE_DEFAULT) {
                $sites['default'] = $this->get_site_info('default');
            } else if (preg_match("/^" . self::FILE_PREFIX . ".*\.conf$/", $file)) {
                $site = preg_replace('/^' . self::FILE_PREFIX . '/', '', $file);
                $site = preg_replace('/\.conf$/', '', $site);
                $info = $this->get_site_info($site);
                $sites[$site] = $info;
            }
        }

        return $sites;
    }

    /**
     * Returns state of default web site configuration.
     *
     * @return boolean TRUE if default web site is configured
     * @throws Engine_Exception
     */

    function is_default_set()
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new File(self::FILE_DEFAULT);

        if ($file->exists())
            return TRUE;
        else
            return FALSE;
    }

    /**
     * Sets server name.
     *
     * @param string $server_name server name
     *
     * @return array settings for a given host
     * @throws Validation_Exception, Engine_Exception
     */

    function set_server_name($server_name)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Validate
        //---------

        Validation_Exception::is_valid($this->validate_server_name($server_name));

        // Update tag if it exists
        //------------------------

        $file = new File(self::FILE_CONFIG);
        $match = $file->replace_lines("/^\s*ServerName/i", "ServerName $server_name\n");

        // If tag does not exist, add it
        //------------------------------

        if (! $match) {
            $match = $file->replace_lines("/^#ServerName/i", "ServerName $server_name\n");

            if (! $match) 
                $file->add_lines_after("ServerName $server_name\n", "/^[^#]/");
        }
    }

    /**
     * Sets parameters for a site.
     *
     * @param string $site       web site
     * @param string $aliases    aliases
     * @param string $group      the group owner
     * @param string $ftp        FTP enabled status
     * @param string $samba      file (SAMBA) enabled status
     * @param string $is_default set to TRUE if this is the default site
     *
     * @return  void
     * @throws  Engine_Exception
     */

    function set_site($site, $aliases, $group, $ftp, $samba, $is_default)
    {
        clearos_profile(__METHOD__, __LINE__);
    
        Validation_Exception::is_valid($this->validate_site($site));
        Validation_Exception::is_valid($this->validate_aliases($aliases));
        Validation_Exception::is_valid($this->validate_group($group));
        Validation_Exception::is_valid($this->validate_ftp_state($ftp));
        Validation_Exception::is_valid($this->validate_file_state($samba));

        if ($ftp && !clearos_library_installed('ftp/ProFTPd'))
            throw new Validation_Exception('web_server_ftp_upload_is_not_available');

        if ($samba && !clearos_library_installed('samba_common/Samba'))
            throw new Validation_Exception('web_server_file_upload_is_not_available');

        // Set variables for default/virtual situation
        //--------------------------------------------

        if ($is_default) {
            $confd = self::FILE_DEFAULT;
            $docroot = self::PATH_DEFAULT;
        } else {
            $confd = self::FILE_PREFIX . $site . '.conf';
            $docroot = self::PATH_VIRTUAL . '/' . $site;
        }

        // Set the server aliases
        //-----------------------

        $file = new File(self::PATH_CONFD . '/' . $confd);
        $file->replace_lines("/^\s*ServerAlias/", "\tServerAlias $aliases\n");

        // Set Flexshare access
        //---------------------

        $flexshare = new Flexshare();

        $comment = lang('web_server_web_site') . ' - ' . $site;

        try {
            $share = $flexshare->get_share($site);
        } catch (Flexshare_Not_Found_Exception $e) {
            $flexshare->add_share($site, $comment, $group, self::PATH_DEFAULT, TRUE);
            $share = $flexshare->get_share($site);
        }

        // FTP
        $flexshare->set_ftp_server_url($site, $site);
        $flexshare->set_ftp_allow_passive($site, 1, Flexshare::FTP_PASV_MIN, Flexshare::FTP_PASV_MAX);
        $flexshare->set_ftp_override_port($site, 0, Flexshare::DEFAULT_PORT_FTP);
        $flexshare->set_ftp_group_greeting($site, $comment);
        $flexshare->set_ftp_group_permission($site, Flexshare::PERMISSION_READ_WRITE_PLUS);
        $flexshare->set_ftp_enabled($site, $ftp);

        // Samba
        $flexshare->set_file_comment($site, $comment);
        $flexshare->set_file_permission($site, Flexshare::PERMISSION_READ_WRITE);
        $flexshare->set_file_browseable($site, 1);
        $flexshare->set_file_enabled($site, $samba);

        // Globals
        $flexshare->set_group($site, $group);
        $flexshare->set_directory($site, $docroot);
        $flexshare->set_share_state($site, TRUE);
    }

    ///////////////////////////////////////////////////////////////////////////
    // V A L I D A T I O N   R O U T I N E S                                 //
    ///////////////////////////////////////////////////////////////////////////

    /**
     * Validation routine for aliases.
     *
     * @param string $aliases aliases
     *
     * @return error message if aliases is invalid
     */

    function validate_aliases($aliases)
    {
        if ($aliases && (!preg_match("/^([0-9a-zA-Z\.\-_ ,\*]+)$/", $aliases)))
            return lang('web_server_aliases_invalid');
    }

    /**
     * Validation routine for flle server state.
     *
     * @param string $state state
     *
     * @return error message if file server state is invalid
     */

    function validate_file_state($state)
    {
        if (! clearos_is_valid_boolean($state))
            return lang('web_server_file_server_state_invalid');
    }

    /**
     * Validation routine for FTP state.
     *
     * @param string $state state
     *
     * @return error message if FTP state is invalid
     */

    function validate_ftp_state($state)
    {
        if (! clearos_is_valid_boolean($state))
            return lang('web_server_ftp_state_invalid');
    }

    /**
     * Validation routine for group.
     *
     * @param string $group_name group name
     *
     * @return error message if group is invalid
     */

    function validate_group($group_name)
    {
        $group = Group_Factory::create($group_name);

        if (! $group->exists())
            return lang('web_server_group_invalid');
    }

    /**
     * Validation routine for site.
     *
     * @param string $site site
     *
     * @return error message if site is invalid
     */

    function validate_site($site)
    {
        // Allow underscores
        if (!preg_match("/^([0-9a-zA-Z\.\-_]+)$/", $site))
            return lang('web_server_site_invalid');
    }

    /**
     * Validation routine for server_name
     *
     * @param string $server_name server name
     *
     * @return string error message if server name is invalid
     */

    function validate_server_name($server_name)
    {
        if (!preg_match("/^[A-Za-z0-9\.\-_]+$/", $server_name))
            return lang('web_server_server_name_invalid');
    }
}

// vim: syntax=php ts=4
