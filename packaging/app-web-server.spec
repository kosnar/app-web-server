
Name: app-web-server
Epoch: 1
Version: 1.6.0
Release: 1%{dist}
Summary: Web Server
License: GPLv3
Group: ClearOS/Apps
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = 1:%{version}-%{release}
Requires: app-base
Requires: app-accounts
Requires: app-groups
Requires: app-users
Requires: app-network

%description
The Web Server app can be used to create simple standalone web sites or as part of a broader infrastructure to deploy web-based applications using technologies like PHP, MySQL, and Javascript.

%package core
Summary: Web Server - Core
License: LGPLv3
Group: ClearOS/Libraries
Requires: app-base-core
Requires: app-network-core
Requires: app-flexshare-core >= 1:1.5.22
Requires: app-php-core >= 1:1.4.40
Requires: httpd >= 2.2.15
Requires: mod_authnz_external
Requires: mod_authz_unixgroup
Requires: mod_ssl
Requires: openssl >= 1.0.1e-16.el6_5.7
Requires: pwauth
Requires: syswatch >= 6.2.3

%description core
The Web Server app can be used to create simple standalone web sites or as part of a broader infrastructure to deploy web-based applications using technologies like PHP, MySQL, and Javascript.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/web_server
cp -r * %{buildroot}/usr/clearos/apps/web_server/

install -d -m 0755 %{buildroot}/var/clearos/httpd
install -d -m 0755 %{buildroot}/var/www/virtual
install -D -m 0644 packaging/filewatch-web-server-configuration.conf %{buildroot}/etc/clearsync.d/filewatch-web-server-configuration.conf
install -D -m 0644 packaging/httpd.php %{buildroot}/var/clearos/base/daemon/httpd.php

%post
logger -p local6.notice -t installer 'app-web-server - installing'

%post core
logger -p local6.notice -t installer 'app-web-server-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/web_server/deploy/install ] && /usr/clearos/apps/web_server/deploy/install
fi

[ -x /usr/clearos/apps/web_server/deploy/upgrade ] && /usr/clearos/apps/web_server/deploy/upgrade

exit 0

%preun
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-web-server - uninstalling'
fi

%preun core
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-web-server-core - uninstalling'
    [ -x /usr/clearos/apps/web_server/deploy/uninstall ] && /usr/clearos/apps/web_server/deploy/uninstall
fi

exit 0

%files
%defattr(-,root,root)
/usr/clearos/apps/web_server/controllers
/usr/clearos/apps/web_server/htdocs
/usr/clearos/apps/web_server/views

%files core
%defattr(-,root,root)
%exclude /usr/clearos/apps/web_server/packaging
%dir /usr/clearos/apps/web_server
%dir /var/clearos/httpd
%dir /var/www/virtual
/usr/clearos/apps/web_server/deploy
/usr/clearos/apps/web_server/language
/usr/clearos/apps/web_server/libraries
/etc/clearsync.d/filewatch-web-server-configuration.conf
/var/clearos/base/daemon/httpd.php
