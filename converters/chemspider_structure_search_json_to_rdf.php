<?php

define('SEARCH_STATUS_REQUEST_TEMPLATE', CHEMSPIDER_ENDPOINT.'?op=GetSearchStatus&rid=');
define('SEARCH_RESULTS_REQUEST_TEMPLATE', CHEMSPIDER_ENDPOINT.'?op=GetSearchResult&rid=');
define('SEARCH_RESULTS_WITH_RELEVANCE_REQUEST_TEMPLATE', CHEMSPIDER_ENDPOINT.'?op=GetSearchResultWithRelevance&rid=');

define('XSD_FLOAT', 'http://www.w3.org/2001/XMLSchema#double');
define('CHEMSPIDER_NS', 'http://www.chemspider.com/');
define('OPS_PREFIX', 'http://www.openphacts.org/api/');
define('CHEMSPIDER_PREFIX', 'http://rdf.chemspider.com/');

define('EXACT_STRUCTURE_SEARCH', 0);
define('SUBSTRUCTURE_SEARCH', 1);
define('SIMILARITY_SEARCH', 2);

define('SEARCH_STATUS_UNKNOWN', 0);
define('SEARCH_STATUS_CREATED', 1);
define('SEARCH_STATUS_SCHEDULED', 2);
define('SEARCH_STATUS_PROCESSING', 3);
define('SEARCH_STATUS_SUSPENDED', 4);
define('SEARCH_STATUS_PARTIAL_RESULT_READY', 5);
define('SEARCH_STATUS_RESULT_READY', 6);
define('SEARCH_STATUS_FAILED', 7);
define('SEARCH_STATUS_TOO_MANY_RECORDS', 8);

define('SEARCH_TIMEOUT', 300000000); //5 minutes
define('POLLING_INTERVAL', 100000); //100ms

$errorStatuses=array(SEARCH_STATUS_UNKNOWN, SEARCH_STATUS_SUSPENDED, SEARCH_STATUS_PARTIAL_RESULT_READY, SEARCH_STATUS_FAILED, SEARCH_STATUS_TOO_MANY_RECORDS);

$searchTypes = array(OPS_PREFIX.'ExactStructureSearch', OPS_PREFIX.'SubstructureSearch', OPS_PREFIX.'SimilaritySearch');

$requestId = $response;

//wait until the status shows the search is completed
pollStatus($requestId, $errorStatuses);

//add Data To DataGraph
$searchType = getSearchType($this->Request->getPathWithoutExtension());
//$resultBNode = '_:searchResult';
$resultBNode = OPS_PREFIX.'ChemicalStructureSearch';
$this->DataGraph->add_resource_triple($resultBNode, RDF_TYPE, $searchTypes[$searchType]);

$decodedFinalResults = getDecodedFinalResults($requestId, $searchType);
if (empty($decodedFinalResults)){
    throw new EmptyResponseException("No results retrieved from Chemspider.");
}

$unreservedParameters = $this->Request->getUnreservedParams();
foreach ($unreservedParameters as $name => $value){
    $processedName = str_replace('.', '#', $name);
    $predicate = OPS_PREFIX.$processedName;

    $this->DataGraph->add_literal_triple($resultBNode, $predicate, $value);
}

foreach ($decodedFinalResults as $elem){
    if ($searchType==SUBSTRUCTURE_SEARCH || $searchType==SIMILARITY_SEARCH){
        $this->DataGraph->add_resource_triple($resultBNode, OPS_PREFIX.'#result', CHEMSPIDER_PREFIX.$elem->{"Id"});
        $this->DataGraph->add_literal_triple(CHEMSPIDER_PREFIX.$elem->{"Id"}, OPS_PREFIX.'#relevance', $elem->{"Relevance"}, null, XSD_FLOAT);
        
    }
    else{
        $this->DataGraph->add_resource_triple($resultBNode, OPS_PREFIX.'#result', CHEMSPIDER_PREFIX.$elem);
    }
}

$rdfData = $this->DataGraph->to_ntriples();//assuming nothing else is in the graph
logDebug("Inserted data: ".$rdfData);

//link pageUri to primaryTopic - resulted blank node
$this->DataGraph->add_resource_triple($this->pageUri, FOAF.'primaryTopic', $resultBNode);
$this->DataGraph->add_resource_triple($resultBNode , FOAF.'isPrimaryTopicOf', $this->pageUri);
$this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);


function getDecodedFinalResults($requestId, $searchType){
    //make request for getting final results
    logDebug("Entered getDecodedFinalResults");
    if ($searchType==SUBSTRUCTURE_SEARCH || $searchType==SIMILARITY_SEARCH){
        $finalRequest=SEARCH_RESULTS_WITH_RELEVANCE_REQUEST_TEMPLATE.$requestId;
    }
    else{
        $finalRequest=SEARCH_RESULTS_REQUEST_TEMPLATE.$requestId;
    }
    $ch = curl_init($finalRequest);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $finalResponse = curl_exec($ch);
    if ($finalResponse==false){
        throw new ErrorException("Failed retrieving search final results from ChemSpider: ".$finalRequest);
    }

    $decodedFinalResponse=json_decode($finalResponse);
    if ($decodedFinalResponse===FALSE OR $decodedFinalResponse===NULL){
        throw new ErrorException("Unexpected results returned from ChemSpider: ".$finalResponse);
    }

    return $decodedFinalResponse;
}


function getSearchType($path){
    if (endsWith("exact", $path)){
        return EXACT_STRUCTURE_SEARCH;
    } else if (endsWith("substructure", $path)){
        return SUBSTRUCTURE_SEARCH;
    } else if (endsWith("similarity", $path)){
        return SIMILARITY_SEARCH;
    }
    else{
        throw new ErrorException('Unknown search type');
    }
}

function pollStatus($requestId, $errorStatuses){
    $statusRequest=SEARCH_STATUS_REQUEST_TEMPLATE.$requestId;
    $statusResponse=null;

    $timeoutCounter = 0;
    $ch = curl_init($statusRequest);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    do {
        if ($statusResponse!=null){
            if ($timeoutCounter==SEARCH_TIMEOUT){
                throw new TimeoutException("Search timed out after waiting for ".(SEARCH_TIMEOUT/1000000/60)." minutes");
            }
            usleep(POLLING_INTERVAL);
            $timeoutCounter += POLLING_INTERVAL;
        }

        $statusResponse = curl_exec($ch);
        if ($statusResponse==false){
            throw new ErrorException("Polling for search status to ChemSpider failed: ".$statusResponse);
        }

        $decodedStatusResponse=json_decode($statusResponse);
        if ($decodedStatusResponse===FALSE OR $decodedStatusResponse===NULL){
            throw new ErrorException("Unexpected response returned from ChemSpider: ".$statusResponse);
        }

        $status=$decodedStatusResponse->{'Status'};
        if (in_array($status, $errorStatuses)){
            throw new ErrorException("Error status returned from ChemSpider: ".$status);
        }
    } while ($status!=SEARCH_STATUS_RESULT_READY);

    curl_close($ch);
}




?>
