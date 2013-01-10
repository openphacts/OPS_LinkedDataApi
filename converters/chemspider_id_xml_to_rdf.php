<?php
//the input data is in the $response variable in xml format
//Response example: 
//<string xmlns="http://www.chemspider.com/">2157</string>
//<http://rdf.chemspider.com/2157> cs:inchi "<request_inchi>"

$xmlData = simplexml_load_string($response);
$rdfData = '';

?>