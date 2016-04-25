<?php

//extract inchi value from SMILES to InChI response
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

$inchi = $decodedResponse->{"mol"};

//make request for InChI to CSID
$inchiToCSIDRequest = CHEMSPIDER_ENDPOINT.'?op=ConvertTo&convertOptions.Direction=InChi2CSID&convertOptions.Text='.urlencode($inchi);

$ch = curl_init($inchiToCSIDRequest);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
if ($response==false){
    throw new ErrorException("Request: ".$externalServiceRequest." failed");
}

$this->pageUri = $this->Request->getUriWithoutPageParam();

require 'converters/chemspider_id_xml_to_rdf.php';



?>
