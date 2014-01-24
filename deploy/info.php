<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'web_server';
$app['version'] = '1.5.23';
$app['release'] = '1';
$app['vendor'] = 'ClearFoundation';
$app['packager'] = 'ClearFoundation';
$app['license'] = 'GPLv3';
$app['license_core'] = 'LGPLv3';
$app['description'] = lang('web_server_app_description');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('web_server_app_name');
$app['category'] = lang('base_category_server');
$app['subcategory'] = lang('base_subcategory_web');

/////////////////////////////////////////////////////////////////////////////
// Controllers
/////////////////////////////////////////////////////////////////////////////

$app['controllers']['web_server']['title'] = $app['name'];
$app['controllers']['settings']['title'] = lang('base_settings');
$app['controllers']['sites']['title'] = lang('web_server_web_sites');

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['requires'] = array(
    'app-accounts',
    'app-groups',
    'app-users',
    'app-network',
);

$app['core_requires'] = array(
    'app-network-core',
    'app-flexshare-core >= 1:1.5.22',
    'app-php-core >= 1:1.4.40',
    'httpd >= 2.2.15',
    'mod_authnz_external',
    'mod_authz_unixgroup',
    'mod_ssl',
    'pwauth',
    'syswatch >= 6.2.3',
);

$app['core_directory_manifest'] = array(
    '/var/clearos/httpd' => array(),
    '/var/www/virtual' => array()
);

$app['core_file_manifest'] = array(
    'httpd.php'=> array('target' => '/var/clearos/base/daemon/httpd.php'),
    'filewatch-web-server-configuration.conf'=> array('target' => '/etc/clearsync.d/filewatch-web-server-configuration.conf'),
);
