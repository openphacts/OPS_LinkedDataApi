<?php
define('MORIARTY_HTTP_CACHE_USE_STALE_ON_FAILURE', true);
define('MORIARTY_ALWAYS_CACHE_EVERYTHING', true);
define('PUELIA_SERVE_FROM_CACHE', true);
define("LOG_SELECT_QUERIES", 1);
define("LOG_VIEW_QUERIES", 1);
define('CACHE_ONE_DAY', (60*60*24*1));
define('CACHE_ONE_WEEK', (60*60*24*7));
define('CACHE_ONE_HOUR', (60*60));
define('CACHE_ONE_YEAR', time() + (60*60*24*7*52));
define('CACHE_OFF', 0);
define('PUELIA_CACHE_AGE', CACHE_ONE_YEAR);
define('PUELIA_MEMCACHE_HOST', 'localhost');
define('PUELIA_MEMCACHE_PORT', '11211');
define ('IMS_EXPAND_ENDPOINT', 'http://ops-ims-13:8080/QueryExpander/expandXML?query=');
define ('CHEMSPIDER_ENDPOINT', 'http://ops.rsc.org/api/v1/JSON.ashx');
define ('IMS_MAP_ENDPOINT', 'http://ops-ims-13:8080/QueryExpander/mapBySetRDF')
?>
