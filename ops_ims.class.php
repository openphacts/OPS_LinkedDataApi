<?php

class OpsIms {
    var $IMS_variables = array(
            '?chembl_target_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/target/' ,
            '?chembl_compound_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/molecule/' ,
            '?uniprot_target_uri'=>'http://purl.uniprot.org/uniprot/' ,
            '?cw_target_uri'=>'http://www.conceptwiki.org/concept/' ,
            '?cw_compound_uri'=>'http://www.conceptwiki.org/concept/' ,
            '?ocrs_compound_uri'=>'http://ops.rsc.org/' ,
            '?db_compound_uri'=>'http://www4.wiwiss.fu-berlin.de/drugbank/resource/drugs/',
            '?db_target_uri'=>'http://www4.wiwiss.fu-berlin.de/drugbank/resource/targets/',
            '?dg_gene_uri' => 'http://identifiers.org/ncbigene/',
    );
    
    var $expander_variables = array('?cw_uri' , '?ocrs_uri' , '?db_uri' , '?chembl_uri' , '?uniprot_uri' , '?pw_uri' , '?aers_uri');
    
    function expandQuery ( $query , $input_uri, $lens ) {
        
        $params='';       
        foreach ($this->expander_variables as &$var) {
            if (strpos($query , $var) !== false) {
                $params.= ", {$var}";
            }
        }
        if ($params !='') {
            $output = $this->expandQueryThroughExpander($query, $params, $input_uri, $lens);
        }
        else {        
            $output = $this->expandQueryThroughIMS($query, $input_uri, $lens);
		}
	
	    return $output ;
   }
   
   private function expandQueryThroughIMS($query, $input_uri, $lens){
       $output = $query ;
       //build a hashtable which maps $variableName -> (uri, curl_handle, filter_clause)
       
       $multiHandle = curl_multi_init();
       $variableInfoMap = array();
           
       //build curl multi handle setup      
       foreach ($this->IMS_variables AS $variableName => $pattern ){
           if (strpos($query, $variableName)!==false) {
               $variableInfoMap[$variableName] = array();
               if (strpos($input_uri, $pattern)!==false){
                   $variableInfoMap[$variableName]['filter'] = " FILTER ({$variableName} = <{$input_uri}>) ";
                   //echo $filter;
               }
               else {
                   $url = IMS_MAP_ENDPOINT;
                   $url .= '?rdfFormat=RDF/XML';
                   $url .= "&targetUriPattern={$pattern}";
                   $url .= '&lensUri=';
                   if ($lens==''){
                       $url .= 'Default';
                   }
                   else{
                       $url .= $lens;
                   }
       
                   $url .= '&Uri='.urlencode($input_uri);
                   
                   $variableInfoMap[$variableName]['url']=$url;
                   
                   $ch = curl_init();
                   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                   curl_setopt($ch,CURLOPT_HTTPHEADER,array ("Accept: text/plain"));
                   curl_setopt($ch, CURLOPT_URL, $url);
                   $variableInfoMap[$variableName]['handle'] = $ch;
                   curl_multi_add_handle($multiHandle, $ch);
               }
           }
       }
       
       $this->doSelectAndHandleResponses($multiHandle, $input_uri, $variableInfoMap);       
       curl_multi_close($multiHandle);
        
       foreach ($variableInfoMap AS $variableName => $info){
           if (isset($info['filter']) AND $info['filter'] != " FILTER ( ") {
               $output = preg_replace("/(WHERE.*?GRAPH[^\}]*?\{)([^\}]*?\\".$variableName.")/s",
               		"$1 {$info['filter']} $2",$output, 1);                          
           }
       }
      
       
       return $output;
   }
   
   private function doSelectAndHandleResponses($multiHandle, $input_uri, &$variableInfoMap){
       do{
           do {
               $mrc = curl_multi_exec($multiHandle, $activeHandles);
           } while ($mrc==CURLM_CALL_MULTI_PERFORM);
       
           if ($activeHandles==0 || $mrc!=CURLM_OK) break;
            
           if (curl_multi_select($multiHandle) != -1){//wait for requests
               $this->handleAvailableResponses($multiHandle, $input_uri, $variableInfoMap);
           }
       }
       while (true);
        
       foreach ($variableInfoMap AS $variableName => $varInfo){
           if (isset($varInfo['url'])&&!isset($varInfo['filter'])){
               $this->handleResponse($varInfo, $multiHandle, $input_uri, $variableName, $variableInfoMap);
           }
       }
   }
   
