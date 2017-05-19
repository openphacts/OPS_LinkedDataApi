<?php
//input in $response in json format

require 'converters/conceptwiki_util.inc.php';

$paramNameToPredicate = array( 'q' => 'searchTerm',
                            'limit' => 'limit',
                            'uuid' => 'tagUUID',
			    'branch' => 'branch' );

$decodedResponse = json_decode($response);
if ($decodedResponse===FALSE OR $decodedResponse===NULL){
    throw new ErrorException("Error decoding external service response: ".$response);
}

if (empty($decodedResponse)){
    throw new EmptyResponseException("No results returned from the ConceptWiki");
}

$searchType = getSearchType($this->Request->getPathWithoutExtension());
$resultBNode = '_:searchResult';
$this->DataGraph->add_literal_triple($resultBNode, RDF_TYPE, $searchType);

$unreservedParameters = $this->Request->getUnreservedParams();
foreach ($unreservedParameters as $name => $value){
    $predSuffix = $paramNameToPredicate[$name];
    $this->DataGraph->add_literal_triple($resultBNode, OPS_API.'#'.$predSuffix, $value);
}

//link the resultBNode with the UUIDs and their tags
$tagCounter = 0;
$urlCounter = 0;

foreach ($decodedResponse as $elem){
    $uuidNode = CONCEPTWIKI_PREFIX.$elem->{"uuid"};
    $this->DataGraph->add_resource_triple($resultBNode, OPS_API.'#result', $uuidNode);

    foreach ($elem->{"labels"} as $label){
        addLabelWithLanguage($uuidNode, $label, $this->DataGraph);
    } 
    
    $this->DataGraph->add_literal_triple($uuidNode, OPS_API.'#match', $elem->{'match'});
    
    foreach ($elem->{"tags"} as $tag){
        $tagBNode = '_:tagNode'.$tagCounter;
        $this->DataGraph->add_resource_triple($uuidNode, OPS_API.'#semanticTag', $tagBNode);
        
        addConceptWithLabels($tagBNode, $tag, $this->DataGraph);
        $tagCounter++;
    }
    
    if (isset($elem->{"urls"})){
    	foreach ($elem->{"urls"} as $url){
    		$urlBNode = '_:urlNode'.$urlCounter;
    		$this->DataGraph->add_resource_triple($uuidNode, SKOS.'exactMatch', $urlBNode);
    		$this->DataGraph->add_resource_triple($urlBNode, CONCEPTWIKI_PREFIX.'#url', $url->{'value'});
    		$this->DataGraph->add_resource_triple($urlBNode, CONCEPTWIKI_PREFIX.'#matchType', $url->{'type'});
    		$urlCounter++;
    	}
    }
}

$rdfData = $this->DataGraph->to_ntriples();//assuming nothing else is in the graph

//link pageUri to primaryTopic - resulted blank node
$this->DataGraph->add_resource_triple($this->pageUri, FOAF.'primaryTopic', $resultBNode);
$this->DataGraph->add_resource_triple($resultBNode , FOAF.'isPrimaryTopicOf', $this->pageUri);
$this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);

function getSearchType($path){
    if (endsWith("freetext", $path)){
        return "Freetext Search";
    }
    else if (endsWith("byTag", $path)){
        return "Search by tag";
    }
    else{
        throw new ErrorException('Unknown search type');
    }
}



?>
