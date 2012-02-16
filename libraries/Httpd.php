<?php

/**
 * Httpd class.
 *
 * @category   Apps
 * @package    Httpd
 * @subpackage Libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2011 ClearFoundation
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

clearos_load_library('base/Daemon');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('flexshare/Flexshare');

// Exceptions
//-----------

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
 * @package    Httpd
 * @subpackage Libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2011 ClearFoundation
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
    const FILE_DEFAULT = 'default.conf';

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Httpd constructor.
     */

    function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    /**
     * Adds web site.
     *
     * @param string $site       web site
     * @param string $aliases    aliases
     * @param string $group      group owner
     * @param string $ftp        FTP enabled status
     * @param string $smb        file (Samba) enabled status
     * @param string $is_default set to TRUE if this is the default site
     *
     * @return void
     * @throws Validation_Exception, Engine_Exception
     */

    function add_site($site, $aliases, $group, $ftp, $smb, $is_default)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_site($site));

        if ($is_default) {
            $confd = self::FILE_DEFAULT;
            $docroot = self::PATH_DEFAULT;
            $log_prefix = '';
        } else {
            $confd = $site . '.vhost';
            $docroot = self::PATH_VIRTUAL . '/' . $site;
            $log_prefix = $site . '_';
        }

        $config = new File(self::PATH_CONFD . '/' . $confd);

        if ($config->exists())
            throw new Validation_Exception(lang('web_server_website_exists'));

        // Create configlet
        //-----------------

        $entry = "<VirtualHost *:80>\n";
        $entry .= "\tServerName $site\n";
        $entry .= "\tServerAlias *.$site\n";
        $entry .= "\tDocumentRoot $docroot\n";
        $entry .= "\tErrorLog /var/log/httpd/" . $log_prefix . "error_log\n";
        $entry .= "\tCustomLog /var/log/httpd/" . $log_prefix . "access_log combined\n";
        $entry .= "</VirtualHost>\n";

        $config->create('root', 'root', '0644');
        $config->add_lines($entry);

        // Create docroot folder
        //----------------------

        $docroot_folder = new Folder($docroot);

        if (! $docroot_folder->exists())
            $docroot_folder->create('root', 'root', '0775');

        // Tweak httpd.conf for virtual site support
        //------------------------------------------

        $httpcfg = new File(self::FILE_CONFIG);
        $match = $httpcfg->replace_lines("/^[#\s]*NameVirtualHost.*\*/", "NameVirtualHost *:80\n");

        if (! $match)
            $httpcfg->add_lines("NameVirtualHost *:80\n");

        // Make sure our "Include conf.d/*.vhost" is still there
        try {
            $includeline = $httpcfg->lookup_line("/^Include\s+conf.d\/\*\.vhost/");
        } catch (File_No_Match_Exception $e) {
            $httpcfg->add_lines("Include conf.d/*.vhost\n");
        }

        // Use set_size to do the rest
        //----------------------------

        $this->set_site($site, $aliases, $group, $ftp, $smb, $is_default);
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

        try {
            $flexshare = new Flexshare();
            $share = $flexshare->get_share($site);

            // Check to see if Directory == docroot
            $conf = $this->get_site_info($site);
            if (trim($conf['docroot']) == trim($share['ShareDir'])) {
                // Default flag to *not* delete contents of dir
                $flexshare->delete_share($site, FALSE);
            }
        } catch (Flexshare_Not_Found_Exception $e) {
            // Ignore
        }

        $config = new File(self::PATH_CONFD . "/" . $site . ".vhost");
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

        $confd = ($site === 'default') ? 'default.conf' : "$site.vhost";

        $info = array();

        $file = new File(self::PATH_CONFD . "/$confd");
        $lines = $file->get_contents_as_array();
        $count = 0;

        foreach ($lines as $line) {
            $result = preg_split('/\s+/', trim($line));

            if ($result[0] == 'ServerAlias') {
                $info['aliases'] = $result[1];
                $count++;
            } else if ($result[0] == 'DocumentRoot') {
                $info['document_root'] = $result[1];
                $count++;
            } else if ($result[0] == 'ServerName') {
                $info['server_name'] = $result[1];
                $count++;
            } else if ($result[0] == 'ErrorLog') {
                $info['error_log'] = $result[1];
                $count++;
            } else if ($result[0] == 'CustomLog') {
                $info['custom_log'] = $result[1];
                $count++;
            }
        }

        if ($count < 5)
            throw new Engine_Exception(lang('base_something_unexpected_happened'));

        // Get Flexshare access
        //---------------------

        $flexshare = new Flexshare();

        try {
            $share = $flexshare->get_share($site);
        } catch (Flexshare_Not_Found_Exception $e) {
            $info['ftp'] = FALSE;
            $info['file'] = FALSE;
            $info['group'] = '';
            return;
        }

        $info['ftp'] = $flexshare->get_ftp_state($site);
        $info['file'] = $flexshare->get_file_state($site);
        $info['group'] = $flexshare->get_group($site);

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
            if (preg_match("/\.vhost$/", $file)) {
                $site = preg_replace('/\.vhost$/', '', $file);
                $info = $this->get_site_info($site);
                $sites[$site] = $info;
            }
        }

        $sites['default'] = $this->get_default_site_info();

        return $sites;
    }

    /**
     * Returns the default site information.
     *
     * @return array default site information
     * @throws Engine_Exception
     */

    function get_default_site_info()
    {
        clearos_profile(__METHOD__, __LINE__);

        return $this->get_site_info('default');
    }

    /**
     * Returns the site informatio for the given site.
     *
     * @param string $site web site
     *
     * @return array site information
     * @throws Validation_Exception, Engine_Exception
     */

    function get_virtual_site_info($site)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_site($site));

        return $this->get_site_info($site);
    }

    /**
     * Sets parameters for a virtual host.
     *
     * @param string $site  web site
     * @param string $alias alias name
     *
     * @return void
     * @throws Validation_Exception, Engine_Exception
     */

    function set_default_site($site, $alias)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Validate
        //---------

        Validation_Exception::is_valid($this->validate_site($site));

        try {
            $file = new File(self::PATH_CONFD . "/" . self::FILE_DEFAULT);
            $file->replace_lines("/^\s*ServerName/", "\tServerName $site\n");
            $file->replace_lines("/^\s*ServerAlias/", "\tServerAlias $alias\n");
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        try {
            $file = new File($filename);
            $file->replace_lines("/^\s*ServerName/", "ServerName $site\n");
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
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
     * @param string $group   the group owner
     * @param string $ftp     FTP enabled status
     * @param string $smb     File (SAMBA) enabled status
     * @param string $is_default set to TRUE if this is the default site
     *
     * @return  void
     * @throws  Engine_Exception
     */

    function set_site($site, $aliases, $group, $ftp, $smb, $is_default)
    {
        clearos_profile(__METHOD__, __LINE__);
    
        Validation_Exception::is_valid($this->validate_site($site));
        // TODO validation

        // Throw exception if FTP/Samba is requested, but not installed
        if ($ftp && !clearos_library_installed('ftp/ProFTPd'))
            throw new Validation_Exception('web_server_ftp_upload_is_not_available');

        if ($smb && !clearos_library_installed('samba/Samba'))
            throw new Validation_Exception('web_server_file_upload_is_not_available');

        // Set variables for default/virtual situation
        //--------------------------------------------

        if ($is_default) {
            $confd = self::FILE_DEFAULT;
            $docroot = self::PATH_DEFAULT;
        } else {
            $confd = $site . '.vhost';
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
            $flexshare->add_share($site, $comment, $group, TRUE);
            $flexshare->set_directory($site, self::PATH_DEFAULT);
            $share = $flexshare->get_share($site);
        }

        // FTP
        $flexshare->set_ftp_server_url($site, $site);
        $flexshare->set_ftp_allow_passive($site, 1, Flexshare::FTP_PASV_MIN, Flexshare::FTP_PASV_MAX);
        $flexshare->set_ftp_override_port($site, 0, Flexshare::DEFAULT_PORT_FTP);
        // FIXME $flexshare->set_ftp_req_ssl($site, 0);
        $flexshare->set_ftp_group_greeting($site, $comment);
        $flexshare->set_ftp_group_permission($site, Flexshare::PERMISSION_READ_WRITE_PLUS);
        $flexshare->set_ftp_enabled($site, $ftp);

        // Samba
        $flexshare->set_file_comment($site, $comment);
        $flexshare->set_file_permission($site, Flexshare::PERMISSION_READ_WRITE);
        $flexshare->set_file_browseable($site, 1);
        $flexshare->set_file_enabled($site, $smb);

        // Globals
        $flexshare->set_group($site, $group);
        $flexshare->set_directory($site, $docroot);
        $flexshare->set_share_state($site, TRUE);
    }

    ///////////////////////////////////////////////////////////////////////////
    // V A L I D A T I O N   R O U T I N E S                                 //
    ///////////////////////////////////////////////////////////////////////////

    /**
     * Validation routine for checking state of default site.
     *
     * @return mixed void if state is valid, errmsg otherwise
     */

    function is_default_set()
    {
        clearos_profile(__METHOD__, __LINE__);
        $file = new File(self::PATH_CONFD . "/" . self::FILE_DEFAULT);
        if (!$file->exists()) {
            // Need file class for lang
            $file = new File();
            $filename = self::PATH_CONFD . "/" . self::FILE_DEFAULT;
            return lang('base_exception_file_not_found') . ' (' . $filename . ')';
        }
    }

    /**
     * Validation routine for site.
     *
     * @param string $site site
     *
     * @return mixed void if site is valid, errmsg otherwise
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

    /**
     * Validation routine for docroot.
     *
     * @param string $docroot document root
     *
     * @return boolean TRUE if document root is valid
     */

    function is_valid_doc_root($docroot)
    {
        // Allow underscores
        if (!isset($docroot) || !$docroot || $docroot == '')
            return lang('web_docroot_invalid');
        $folder = new Folder($docroot);
        if (! $folder->exists())
            return lang('base_exception_folder_not_found') . ' (' . $docroot . ')';
    }
}

// vim: syntax=php ts=4
