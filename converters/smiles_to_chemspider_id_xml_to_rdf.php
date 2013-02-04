<?php

define('CHEMSPIDER_NS', 'http://www.chemspider.com/');

//extract inchi value from SMILES to InChI response
$xmlData = simplexml_load_string($response);

$ns = $xmlData->getDocNamespaces();
if ($ns[''] !== CHEMSPIDER_NS){
    throw new Exception("Converter - Chemspider Id XML to RDF: namespace ".$ns['']." not expected");
}

$inchi = $xmlData[0];

//make request for InChI to CSID
$inchiToCSIDRequest = 'http://www.chemspider.com/InChI.asmx/InChIToCSID?inchi='.urlencode($inchi);

$ch = curl_init($inchiToCSIDRequest);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
if ($response==false){
    throw new ErrorException("Request: ".$externalServiceRequest." failed");
}

$this->pageUri = $this->Request->getUriWithoutPageParam();

require 'converters/chemspider_id_xml_to_rdf.php';



?>