   private function handleAvailableResponses($multiHandle, $input_uri, $variableInfoMap){
       do{
           $info = curl_multi_info_read($multiHandle);
           if ($info===FALSE) break;
       
           if ( $info['result'] != CURLE_OK ) {
               logError("Error receiving info from the IMS");
               break;
           }
           else{//process handle
               foreach ($variableInfoMap AS $variableName => $varInfo){
                   if ($varInfo['handle']==$info['handle']){
                       $this->handleResponse($varInfo, $multiHandle, $input_uri, $variableName, $variableInfoMap);
                       break;
                   }
               }
           }
       }
       while(true);
   }
   
   private function handleResponse($varInfo, $multiHandle, $input_uri, $variableName, &$variableInfoMap){
       $response = curl_multi_getcontent($varInfo['handle']);
       curl_close($varInfo['handle']);
       curl_multi_remove_handle($multiHandle, $varInfo['handle']);
       //echo $url;
       $graph = new SimpleGraph() ;
       $graph->add_rdf($response);
       $variableInfoMap[$variableName]['filter'] = $this->buildFilterFromMappings($graph, array($input_uri), $variableName);
   }
   
   private function expandQueryThroughExpander($query, $params, $input_uri, $lens){
       $output = $query ;
       
       $url = IMS_EXPAND_ENDPOINT;
       $url .= urlencode($query) ;
       $params=substr($params, 2);
       $url .= '&parameter=' ;
       $url .= urlencode($params);
       $url .= '&lensUri=';
       if (empty($lens)){
           $url .= 'Default';
       }
       else{
           $url .= $lens;
       }
       
       $url .= '&inputURI=' . urlencode($input_uri) ;
       $response = $this->getResponse($url, "application/xml");
       
       //echo $query;
       //echo '<br><br>';
       //echo $url;
       $output = simplexml_load_string($response)->expandedQuery ;
       return $output;
   }

  function expandBatchQuery( $query , $uriList, $lens) {
	$rdf = "";
	$output['expandedQuery']=$query;
	$output['imsRDF']=$rdf;
	foreach ($this->IMS_variables AS $name => $pattern) {
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
		
		$response = $this->getResponse($url, "text/plain");
		//echo $url . "\n";
		$graph = new SimpleGraph() ;
		$graph->add_rdf($response);
		$rdf.=$response;
		
		$filter = $this->buildFilterFromMappings($graph, $uriList, $name, $expanded);		
		if (isset($filter) AND $filter != " FILTER ( ") {
		    $output['expandedQuery'] = preg_replace("/(WHERE.*?GRAPH[^\}]*?\{)([^\}]*?\\".$name.")/s",
		    								"$1{$filter} $2",
		    								$output['expandedQuery']);
		}
	    }
	}
	//echo $rdf;
	$output['imsRDF']=$rdf;
	return $output;
  }
  
  private function buildFilterFromMappings($graph, $uriList, $variableName, &$expanded=array()){
  	foreach ($uriList AS $input_uri){
  		foreach ($graph->get_subject_properties($input_uri, true) AS $p ) {
  			foreach($graph->get_subject_property_values($input_uri, $p) AS $mapping) {
  				$expanded[] = $mapping["value"];
  			}
  		}
  	}
  	if (count($expanded)>0){
  		$filter = " FILTER ( ";
  		foreach ($expanded AS $mapping) {
  			$filter.= "{$variableName} = <{$mapping}> ||";
  		}
  		 
  		$filter = substr($filter,0,strlen($filter)-3);
  		$filter.= " )";
  	}
  	else{
  		$filter = " FILTER ( {$variableName} = 'No mappings found' )" ;
  	}

  	return $filter;
  }
  
  private function getResponse($url, $mimetype){
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch,CURLOPT_HTTPHEADER,array ("Accept: ".$mimetype));
      $response = curl_exec($ch);
      curl_close($ch);
      
      return $response;
  }

}
?>
