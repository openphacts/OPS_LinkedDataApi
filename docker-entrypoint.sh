#!/bin/bash
set -e

if [ ! -z "$CONCEPTWIKI" ] ; then
	echo Using ConceptWiki instance at $CONCEPTWIKI
	sed -i "s,http://[^/]*/web-ws/concept,$CONCEPTWIKI,g" /var/www/html/api-config-files/*ttl
fi

if [ ! -z "$CRS" ] ; then
	echo Using CRS instance at $CRS
	sed -i "s,http.*/JSON.ashx,$CRS/JSON.ashx,g" /var/www/html/api-config-files/*ttl /var/www/html/api-config-files/deployment.settings.php
fi

exec "$@"

