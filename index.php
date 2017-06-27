<?php
ini_set('memory_limit', '3072M');

require 'deployment.settings.php';
require_once 'lda.inc.php';
require 'setup.php';
require_once 'lda-cache.class.php';
require_once 'lda-request.class.php';
require_once 'lda-response.class.php';
require_once 'graphs/configgraph.class.php';
require_once 'responses/Response304.class.php';
Logger::configure("puelia.logging.properties");

LinkedDataApiRequest::eliminateDebugParams();

$HttpRequestFactory = new HttpRequestFactory();

//if(function_exists('memcache_connect')){
//  $MemCacheObject = new LinkedDataApiCache();
//  $HttpRequestFactory->set_cache($MemCacheObject);
//}
$Request = new LinkedDataApiRequest();
header("Access-Control-Allow-Origin: *");

define("CONFIG_PATH", '/api-config');
define("CONFIG_URL", $Request->getBaseAndSubDir().CONFIG_PATH);
logDebug("Request URI: ".$Request->getUri());
if(rtrim($Request->getPath(), '/')==$Request->getInstallSubDir()){
	header("Location: ".CONFIG_URL, true, 303);
	exit;
}


if ("/swagger" ==  $Request->getPathWithoutVersionAndExtension()) {
        $swagger = file_get_contents("api-config-files/swagger.json", true);
        $json = json_decode($swagger, true);
        $base = preg_replace(",(.*)/[^/]*$,", '$1/', $Request->getUri());
	if (isset($_SERVER["HTTP_X_3SCALE_PROXY_SECRET_TOKEN"])) {
            $base = str_replace("http://", "https://", $base);
        }
        $json["basePath"] = $base;

//phpinfo();
        header("Content-Type: application/json");
        echo json_encode($json); // JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
}


if (
    defined("PUELIA_SERVE_FROM_CACHE")
        AND
    PUELIA_SERVE_FROM_CACHE
        AND
    !$Request->hasNoCacheHeader()
        AND
    $cachedResponse = LinkedDataApiCache::hasCachedResponse($Request)
    )
{
	logDebug("Found cached response");
	if (isset($Request->ifNoneMatch) && $cachedResponse->eTag == $Request->ifNoneMatch)
	{
		logDebug("ETag matched, returning 304");
		$Response = new Response304($cachedResponse);
	}
	else if (isset($Request->ifModifiedSince) && $cachedResponse->generatedTime <= $Request->ifModifiedSince)
	{
		logDebug("Last modified date matched, returning 304");
		$Response = new Response304($cachedResponse);
	}
	else
	{
		logDebug("Re-Serving cached response");
		$Response = $cachedResponse;
	}
}
else
{
	if (!(defined("PUELIA_SERVE_FROM_CACHE") AND PUELIA_SERVE_FROM_CACHE
                                    AND !$Request->hasNoCacheHeader())){
        	$HttpRequestFactory->read_from_cache(FALSE);
    	}

	logDebug("Generating fresh response");

  $files = glob('api-config-files/*.ttl');
  /* if there is a config file named after the current domain, use only that */
    $domainBasedConfigFilename= 'api-config-files/'.$Request->getServerName().'.ttl';
  if(in_array($domainBasedConfigFilename, $files)){
    $files = array($domainBasedConfigFilename);
  }
    /*
     * Loop thru all of the RDF *.ttl files in the 'api-config-files' directory.
     * For each, load the ttl file into an RDF Graph, in the variable $ConfigGraph.
     * Note: the $ConfigGraph object contains the $Request object.
     * If the config of $ConfigGraph matches the $Request, construct a $Response object from the
     * $Request and $ConfigGraph, then call $Response->process() and exit the loop.
     * Finally, call $Response->serve() and cache the $Request --> $Response pair.
	 * Note: the $CompleteConfigGraph object is only used if no config matches the $Request.
    */
  $CompleteConfigGraph = new ConfigGraph(null, $Request, $HttpRequestFactory);
  foreach($files as $file){
      //logDebug("Iterating over files in /api-config: $file");
      if($ConfigGraph = LinkedDataApiCache::hasCachedConfig($file)){
//          logDebug("Found Cached Config {$file}");
          $CompleteConfigGraph->add_graph($ConfigGraph);
          $ConfigGraph->setRequest($Request);
      } else {
//          logDebug("Checking Config file: $file");
          $rdf = file_get_contents($file);
          $CompleteConfigGraph->add_rdf($rdf);
          $ConfigGraph =  new ConfigGraph(null, $Request, $HttpRequestFactory);
          $ConfigGraph->add_rdf($rdf);
          $errors = $ConfigGraph->get_parser_errors();
          if(!empty($errors)){
              foreach($ConfigGraph->get_parser_errors() as $errorList){
                  foreach($errorList as $errorMsg){
                      logDebug('Error parsing '.$file.'  '.$errorMsg);
                  }
              }
          }
//          logDebug("Caching $file");
          LinkedDataApiCache::cacheConfig($file, $ConfigGraph);
      }

      /*
       * $ConfigGraph contains the Graph from the ttl file for this loop iteration.
       * Next, see if the ConfigGraph matches the current $Request.  If it does, create a new
       * $Response instance, which will contain the $Request and $ConfigGraph.
       * Then, call $Response->process() and exit the loop.  Below, followup with
       * $Response->serve();
       */
      $ConfigGraph->init();
      $ConfigGraph->sparqlEndpointUri = OPS_SPARQL_ENDPOINT;
      if($selectedEndpointUri = $ConfigGraph->getEndpointUri()){
          logDebug("Endpoint Uri Selected: $selectedEndpointUri");
          unset($CompleteConfigGraph);
          $Response =  new LinkedDataApiResponse($Request, $ConfigGraph, $HttpRequestFactory);
        		$Response->process();
        		break;
      } else if($docPath = $ConfigGraph->dataUriToEndpointItem($Request->getUri())){
          logDebug("Redirecting ".$Request->getUri()." to {$docPath}");
          header("Location: $docPath", 303);
          exit;
      }
  }

  if(!isset($selectedEndpointUri)){
      logDebug("No Endpoint Selected");
      $Response =  new LinkedDataApiResponse($Request, $CompleteConfigGraph);

      if($Request->getPathWithoutExtension()==$Request->getInstallSubDir().CONFIG_PATH){
          logDebug("Serving ConfigGraph");
          $Response->serveConfigGraph();
      } else {
          logDebug("URI Requested:" . $Request->getPathWithoutExtension());
          $Response->process();
      }

  }
}

$Response->serve();
if (defined("PUELIA_SERVE_FROM_CACHE") AND  PUELIA_SERVE_FROM_CACHE
        AND !$Request->hasNoCacheHeader()
        AND $Response->cacheable)
{
	LinkedDataApiCache::cacheResponse($Request, $Response);
}
?>
