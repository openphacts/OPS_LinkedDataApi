<?php
define('CHEMSPIDER_NS', 'http://www.chemspider.com/');
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

$errorStatuses=array(SEARCH_STATUS_UNKNOWN, SEARCH_STATUS_PARTIAL_RESULT_READY, SEARCH_STATUS_FAILED, SEARCH_STATUS_TOO_MANY_RECORDS);

$requestId = $response;
echo $this->Request->getPath();

pollStatus($requestId);

//make request for getting final results
$finalRequest='http://parts.chemspider.com/JSON.ashx?op=GetSearchResult&rid='.$requestId;
$ch = curl_init($finalRequest);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$finalResponse = curl_exec($ch);
if ($finalResponse==false){
    throw new ErrorException("Request: ".$finalRequest." failed");
}

//get results in json format
$decodedFinalResponse=json_decode($finalResponse);
if ($decodedFinalResponse==null){
    throw new ErrorException("Bad JSON returned from ChemSpider: ".$finalResponse);
}

$searchType = getSearchType($this->Request->getPathWithoutExtension);
$this->DataGraph->add_resource_triple('_:searchResult', RDF_TYPE, $searchType);

//TODO add search options from Request
$paramBindings = $this->ConfigGraph->getParamVariableBindings();
foreach ($paramBindings as $name => $value){
    echo $name.' '.$value.'\n';
}

foreach ($decodedFinalResponse as $elem){
    $this->DataGraph->add_literal_triple($resultBNode, CHEMSPIDER_PREFIX.'result', $elem);
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

function endsWith($needle, $haystack){
    return (substr($haystack, -strlen($needle))===$needle);
}


function pollStatus($requestId){
    $statusRequest='http://parts.chemspider.com/JSON.ashx?op=GetSearchStatus&rid='.$requestId;
    $statusResponse=null;
    
    do {
        if ($statusResponse!=null){
            usleep(100000);//100ms
        }
    
        $ch = curl_init($statusRequest);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $statusResponse = curl_exec($ch);
        if ($statusResponse==false){
            throw new ErrorException("Request: ".$statusResponse." failed");
        }
    
        $decodedStatusResponse=json_decode($statusResponse);
        if ($decodedStatusResponse==null){
            throw new ErrorException("Bad JSON returned from ChemSpider: ".$statusResponse);
        }
    
        $status=$decodedStatusResponse->{'Status'};
        if (in_array($status, $errorStatuses)){
            throw new ErrorException("Error status returned from ChemSpider: ".$status);
        }
    } while ($status!=SEARCH_STATUS_RESULT_READY);
    
}




?>