
Name: app-web-server
Epoch: 1
Version: 1.0.10
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
The web server app provides an instance of the Apache web server.  This app can be used to create simple standalone websites or as part of a broader infrastructure to deploy web-based applications based on other technologies like PHP, MySQL, Javascript etc.

%package core
Summary: Web Server - APIs and install
License: LGPLv3
Group: ClearOS/Libraries
Requires: app-base-core
Requires: app-network-core
Requires: app-flexshare-core
Requires: httpd >= 2.2.15
Requires: mod_ssl

%description core
The web server app provides an instance of the Apache web server.  This app can be used to create simple standalone websites or as part of a broader infrastructure to deploy web-based applications based on other technologies like PHP, MySQL, Javascript etc.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/web_server
cp -r * %{buildroot}/usr/clearos/apps/web_server/

install -d -m 0755 %{buildroot}/var/clearos/httpd
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
%exclude /usr/clearos/apps/web_server/tests
%dir /usr/clearos/apps/web_server
%dir /var/clearos/httpd
/usr/clearos/apps/web_server/deploy
/usr/clearos/apps/web_server/language
/usr/clearos/apps/web_server/libraries
/var/clearos/base/daemon/httpd.php
