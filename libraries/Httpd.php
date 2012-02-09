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
     * Adds the default host.
     *
     * @param string $domain domain name
     *
     * @return void
     * @throws Validation_Exception, Engine_Exception
     */

    function add_default_host($domain)
    {
        clearos_profile(__METHOD__, __LINE__);
        $this->add_host($domain, self::FILE_DEFAULT);
    }

    /**
     * Adds a virtual host with defaults.
     *
     * @param string $domain domain name
     *
     * @return void
     * @throws Validation_Exception, Engine_Exception
     */

    function add_virtual_host($domain)
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->add_host($domain, "$domain.vhost");
    }

    /**
     * Generic add virtual host.
     *
     * @param string $domain domain name
     * @param string $confd  configuration file
     *
     * @return void
     * @throws Validation_Exception, Engine_Exception
     */

    function add_host($domain, $confd)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Validate
        //---------

        if (! $this->is_valid_domain($domain))
            throw new Validation_Exception(lang('web_website_invalid'));

        try {
            $config = new File(self::PATH_CONFD . "/$confd");
            if ($config->exists()) {
                throw new Validation_Exception(lang('web_website_exists'));
            }
        } catch (Validation_Exception $e) {
            throw new Validation_Exception(clearos_exception_message($e));
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        $docroot = self::PATH_VIRTUAL . "/$domain";
        $entry = "<VirtualHost *:80>\n";
        $entry .= "\tServerName $domain\n";
        $entry .= "\tServerAlias *.$domain\n";
        if ($confd == self::FILE_DEFAULT) {
            $entry .= "\tDocumentRoot /var/www/html\n";
            $entry .= "\tErrorLog /var/log/httpd/error_log\n";
            $entry .= "\tCustomLog /var/log/httpd/access_log combined\n";
        } else {
            $entry .= "\tDocumentRoot $docroot\n";
            $entry .= "\tErrorLog /var/log/httpd/" . $domain . "_error_log\n";
            $entry .= "\tCustomLog /var/log/httpd/" . $domain . "_access_log combined\n";
        }
        $entry .= "</VirtualHost>\n";

        try {
            $config->create('root', 'root', '0644');
            $config->add_lines($entry);
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        try {
            $webfolder = new Folder($docroot);
            if (! $webfolder->exists())
                $webfolder->create('root', 'root', '0775');
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        // Uncomment NameVirtualHost
        try {
            $httpcfg = new File(self::FILE_CONFIG);
            $match = $httpcfg->replace_lines("/^[#\s]*NameVirtualHost.*\*/", "NameVirtualHost *:80\n");
            if (! $match)
                $httpcfg->add_lines("NameVirtualHost *:80\n");
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        // Make sure our "Include conf.d/*.vhost" is still there
        try {
            $includeline = $httpcfg->LookupLine("/^Include\s+conf.d\/\*\.vhost/");
        } catch (File_No_Match_Exception $e) {
            $httpcfg->add_lines("Include conf.d/*.vhost\n");
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
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

    function delete_virtual_host($site)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Validate
        //---------

        Validation_Exception::is_valid($this->validate_site($site));

        $flexshare = new Flexshare();
        try {
            $share = $flexshare->get_share($site);
            // Check to see if Directory == docroot
            $conf = $this->get_site_info($site);
            if (trim($conf['docroot']) == trim($share['ShareDir'])) {
                // Default flag to *not* delete contents of dir
                $flexshare->delete_share($site, FALSE);
            }
        } catch (Flexshare_Not_Found_Exception $e) {
            //Ignore
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        try {
            $config = new File(self::PATH_CONFD . "/" . $site . ".vhost");
            $config->delete();
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
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
            if (preg_match("/\.vhost$/", $file))
                array_push($sites, preg_replace("/\.vhost$/", "", $file));
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

    function get_virtual_host_info($site)
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

    function set_default_host($site, $alias)
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
     * Sets parameters for a virtual host.
     *
     * @param string $site    site domain name
     * @param string $alias   alias name
     * @param string $docroot document root
     *
     * @return void
     * @throws Validation_Exception, Engine_Exception
     */

    function set_virtual_host($site, $alias, $docroot)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Validate
        //---------

        Validation_Exception::is_valid($this->validate_site($site));

        Validation_Exception::is_valid($this->validate_doc_root($docroot));

        // TODO validation

        try {
            $file = new File(self::PATH_CONFD . "/" . $site . ".vhost");
            $file->replace_lines("/^\s*ServerAlias/", "\tServerAlias $alias\n");
            $file->replace_lines("/^\s*DocumentRoot/", "\tDocumentRoot $docroot\n");
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Sets parameters for a site.
     *
     * @param string $site    web site
     * @param string $docroot document root
     * @param string $group   the group owner
     * @param string $ftp     FTP enabled status
     * @param string $smb     File (SAMBA) enabled status
     *
     * @return  void
     * @throws  Engine_Exception
     */

    function configure_upload_methods($site, $docroot, $group, $ftp, $smb)
    {
        clearos_profile(__METHOD__, __LINE__);
    
        if ($ftp && ! file_exists(COMMON_CORE_DIR . "/api/Proftpd.class.php"))
            return;

        if ($smb && ! file_exists(COMMON_CORE_DIR . "/api/Samba.class.php"))
            return;

        try {
            $flexshare = new Flexshare();
            try {
                if (!$ftp && !$smb) {
                    try {
                        $flexshare->get_share($site);
                        $flexshare->delete_share($site, FALSE);
                    } catch (Flexshare_Not_Found_Exception $e) {
                        // GetShare will trigger this exception on a virgin box
                        // TODO: implement Flexshare.exists($name) instead of this hack
                    } catch (Exception $e) {
                        throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
                    }
                    return;
                }
            } catch (Exception $e) {
                throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
            }
            try {
                $share = $flexshare->get_share($site);
            } catch (Flexshare_Not_Found_Exception $e) {
                $flexshare->add_share($site, lang('web_site') . " - " . $site, $group, TRUE);
                $flexshare->set_directory($site, self::PATH_DEFAULT);
                $share = $flexshare->get_share($site);
            } catch (Exception $e) {
                throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
            }
            // FTP
            // We check setting of some parameters so we can allow user override using Flexshare.
            if (!isset($share['FtpServerUrl']))
                $flexshare->set_ftp_server_url($site, $site);
            $flexshare->set_ftp_allow_passive($site, 1, Flexshare::FTP_PASV_MIN, Flexshare::FTP_PASV_MAX);
            if (!isset($share['FtpPort']))
                $flexshare->set_ftp_override_port($site, 0, Flexshare::DEFAULT_PORT_FTP);
            if (!isset($share['FtpReqSsl']))
                $flexshare->set_ftp_req_ssl($site, 0);
            $flexshare->set_ftp_req_auth($site, 1);
            $flexshare->set_ftp_allow_anonymous($site, 0);
            $flexshare->set_ftp_user_owner($site, NULL);
            //$flexshare->set_ftp_group_access($site, Array($group));
            if (!isset($share['FtpGroupGreeting']))
                $flexshare->set_ftp_group_greeting($site, lang('web_site') . ' - ' . $site);
            $flexshare->set_ftp_group_permission($site, Flexshare::PERMISSION_READ_WRITE_PLUS);
            $flexshare->set_ftp_group_umask($site, Array('owner'=>0, 'group'=>0, 'world'=>2));
            $flexshare->set_ftp_enabled($site, $ftp);
            // Samba
            $flexshare->set_file_comment($site, lang('web_site') . ' - ' . $site);
            $flexshare->set_file_public_access($site, 0);
            $flexshare->set_file_permission($site, Flexshare::PERMISSION_READ_WRITE);
            $flexshare->set_file_create_mask($site, Array('owner'=>6, 'group'=>6, 'world'=>4));
            $flexshare->set_file_enabled($site, $smb);
            $flexshare->set_file_browseable($site, 0);

            // Globals
            $flexshare->set_group($site, $group);
            $flexshare->set_directory($site, $docroot);
            $flexshare->toggle_share($site, ($ftp|$smb));

        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
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

    function is_valid_site($site)
    {
        // Allow underscores
        if (!preg_match("/^([0-9a-zA-Z\.\-_]+)$/", $site))
            return lang('web_site_invalid');
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
