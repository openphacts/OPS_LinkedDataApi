<?php
define('MORIARTY_HTTP_CACHE_USE_STALE_ON_FAILURE', true);
define('MORIARTY_ALWAYS_CACHE_EVERYTHING', true);
// PUELIA CACHING
$useMemcache = getenv('USE_MEMCACHE', true) ?: getenv('USE_MEMCACHE');
if ($useMemcache && $useMemcache == 'TRUE') {
    define('PUELIA_SERVE_FROM_CACHE', true);
} else {
    define('PUELIA_SERVE_FROM_CACHE',false);
}
#define('PUELIA_SERVE_FROM_CACHE', true);
define("LOG_SELECT_QUERIES", 1);
define("LOG_VIEW_QUERIES", 1);
define('CACHE_ONE_DAY', (60*60*24*1));
define('CACHE_ONE_WEEK', (60*60*24*7));
define('CACHE_ONE_HOUR', (60*60));
define('CACHE_ONE_YEAR', time() + (60*60*24*7*52));
define('CACHE_OFF', 1);
define('PUELIA_CACHE_AGE', CACHE_ONE_YEAR);
// MEMCACHE HOST
$memcacheEndpointUri = getenv('MEMCACHE_ENDPOINT', true) ?: getenv('MEMCACHE_ENDPOINT');
if ($memcacheEndpointUri) {
    define('PUELIA_MEMCACHE_HOST', $memcacheEndpointUri);
} else {
    define('PUELIA_MEMCACHE_HOST', 'localhost');
}
//define('PUELIA_MEMCACHE_HOST', 'my-memcache');
define('PUELIA_MEMCACHE_PORT', '11211');
define ('CHEMSPIDER_ENDPOINT', 'https://chemistry.openphacts.org/api/JSON.ashx');
// IMS endpoint
$imsEndpointUri = getenv('IMS_ENDPOINT', true) ?: getenv('IMS_ENDPOINT');
if ($imsEndpointUri) {
    define('IMS_EXPAND_ENDPOINT', $imsEndpointUri . '/QueryExpander/expandXML?query=');
    define('IMS_MAP_ENDPOINT', $imsEndpointUri . '/QueryExpander/mapUriRDF');
} else {
    define('IMS_EXPAND_ENDPOINT', 'http://localhost:3004/QueryExpander/expandXML?query=');
    define('IMS_MAP_ENDPOINT', 'http://localhost:3004/QueryExpander/mapUriRDF');
}
// Get sparql endpoint from environment variable
$sparqlEndpointUri = getenv('OPS_SPARQL_ENDPOINT', true) ?: getenv('OPS_SPARQL_ENDPOINT');
if ($sparqlEndpointUri) {
    define('OPS_SPARQL_ENDPOINT', $sparqlEndpointUri);
} else {
    define('OPS_SPARQL_ENDPOINT', null);
}
?>
