<?php
//input in $response in json format

require 'converters/conceptwiki_util.inc.php';

$paramNameToPredicate = array( 'q' => 'searchTerm',
                            'limit' => 'limit',
                            'uuid' => 'tagUUID' );

$decodedResponse = json_decode($response);

$searchType = getSearchType($this->Request->getPathWithoutExtension());
$resultBNode = '_:searchResult';
$this->DataGraph->add_literal_triple($resultBNode, RDF_TYPE, $searchType);

$unreservedParameters = $this->Request->getUnreservedParams();
foreach ($unreservedParameters as $name => $value){
    $predSuffix = $paramNameToPredicate[$name];
    $this->DataGraph->add_literal_triple($resultBNode, OPS_API.$predSuffix, $value);
}

//link the resultBNode with the UUIDs and their tags
$tagCounter = 0;
foreach ($decodedResponse as $elem){
    $uuidNode = CONCEPTWIKI_PREFIX.$elem->{"uuid"};
    $this->DataGraph->add_resource_triple($resultBNode, OPS_API.'result', $uuidNode);

    foreach ($elem->{"labels"} as $label){
        addLabelWithLanguage($uuidNode, $label, $this->DataGraph);
    } 
    
    foreach ($elem->{"tags"} as $tag){
        $tagBNode = '_:tagNode'.$tagCounter;
        $this->DataGraph->add_resource_triple($uuidNode, OPS_API.'semanticTag', $tagBNode);
        
        $this->DataGraph->add_literal_triple($tagBNode, OPS_API.'uuid', $tag->{"uuid"});
        
        foreach ($tag->{"labels"} as $tagLabel){
            addLabelWithLanguage($tagBNode, $tagLabel, $this->DataGraph);
        }
        
        $tagCounter++;
    }
    
}

$rdfData = $this->DataGraph->to_ntriples();//assuming nothing else is in the graph

//link pageUri to primaryTopic - resulted blank node
$this->DataGraph->add_resource_triple($this->pageUri, FOAF.'primaryTopic', $resultBNode);
$this->DataGraph->add_resource_triple($resultBNode , FOAF.'isPrimaryTopicOf', $this->pageUri);
$this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);

function getSearchType($path){//TODO get this from the config
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