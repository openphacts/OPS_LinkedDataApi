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

$rdfData = $this->DataGraph->to_ntriples();//assuming nothing else is in the graph

//link pageUri to primaryTopic - resulted blank node
$this->DataGraph->add_resource_triple($this->pageUri, FOAF.'primaryTopic', $conceptWikiURL);
$this->DataGraph->add_resource_triple($conceptWikiURL , FOAF.'isPrimaryTopicOf', $this->pageUri);
$this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);

?>