<?php

/**
 * Httpd class.
 *
 * @category   apps
 * @package    web-server
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2014 ClearFoundation
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
use \clearos\apps\network\Hostname as Hostname;

clearos_load_library('base/Daemon');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('flexshare/Flexshare');
clearos_load_library('groups/Group_Factory');
clearos_load_library('network/Hostname');

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
 * @category   apps
 * @package    web-server
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2014 ClearFoundation
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
    const TYPE_WEB_APP = 'web_app';
    const TYPE_WEB_SITE = 'web_site';
    const TYPE_WEB_SITE_DEFAULT = 'web_site_default';

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
     * @param string $site    web site
     * @param string $aliases aliases
     * @param string $group   group owner
     * @param string $ftp     FTP enabled status
     * @param string $samba   file (Samba) enabled status
     * @param string $type    type of site
     * @param array  $options web server options
     *
     * @return void
     * @throws Validation_Exception, Engine_Exception
     */

    function add_site($site, $aliases, $group, $ftp, $samba, $type, $options = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Create docroot folder
        //----------------------

        if (($type === self::TYPE_WEB_SITE) || ($type === self::TYPE_WEB_SITE_DEFAULT)) {
            $docroot = ($type == self::TYPE_WEB_SITE_DEFAULT) ? self::PATH_DEFAULT : self::PATH_VIRTUAL . '/' . $site;

            $docroot_folder = new Folder($docroot);

            if (! $docroot_folder->exists())
                $docroot_folder->create('root', 'root', '0775');
        }

        // Add Flexshare
        //--------------

        $comment = lang('web_server_web_site') . ' - ' . $site;

        $flexshare = new Flexshare();

        $flexshare->add_share($site, $comment, $group, $docroot, Flexshare::TYPE_WEB_SITE);

        // Tweak httpd.conf for virtual site support
        //------------------------------------------

        $config = new File(self::FILE_CONFIG);
        $match = $config->replace_lines("/^[#\s]*NameVirtualHost.*\*/", "NameVirtualHost *:80\n");

        if (! $match)
            $config->add_lines("NameVirtualHost *:80\n");

        // Use set_site to do the rest
        //----------------------------

        $this->_set_core_info($site, $aliases, $group, $ftp, $samba, $type, $options);
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
        $flexshare->delete_share($site, FALSE);
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

    function get_site($site)
    {
        clearos_profile(__METHOD__, __LINE__);

        $flexshare = new Flexshare();

        return $flexshare->get_share($site);
    }

    /**
     * Returns a list of configured sites.
     *
     * @param string $type type (web site or web app)
     *
     * @return array list of sites
     *
     * @throws Engine_Exception
     */

    function get_sites($type = self::TYPE_WEB_SITE)
    {
        clearos_profile(__METHOD__, __LINE__);

        $flexshare = new Flexshare();

        if (($type === self::TYPE_WEB_SITE) || ($type == self::TYPE_WEB_SITE_DEFAULT))
            $flexshare_type = Flexshare::TYPE_WEB_SITE;
        elseif ($type === self::TYPE_WEB_APP)
            $flexshare_type = Flexshare::TYPE_WEB_APP;

        return $flexshare->get_shares($type);
    }

    /**
     * Initializes configuration install.
     *
     * @return void
     * @throws Engine_Exception
     */

    function initialize()
    {
        clearos_profile(__METHOD__, __LINE__);

        $hostname = new Hostname();
        $default = $hostname->get();

        $this->set_server_name($default);

        $file = new File(self::FILE_CONFIG, TRUE);

        $file->replace_lines_between('/^\s*Options\s+.*/i', '#   Options Indexes FollowSymLinks', '/^\s*<Directory\s+.\/var\/www\/html.>/i', '/^\s*<\/Directory>/');
        $file->replace_lines_between('/^\s*AllowOverride\s+.*/i', '#   AllowOverride None', '/^\s*<Directory\s+.\/var\/www\/html.>/i', '/^\s*<\/Directory>/');
    }

    /**
     * Check to see if site is default.
     *
     * @param string $site site
     *
     * @return boolean TRUE if default web site
     * @throws Engine_Exception
     */

    function is_default($site)
    {
        clearos_profile(__METHOD__, __LINE__);

        $flexshare = new Flexshare();

        try {
            $share = $flexshare->get_share($site);
        } catch (Flexshare_Not_Found_Exception $e) {
            return FALSE;
        }

        if (isset($share['WebDefaultSite']) && $share['WebDefaultSite'])
            return TRUE;
        else
            return FALSE;
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

        $flexshare = new Flexshare();

        $sites = $flexshare->get_shares(Flexshare::TYPE_WEB_SITE);

        $default_set = FALSE;

        foreach ($sites as $name => $site) {
            if ($site['WebDefaultSite'])
                $default_set = TRUE;
        }

        return $default_set;
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
     * @param string $site    web site
     * @param string $aliases aliases
     * @param string $group   group owner
     * @param string $ftp     FTP enabled status
     * @param string $samba   file enabled status
     * @param string $type    type of site
     * @param array  $options web server options
     *
     * @return  void
     * @throws  Engine_Exception
     */

    function set_site($site, $aliases, $group, $ftp, $samba, $type, $options = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        // The default share is a bit different.  /var/www/html does not 
        // change but the base domain might change (i.e. the Flexshare name)
        //------------------------------------------------------------------

        $flexshare = new Flexshare();

        if ($type === self::TYPE_WEB_SITE_DEFAULT) {
            $shares = $flexshare->get_shares(Flexshare::TYPE_WEB_SITE);

            foreach ($shares as $site_name => $details) {
                if (isset($details['WebDefaultSite']) && $details['WebDefaultSite']) {

                    $flexshare->delete_share($site_name, FALSE);
                    $comment = lang('web_server_web_site') . ' - ' . $site;
                    $this->add_site($site, $aliases, $group, $ftp, $samba, self::TYPE_WEB_SITE_DEFAULT, $options);
                    return;
                }
            }
        }

        $this->_set_core_info($site, $aliases, $group, $ftp, $samba, $type, $options);
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

    ///////////////////////////////////////////////////////////////////////////
    // P R I V A T E  M E T H O D S                                          //
    ///////////////////////////////////////////////////////////////////////////

    /**
     * Sets parameters for a site.
     *
     * @param string $site    site name
     * @param string $aliases aliases
     * @param string $group   group owner
     * @param string $ftp     FTP enabled status
     * @param string $samba   file enabled status
     * @param string $type    type of site
     * @param array  $options web server options
     *
     * @return  void
     * @throws  Engine_Exception
     */

    protected function _set_core_info($site, $aliases, $group, $ftp, $samba, $type, $options)
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

        // Set Flexshare
        //--------------

        $flexshare = new Flexshare();

        if (isset($options['comment']))
            $comment = $options['comment'];
        else
            $comment = lang('web_server_web_site') . ' - ' . $site;

        // FTP
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

        // Web and Options
        $flexshare->set_web_server_alias($site, $aliases);
        $flexshare->set_web_realm($site, $comment);

        $flexshare->set_web_server_name($site, $site);
        // FIXME: is the server_name option below used?

        if (isset($options['server_name']))
            $flexshare->set_web_server_name($site, $options['server_name']);

        if (isset($options['server_name_alternate']))
            $flexshare->set_web_server_name_alternate($site, $options['server_name_alternate']);

        if (isset($options['server_alias_alternate']))
            $flexshare->set_web_server_alias_alternate($site, $options['server_alias_alternate']);

        if (isset($options['directory_alias']))
            $flexshare->set_web_directory_alias($site, $options['directory_alias']);

        if (isset($options['directory_alias_alternate']))
            $flexshare->set_web_directory_alias_alternate($site, $options['directory_alias_alternate']);

        if (isset($options['web_access']))
            $flexshare->set_web_access($site, $options['web_access']);

        if (isset($options['require_authentication']))
            $flexshare->set_web_require_authentication($site, $options['require_authentication']);

        if (isset($options['require_ssl']))
            $flexshare->set_web_require_ssl($site, $options['require_ssl']);

        if (isset($options['show_index']))
            $flexshare->set_web_show_index($site, $options['show_index']);

        if (isset($options['follow_symlinks']))
            $flexshare->set_web_follow_symlinks($site, $options['follow_symlinks']);

        if (isset($options['ssi']))
            $flexshare->set_web_allow_ssi($site, $options['ssi']);

        if (isset($options['htaccess']))
            $flexshare->set_web_htaccess_override($site, $options['htaccess']);

        if (isset($options['php']))
            $flexshare->set_web_php($site, $options['php']);

        if (isset($options['cgi']))
            $flexshare->set_web_cgi($site, $options['cgi']);

        $flexshare->set_web_enabled($site, TRUE);

        // Globals
        $flexshare->set_group($site, $group);
        $flexshare->set_share_state($site, TRUE);
        $flexshare->update_share($site, TRUE);
    }
}

// vim: syntax=php ts=4
