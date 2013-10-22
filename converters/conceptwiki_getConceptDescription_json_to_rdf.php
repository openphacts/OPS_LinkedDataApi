<?php

require 'converters/conceptwiki_util.inc.php';

$decodedResponse = json_decode($response);
if ($decodedResponse===FALSE OR $decodedResponse===NULL){
    throw new ErrorException("Error decoding external service response: ".$response);
}

if (empty($decodedResponse)){
    throw new EmptyResponseException("No results returned from the ConceptWiki");
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

//add urls with notations
$urlCounter = 0;
foreach ($decodedResponse->{"urls"} as $url){
    $urlBNode = '_:urlNode'.$urlCounter;
    $this->DataGraph->add_resource_triple($conceptWikiURL, SKOS.'exactMatch', $urlBNode);
    $this->DataGraph->add_resource_triple($urlBNode, CONCEPTWIKI_PREFIX.'#url', $url->{'value'});
    $this->DataGraph->add_resource_triple($urlBNode, CONCEPTWIKI_PREFIX.'#matchType', $url->{'type'});
    
    $lastPos = strrpos ( $url->{'value'} , '#');
    if ($lastPos === FALSE){
        $lastPos = strrpos ( $url->{'value'} , '/');
    }
    if ($lastPos === FALSE){
        continue;
    }
    
    $codeFromURL = substr($url->{'value'}, $lastPos+1);
    foreach ($decodedResponse->{"notations"} as $notation){
        if ($notation->{'code'} === $codeFromURL){
            foreach ($notation->{'sources'} as $source){
                $sourceURL = CONCEPTWIKI_PREFIX.$source->{'uuid'};
                $this->DataGraph->add_resource_triple($urlBNode, VOID.'inDataset', $sourceURL);
                foreach($source->{'labels'} as $label){
                    addLabelWithLanguage($sourceURL, $label, $this->DataGraph);
                }
                $this->DataGraph->add_literal_triple($sourceURL, CONCEPTWIKI_PREFIX.'#deleted', 
                        (bool)$source->{'deleted'} ? 'true' : 'false',
                        null, XSD.'boolean');
            } 
            break;
        }
    }
    
    $urlCounter++;
}

//add notes : skos:definition
foreach ($decodedResponse->{"notes"} as $note){
    if ($note->{"type"} === 'DEFINITION'){
        $this->DataGraph->add_literal_triple($conceptWikiURL, SKOS.'definition', $note->{'text'});//add language
    }
}


$rdfData = $this->DataGraph->to_ntriples();//assuming nothing else is in the graph

//link pageUri to primaryTopic - resulted blank node
$this->DataGraph->add_resource_triple($this->pageUri, FOAF.'primaryTopic', $conceptWikiURL);
$this->DataGraph->add_resource_triple($conceptWikiURL , FOAF.'isPrimaryTopicOf', $this->pageUri);
$this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);

?>