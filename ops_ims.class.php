<?php

class OpsIms {

    function expandQuery ( $query , $input_uri, $lens ) {
        $variables = array('?cw_uri' , '?ocrs_uri' , '?db_uri' , '?chembl_uri' , '?uniprot_uri' , '?pw_uri' , '?aers_uri');
        
        $params='';
        $output = $query ;
        foreach ($variables as &$var) {
            if (strpos($query , $var) !== false) {
                $params.= ", {$var}";
            }
        }
        if ($params !='') {
            $url = IMS_EXPAND_ENDPOINT;
            $url .= urlencode($query) ;
	        $params=substr($params, 2);
            $url .= '&inputURI=' . urlencode($input_uri) ;
            $url .= '&parameter=' ;
            $url .= urlencode($params);
            $url .= '&lensUri=';
            if (empty($lens)){
                $url .= 'Default';
            }
            else{
                $url .= $lens;
            }
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch,CURLOPT_HTTPHEADER,array (
            "Accept: application/xml"
                    ));
            $response = curl_exec($ch);
            curl_close($ch);

            //echo $query;
            //echo '<br><br>';
	    //echo $url;
            $output = simplexml_load_string($response)->expandedQuery ;
        }
        else {
            $variables = array(
                    '?chembl_target_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/target/' ,
                    '?chembl_compound_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/molecule/' ,
                    '?uniprot_target_uri'=>'http://purl.uniprot.org/uniprot/' ,
                    '?cw_target_uri'=>'http://www.conceptwiki.org/concept/' ,
                    '?cw_compound_uri'=>'http://www.conceptwiki.org/concept/' ,
                    '?ocrs_compound_uri'=>'http://ops.rsc.org/' ,
                    '?db_compound_uri'=>'http://www4.wiwiss.fu-berlin.de/drugbank/resource/drugs/',
                    '?db_target_uri'=>'http://www4.wiwiss.fu-berlin.de/drugbank/resource/targets/',
            );
            foreach ($variables AS $name => $pattern ){
                if (strpos($query, $name)!==false) {
                    if (strpos($input_uri, $pattern)!==false){
                        $filter = " FILTER ({$name} = <{$input_uri}>) ";
                        //echo $filter;
                    }
                    else {
                        $url = IMS_MAP_ENDPOINT;
			$url .= '?rdfFormat=Turtle';
                        $url .= '&Uri='.urlencode($input_uri);
                        $url .= "&targetUriPattern={$pattern}";
                        $url .= '&lensUri=';
                        if ($lens==''){
                            $url .= 'Default';
                        }
                        else{
                            $url .= $lens;
                        }
                        
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch,CURLOPT_HTTPHEADER,array ("Accept: text/plain"));

                        $response = curl_exec($ch);
                        curl_close($ch);
                        //echo $url;
                        $graph = new SimpleGraph() ;
                        $graph->add_rdf($response);
                        $filter = " FILTER ( ";
                        foreach ($graph->get_subject_properties($input_uri, true) AS $p ) {
                            foreach($graph->get_subject_property_values($input_uri, $p) AS $mapping) {
                                $filter.= "{$name} = <" . $mapping["value"] . "> || ";
                            }
                        }
                        if ($filter != " FILTER ( ") {
                            $filter = substr($filter,0,strlen($filter)-3);
                            $filter.= " )";
                        }
                        else $filter = " FILTER ( {$name} = 'No mappings found' )" ;
                        //echo $filter;
                    }
                    if (isset($filter) AND $filter != " FILTER ( ") {
                        $output = preg_replace("/(WHERE.*?GRAPH[^\}]*?\{)([^\}]*?\\".$name.")/s","$1
                        {$filter} $2",$output);
                        //echo $output;
                    }
                }
		}
	}
	return $output ;
   }

  function expandBatchQuery( $query , $uriList, $lens) {
	$variables = array(
          '?chembl_target_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/target/' ,
          '?chembl_compound_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/molecule/' ,
          '?uniprot_target_uri'=>'http://purl.uniprot.org/uniprot/' ,
          '?cw_target_uri'=>'http://www.conceptwiki.org/concept/' ,
          '?cw_compound_uri'=>'http://www.conceptwiki.org/concept/' ,
          '?ocrs_compound_uri'=>'http://ops.rsc.org/' ,
          '?db_compound_uri'=>'http://www4.wiwiss.fu-berlin.de/drugbank/resource/drugs/',
          '?db_target_uri'=>'http://www4.wiwiss.fu-berlin.de/drugbank/resource/targets/',
        );
	$rdf = "";
	$output['expandedQuery']=$query;
	$output['imsRDF']=$rdf;
	foreach ($variables AS $name => $pattern) {
	    if (strpos($query, $name)!==false){
		$expanded = array();
		$url = IMS_MAP_ENDPOINT;
		$url .= '?rdfFormat=N-Triples';
		$url .= "&targetUriPattern={$pattern}";
        	$url .= '&lensUri=';
        	if ($lens==''){
            	    $url .= 'Default';
        	}
        	else{
            	    $url .= $lens;
		}
        	foreach ($uriList AS $uri){
                    if (strpos($uri, $pattern)!==false){
                   	$expanded[] = $uri;
                    }
		    elseif (filter_var($uri, FILTER_VALIDATE_URL)) {
                    	$url .= '&Uri='.urlencode($uri);
		    }
		}
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch,CURLOPT_HTTPHEADER,array ("Accept: text/plain"));
		$response = curl_exec($ch);
		curl_close($ch);
		//echo $url . "\n";
		$graph = new SimpleGraph() ;
		$graph->add_rdf($response);
		$rdf.=$response;
		foreach ($uriList AS $input_uri){
		    foreach ($graph->get_subject_properties($input_uri, true) AS $p ) {
			foreach($graph->get_subject_property_values($input_uri, $p) AS $mapping) {
			    $expanded[] = $mapping["value"];
			}
		    }
		}
		$filter = " FILTER ( ";
		foreach ($expanded AS $mapping) {
		    $filter.= "{$name} = <{$mapping}> ||";
		}
		if ($filter != " FILTER ( ") {
		    $filter = substr($filter,0,strlen($filter)-3);
		    $filter.= " )";
		}
		else $filter = " FILTER ( {$name} = 'No mappings found' )" ;
		if (isset($filter) AND $filter != " FILTER ( ") {
		    $output['expandedQuery'] = preg_replace("/(WHERE.*?GRAPH[^\}]*?\{)([^\}]*?\\".$name.")/s","$1
{$filter} $2",$output['expandedQuery']);
		}
	    }
	}
	//echo $rdf;
	$output['imsRDF']=$rdf;
	return $output;
  }

}
?>
