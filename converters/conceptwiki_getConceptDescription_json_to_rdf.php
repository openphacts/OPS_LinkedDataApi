<?php

require 'converters/conceptwiki_util.inc.php';

$decodedResponse = json_decode($response);
if ($decodedResponse===FALSE OR $decodedResponse===NULL){
    throw new ErrorException("Error decoding external service response: ".$response);
}

$unreservedParameters = $this->Request->getUnreservedParams();
$conceptWikiURL = CONCEPTWIKI_PREFIX.$unreservedParameters['uuid'];

foreach ($decodedResponse->{"labels"} as $label){
    addLabelWithLanguage($conceptWikiURL, $label, $this->DataGraph); 
}

//add tags
$tagCounter = 0;
foreach ($decodedResponse->{"tags"} as $tag){
    $tagBNode = '_:tagNode'.$tagCounter;
    $this->DataGraph->add_resource_triple($conceptWikiURL, OPS_API.'#semanticTag', $tagBNode);

    addConceptWithLabels($tagBNode, $tag, $this->DataGraph);
    $tagCounter++;
}

//add urls
$urlCounter = 0;
foreach ($decodedResponse->{"urls"} as $url){
    $urlBNode = '_:urlNode'.$urlCounter;
    $this->DataGraph->add_resource_triple($conceptWikiURL, SKOS.'exactMatch', $urlBNode);
    $this->DataGraph->add_resource_triple($urlBNode, CONCEPTWIKI_PREFIX.'#url', $url->{'value'});
    $this->DataGraph->add_resource_triple($urlBNode, CONCEPTWIKI_PREFIX.'#matchType', $url->{'type'});
    $urlCounter++;
}

//add notes : skos:definition
foreach ($decodedResponse->{"notes"} as $note){
    if ($note->{"type"} === 'DEFINITION'){
        $this->DataGraph->add_literal_triple($conceptWikiURL, SKOS.'definition', $note->{'text'});//add language
    }
}

//add notations:  skos:notation bNode
//            bNode ops_api:code ..
//            bNode ops_api:source sourceBNode
//                sourceBNode ops_api:uuid ..
//                sourceBNode skos:prefLabel ..
//                sourceBNode skos:altLabel ..
//                sourceBNode ops_api:deleted ..
$notationCounter = 0;
$sourceCounter = 0;
foreach ($decodedResponse->{"notations"} as $notation){
    $notationBNode = '_:notation'.$notationCounter;
    $this->DataGraph->add_resource_triple($conceptWikiURL, SKOS.'notation', $notationBNode);

    $this->DataGraph->add_resource_triple($notationBNode, OPS_API.'#code', $notation->{'code'});
    foreach ($notation->{"sources"} as $source){
        $sourceBNode = '_:sourceBNode'.$sourceCounter;
        $this->DataGraph->add_resource_triple($notationBNode, OPS_API.'#source', $sourceBNode);
        
        addConceptWithLabels($sourceBNode, $source, $this->DataGraph);                   
        $sourceCounter++;
    }
    
    $notationCounter++;
}


$rdfData = $this->DataGraph->to_ntriples();//assuming nothing else is in the graph

//link pageUri to primaryTopic - resulted blank node
$this->DataGraph->add_resource_triple($this->pageUri, FOAF.'primaryTopic', $conceptWikiURL);
$this->DataGraph->add_resource_triple($conceptWikiURL , FOAF.'isPrimaryTopicOf', $this->pageUri);
$this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);

?>