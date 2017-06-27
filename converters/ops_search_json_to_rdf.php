<?php
//input in $response in json format

//require 'converters/conceptwiki_util.inc.php';

$paramNameToPredicate = array( 'query' => 'searchTerm',
                               'limit' => 'limit',
		                       'type' => 'type',
			                   'branch' => 'branch' );

$decodedResponse = json_decode($response);
if ($decodedResponse===FALSE OR $decodedResponse===NULL){
    throw new ErrorException("Error decoding external service response: ".$response);
}

if (empty($decodedResponse)){
    throw new EmptyResponseException("No results returned from the ConceptWiki");
}
$resultBNode = '_:searchResult';


$unreservedParameters = $this->Request->getUnreservedParams();
foreach ($unreservedParameters as $name => $value){
    $predSuffix = $paramNameToPredicate[$name];
    $this->DataGraph->add_literal_triple($resultBNode, OPS_API.'#'.$predSuffix, $value);
}

$tagCounter = 0;
$urlCounter = 0;

foreach ($decodedResponse as $elem){
    foreach ($elem->{"hits"} as $hit) {
            $id = $hit->{"_id"};
	        $source = $hit->{"_source"};

            $urlBNode = '_:urlNode'.$urlCounter;
            $uuidNode = $id;
            $this->DataGraph->add_resource_triple($urlBNode, $uuidNode.'#url', $id);
            foreach ($source->{"label"} as $label) {
            	$this->DataGraph->add_literal_triple($urlBNode, SKOS.'prefLabel', $label);
            }
            $this->DataGraph->add_resource_triple($resultBNode, OPS_API.'#result', $urlBNode);
            
            $urlCounter++;
    }
}


$rdfData = $this->DataGraph->to_ntriples();//assuming nothing else is in the graph

//link pageUri to primaryTopic - resulted blank node
$this->DataGraph->add_resource_triple($this->pageUri, FOAF.'primaryTopic', $resultBNode);
$this->DataGraph->add_resource_triple($resultBNode , FOAF.'isPrimaryTopicOf', $this->pageUri);
$this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);


?>
