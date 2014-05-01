<?php

define('REQUEST_URI_NO', 40);

class OpsIms {
    var $IMS_variables = array(
            '?chembl_target_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/target/' ,
            '?chembl_compound_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/molecule/' ,
            '?uniprot_target_uri'=>'http://purl.uniprot.org/uniprot/' ,
            '?cw_target_uri'=>'http://www.conceptwiki.org/concept/' ,
            '?cw_compound_uri'=>'http://www.conceptwiki.org/concept/' ,
            '?ocrs_compound_uri'=>'http://ops.rsc.org/OPS' ,
            '?db_compound_uri'=>'http://www4.wiwiss.fu-berlin.de/drugbank/resource/drugs/',
            '?db_target_uri'=>'http://www4.wiwiss.fu-berlin.de/drugbank/resource/targets/',
            '?dg_gene_uri' => 'http://identifiers.org/ncbigene/',
    );
    
    var $IMS_interm_variables = array(
            '?ims_chembl_target_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/target/' ,
            '?ims_chembl_compound_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/molecule/' ,
            '?ims_uniprot_target_uri'=>'http://purl.uniprot.org/uniprot/' ,
            '?ims_cw_target_uri'=>'http://www.conceptwiki.org/concept/' ,
            '?ims_cw_compound_uri'=>'http://www.conceptwiki.org/concept/' ,
            '?ims_ocrs_compound_uri'=>'http://ops.rsc.org/OPS' ,
            '?ims_db_compound_uri'=>'http://www4.wiwiss.fu-berlin.de/drugbank/resource/drugs/',
            '?ims_db_target_uri'=>'http://www4.wiwiss.fu-berlin.de/drugbank/resource/targets/',
            '?ims_dg_gene_uri' => 'http://identifiers.org/ncbigene/',
	    '?ims_umls_disease_uri' => 'http://linkedlifedata.com/resource/umls/id/',
            '?ims_omim_disease_uri' => 'http://identifiers.org/omim/',
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
	      //logDebug("IMS Request: ".$url);                   
              $variableInfoMap[$variableName]['url']=$url;
              $ch = curl_init();
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
              curl_setopt($ch,CURLOPT_HTTPHEADER,array ("Accept: text/plain"));
              curl_setopt($ch, CURLOPT_URL, $url);
              $variableInfoMap[$variableName]['handle'] = $ch;
              curl_multi_add_handle($multiHandle, $ch);
           }
       }
       
       $this->doSelectAndHandleResponses($multiHandle, $input_uri, $variableInfoMap);       
       curl_multi_close($multiHandle);
       foreach ($variableInfoMap AS $variableName => $info){
           if (isset($info['filter']) && preg_match("/(WHERE.*?)(GRAPH[^\}]*?\{[^\}]*?\\".$variableName.")/s",$output)) {
               $output = preg_replace("/(WHERE.*?)(GRAPH[^\}]*?\{[^\}]*?\\".$variableName.")/s",
               		"$1 {$info['filter']} $2",$output, 1);
           }
       }
       $output = preg_replace("/\*#\*/","}",$output);
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
                   if (isset($varInfo['handle']) && isset($info['handle']) && $varInfo['handle']==$info['handle']){
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
	//logDebug("IMS Response: {$response}");
       curl_close($varInfo['handle']);
       curl_multi_remove_handle($multiHandle, $varInfo['handle']);
       //echo $url;
       $graph = new SimpleGraph() ;
       $graph->add_rdf($response);
       $variableInfoMap[$variableName]['filter'] = $this->buildFilterFromMappings($graph, array($input_uri), $variableName);
   }
   
   private function expandQueryThroughExpander($query, $params, $input_uri, $lens){
       $output = $query ;
       $output = preg_replace("/\*#\*/","}",$output);
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
	//logDebug("IMS Request: ".$url);
       $output = simplexml_load_string($response)->expandedQuery ;
       return $output;
   }

  function expandBatchQuery( $query , $uriList, $lens) {
	$rdf = "";
	$output['expandedQuery']=$query;
	$output['imsRDF']=$rdf;
	foreach ($this->IMS_interm_variables AS $name => $pattern) {
	    if (strpos($query, $name)!==false){
		$expanded = array();
		$urlStart = IMS_MAP_ENDPOINT;
		$urlStart .= '?rdfFormat=N-Triples';
		$urlStart .= "&targetUriPattern={$pattern}";
		$urlStart .= '&lensUri=';
		if ($lens==''){
		    $urlStart .= 'Default';
		}
		else{
		    $urlStart .= $lens;
		}
		
		$graph = new SimpleGraph() ;
		$iter = 1;
		$url=$urlStart;

		foreach ($uriList AS $uri){
		    if ($iter % REQUEST_URI_NO == 0){
		        $response = $this->getResponse($url, "text/plain");
		        //echo $url . "\n";
		        
		        $graph->add_rdf($response);
		        $rdf.=$response;
		        
		        $url=$urlStart;
		    }
		    
		    $url .= '&Uri='.urlencode($uri);
		    
		    $iter++;
		}
		
		$response = $this->getResponse($url, "text/plain");
		//echo $url . "\n";
		$graph->add_rdf($response);
		$rdf.=$response;
		
		$filter = $this->buildFilterFromMappings($graph, $uriList, $name, $expanded);		
		if (isset($filter) ) {
		    $output['expandedQuery'] = preg_replace("/(WHERE.*?)(GRAPH[^\}]*?\{[^\}]*?\\".$name.")/s",
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
          $filter = " VALUES {$variableName} { ";
          foreach (array_unique($expanded, SORT_STRING) AS $mapping) {
              $filter.= "<{$mapping}> ";
          }
          $filter.= " }";
      }
      else{
          $filter = " VALUES {$variableName} {'No mappings found'}" ;
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
