<?php
//the input data is in the $response variable in xml format
//the output is put in $rdfData in NTriples format
//Conversion example for InChI to CSID:
//Request:
// /structure/inchi=InChI%3D1S%2FC9H8O4%2Fc1-6(10)13-8-5-3-2-4-7(8)9(11)12%2Fh2-5H%2C1H3%2C(H%2C11%2C12)
//Good Response:
//{"mol": "2157" , "message": "", "confidence":100}
//
//Resulting RDF:
//<http://rdf.chemspider.com/2157> cs:inchi "1S/C9H8O4/c1-6(10)13-8-5-3-2-4-7(8)9(11)12/h2-5H,1H3,(H,11,12)"
//

define('CHEMSPIDER_PREFIX', 'http://chemistry.openphacts.org/OPS');

//decode JSON
$decodedResponse=json_decode($response);
if ($decodedResponse===FALSE OR $decodedResponse===NULL){
	throw new ErrorException("Unexpected results returned from ChemSpider: ".$response);
}

if (!empty($decodedResponse->{"message"})){
	throw new ErrorException("Results returned from ChemSpider contain warnings or errors: \"".$decodedResponse->{"message"}."\"");
}

if ($decodedResponse->{"confidence"}!=100){
	throw new ErrorException("Results returned from ChemSpider have only a confidence of ".$decodedResponse->{"confidence"}."%");
}

$csid = $decodedResponse->{"mol"};
$fullCSID = CHEMSPIDER_PREFIX.$csid;

//extract inchi value from the request
$inputNode = $this->ConfigGraph->get_first_resource($this->ConfigGraph->getApiUri(), API.'variable');
$unreservedParameters = $this->Request->getUnreservedParams();

$paramName = $this->ConfigGraph->get_first_literal($inputNode, API.'label');//'inchi' or 'inchikey'
$paramValue = $unreservedParameters[$paramName];

//link the CSID to the inchi/inchikey value
$this->DataGraph->add_literal_triple($fullCSID, $inputNode, $paramValue);

$rdfData = $this->DataGraph->to_ntriples();//assuming nothing else is in the graph

//link pageUri to the primary topic - CSID
$this->DataGraph->add_resource_triple($this->pageUri, FOAF.'primaryTopic', $fullCSID);
$this->DataGraph->add_resource_triple($fullCSID , FOAF.'isPrimaryTopicOf', $this->pageUri);
$this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);

?>
