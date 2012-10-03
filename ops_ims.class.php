<?php

class OpsIms {

	function expandQuery ( $query , $input_uri ) {
		$variables = array('?cw_uri' , '?cs_uri' , '?db_uri' , '?chembl_uri' , '?uniprot_uri');
		$url = 'http://openphacts.cs.man.ac.uk:9090/QueryExpander/expandXML?query=' ;
		$url .= urlencode($query) ;
		$params='';
		foreach ($variables as &$var) {
			if (strstr($query , $var) != false) {
				$params.= ", {$var}";
			}
		}
		if ($params !='') {
			$url .= '&inputURI=' . urlencode($input_uri) ;
			$url .= '&parameter=' ;
			$url .= urlencode($params);
		}
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
		curl_setopt($ch,CURLOPT_HTTPHEADER,array (
		        "Accept: application/xml"
		    ));
		$response = curl_exec($ch);
		curl_close($ch);
		//echo $url;
		$output = simplexml_load_string($response)->expandedQuery ;
		return $output ;
	}
 
}

?>
