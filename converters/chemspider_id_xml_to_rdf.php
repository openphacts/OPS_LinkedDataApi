<?php
//the input data is in the $response variable in xml format
//the output is put in $rdfData in NTriples format
//Conversion example for InChI to CSID: 
//Request: 
// /structure/inchi=InChI%3D1S%2FC9H8O4%2Fc1-6(10)13-8-5-3-2-4-7(8)9(11)12%2Fh2-5H%2C1H3%2C(H%2C11%2C12)
//Respone:
//<string xmlns="http://www.chemspider.com/">2157</string>
//
//Resulting RDF:
//<http://rdf.chemspider.com/2157> cs:inchi "1S/C9H8O4/c1-6(10)13-8-5-3-2-4-7(8)9(11)12/h2-5H,1H3,(H,11,12)"
//

define('CHEMSPIDER_NS', 'http://www.chemspider.com/');
define('CHEMSPIDER_PREFIX', 'http://rdf.chemspider.com/');

$xmlData = simplexml_load_string($response);

$ns = $xmlData->getDocNamespaces();
if ($ns[''] !== CHEMSPIDER_NS){
    throw new Exception("Converter - Chemspider Id XML to RDF: namespace ".$ns['']." not expected");
}

$csid = $xmlData[0];
$fullCSID = CHEMSPIDER_PREFIX.$csid;

//extract inchi value from the request
$inputNode = $this->ConfigGraph->get_first_resource($this->ConfigGraph->getApiUri(), API.'variable');
$unreservedParameters = $this->Request->getUnreservedParams();

$paramName = $this->ConfigGraph->get_first_literal($inputNode, API.'label');//'inchi' or 'inchikey'
$paramValue = $unreservedParameters[$paramName];                        

$this->DataGraph->add_literal_triple($fullCSID, $inputNode, $paramValue);

$rdfData = $this->DataGraph->to_ntriples();//assuming nothing else is in the graph

//link pageUri to the primary topic - CSID
$this->DataGraph->add_resource_triple($this->pageUri, FOAF.'primaryTopic', $fullCSID);
$this->DataGraph->add_resource_triple($fullCSID , FOAF.'isPrimaryTopicOf', $this->pageUri);
$this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);

?>