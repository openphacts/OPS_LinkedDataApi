<?php

//input in $response in xml format 
//output $rdfData in NTriples format

//generate triples as: $inputConceptWiki skos:exactMatch $targetURLs

$xmlData = simplexml_load_string($response);
if ($xmlData===false){
    $errorString = "Failed loading XML result from IMS: ";
    foreach(libxml_get_errors() as $error) {
        $errorString .= "\t".$error->message;
    }
    throw new ErrorException($errorString);
}

$unreservedParameters = $this->Request->getUnreservedParams();
$inputURL = $unreservedParameters["URL"];

foreach ($xmlData->children() as $mapping){
    foreach ($mapping->{"targetURL"} as $elem){
        $targetURL = (string)$elem;
        if (!empty($targetURL) AND $targetURL!==$inputURL){
            $this->DataGraph->add_resource_triple($inputURL, SKOS.'exactMatch', $targetURL);
        }
    }
}

$rdfData = $this->DataGraph->to_ntriples();

//link pageUri to the primary topic - $inputURL
$this->DataGraph->add_resource_triple($this->pageUri, FOAF.'primaryTopic', $inputURL);
$this->DataGraph->add_resource_triple($inputURL , FOAF.'isPrimaryTopicOf', $this->pageUri);
$this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);

?>