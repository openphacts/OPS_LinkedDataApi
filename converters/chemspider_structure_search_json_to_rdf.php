<?php

define('SEARCH_STATUS_REQUEST_TEMPLATE', 'http://parts.chemspider.com/JSON.ashx?op=GetSearchStatus&rid=');
define('SEARCH_RESULTS_REQUEST_TEMPLATE', 'http://parts.chemspider.com/JSON.ashx?op=GetSearchResult&rid=');

define('CHEMSPIDER_NS', 'http://www.chemspider.com/');
define('OPS_CHEMSPIDER_PREFIX', 'http://www.chemspider.com/api/');
define('CHEMSPIDER_PREFIX', 'http://rdf.chemspider.com/');

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

$requestId = $response;

pollStatus($requestId);

$decodedFinalResults = getDecodedFinalResults($requestId);


//add Data To DataGraph
$searchType = getSearchType($this->Request->getPathWithoutExtension());
$resultBNode = '_:searchResult';
$this->DataGraph->add_literal_triple($resultBNode, RDF_TYPE, $searchType);

$unreservedParameters = $this->Request->getUnreservedParams();
foreach ($unreservedParameters as $name => $value){
    $processedName = str_replace('.', '#', $name);
    $predicate = OPS_CHEMSPIDER_PREFIX.$processedName;

    $this->DataGraph->add_literal_triple($resultBNode, $predicate, $value);
}

foreach ($decodedFinalResults as $elem){
    $this->DataGraph->add_resource_triple($resultBNode, OPS_CHEMSPIDER_PREFIX.'result', CHEMSPIDER_PREFIX.$elem);
}

$rdfData = $this->DataGraph->to_ntriples();//assuming nothing else is in the graph

//link pageUri to primaryTopic - resulted blank node
$this->DataGraph->add_resource_triple($this->pageUri, FOAF.'primaryTopic', $resultBNode);
$this->DataGraph->add_resource_triple($resultBNode , FOAF.'isPrimaryTopicOf', $this->pageUri);
$this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);



function getDecodedFinalResults($requestId){
    //make request for getting final results
    $finalRequest=SEARCH_RESULTS_REQUEST_TEMPLATE.$requestId;
    $ch = curl_init($finalRequest);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $finalResponse = curl_exec($ch);
    if ($finalResponse==false){
        throw new ErrorException("Failed retrieving search final results from ChemSpider: ".$finalRequest);
    }

    $decodedFinalResponse=json_decode($finalResponse);
    if ($decodedFinalResponse===FALSE OR $decodedFinalResponse===NULL){
        throw new ErrorException("Bad JSON returned from ChemSpider: ".$finalResponse);
    }

    return $decodedFinalResponse;
}


function getSearchType($path){
    if (endsWith("exact", $path)){
        return "ExactStructureSearch";
    } else if (endsWith("substructure", $path)){
        return "SubstructureSearch";
    } else if (endsWith("similarity", $path)){
        return "SimilaritySearch";
    }
    else{
        throw new ErrorException('Unknown search type');
    }
}

function pollStatus($requestId){
    $statusRequest=SEARCH_STATUS_REQUEST_TEMPLATE.$requestId;
    $statusResponse=null;

    $timeoutCounter = 0;
    $ch = curl_init($statusRequest);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    do {
        if ($statusResponse!=null){
            if ($timeoutCounter==SEARCH_TIMEOUT){
                throw new ErrorException("Search timed out after waiting for ".(SEARCH_TIMEOUT/1000000/60)." minutes");
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
            throw new ErrorException("Bad JSON returned from ChemSpider: ".$statusResponse);
        }

        $status=$decodedStatusResponse->{'Status'};
        if (in_array($status, $errorStatuses)){
            throw new ErrorException("Error status returned from ChemSpider: ".$status);
        }
    } while ($status!=SEARCH_STATUS_RESULT_READY);

    curl_close($ch);
}




?>