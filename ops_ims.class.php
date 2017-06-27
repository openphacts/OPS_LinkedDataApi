<?php
require_once 'exceptions.inc.php';

define('REQUEST_URI_NO', 40);

class OpsIms {

    private $_request;

    var $IMS_variables = array(
            '?chembl_target_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/target/' ,
            '?chembl_compound_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/molecule/' ,
            '?uniprot_target_uri'=>'http://purl.uniprot.org/uniprot/' ,
            '?cw_target_uri'=>'http://www.conceptwiki.org/concept/' ,
            '?cw_compound_uri'=>'http://www.conceptwiki.org/concept/' ,
            '?ocrs_compound_uri'=>'http://ops.rsc.org/OPS' ,
            '?db_compound_uri'=>'http://bio2rdf.org/drugbank',
            '?db_target_uri'=>'http://bio2rdf.org/drugbank',
            '?dg_gene_uri' => 'http://identifiers.org/ncbigene/',
	    '?umls_disease_uri' => 'http://linkedlifedata.com/resource/umls/id/',
	    '?node_uri' => 'http://rdf.ebi.ac.uk/resource/chembl/protclass/&targetUriPattern=http://purl.obolibrary.org/obo/CHEBI_&targetUriPattern=http://purl.uniprot.org/enzyme/&targetUriPattern=http://purl.obolibrary.org/obo/GO_&targetUriPattern=http://www.bioassayontology.org/bao#BAO_&targetUriPattern=http://purl.obolibrary.org/obo/DOID_',
	    '?aers_compound_uri' => 'http://aers.data2semantics.org/resource/drug/',
	    '?patent_uri' => 'http://rdf.ebi.ac.uk/resource/surechembl/patent/',
	    '?pw_uri' => 'http://identifiers.org/wikipathways/',
	    '?pw_compound_uri' => '',
	    '?pw_target_uri' => '',
            '?pw_entity_uri' => '',
	    '?pw_ref_uri' => 'http://identifiers.org/pubmed/',
	    '?schembl_target_uri' => 'http://rdf.ebi.ac.uk/resource/surechembl/target/',
	    '?schembl_compound_uri' => 'http://rdf.ebi.ac.uk/resource/surechembl/molecule/',
	    '?schembl_disease_uri' => 'http://rdf.ebi.ac.uk/resource/surechembl/indication/',
	    '?oidd_assay_uri' => 'http://openinnovation.lilly.com/bioassay#',
	    '?chembl_assay_uri' => 'http://rdf.ebi.ac.uk/resource/chembl/assay/',
	    '?nextprot_target_uri' => 'http://www.nextprot.org/db/search#'
    );

    var $IMS_interm_variables = array(
            '?ims_chembl_target_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/target/' ,
            '?ims_chembl_compound_uri'=>'http://rdf.ebi.ac.uk/resource/chembl/molecule/' ,
            '?ims_uniprot_target_uri'=>'http://purl.uniprot.org/uniprot/' ,
            '?ims_cw_target_uri'=>'http://www.conceptwiki.org/concept/' ,
            '?ims_cw_compound_uri'=>'http://www.conceptwiki.org/concept/' ,
            '?ims_ocrs_compound_uri'=>'http://ops.rsc.org/OPS' ,
            '?ims_db_compound_uri'=>'http://bio2rdf.org/drugbank:',
            '?ims_db_target_uri'=>'http://bio2rdf.org/drugbank',
	    '?ims_schembl_compound_uri' => 'http://rdf.ebi.ac.uk/resource/surechembl/molecule/',
            '?ims_dg_gene_uri' => 'http://identifiers.org/ncbigene/',
	    '?ims_umls_disease_uri' => 'http://linkedlifedata.com/resource/umls/id/',
            '?ims_omim_disease_uri' => 'http://identifiers.org/omim/',
    );

    var $expander_variables = array();//'?cw_uri' , '?ocrs_uri' , '?db_uri' , '?chembl_uri' , '?uniprot_uri' , '?aers_uri');

  function __construct($request){
    $this->_request = $request;
  }

  /**
   * Called by SparqlWriter
   *
   * @return mixed|SimpleXMLElement[]
   */
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

  private function getImsMapEndpoint() {
//      return IMS_MAP_ENDPOINT;
    $imsEndpointFromRequest = $this->_request->getParam('_imsendpoint');
    if ($imsEndpointFromRequest) {
//    	logDebug("Using IMS endpoint from params: $imsEndpointFromRequest");
      $imsMapEndpoint = $imsEndpointFromRequest . '/QueryExpander/mapUriRDF';
      return $imsMapEndpoint;
    } else if (IMS_MAP_ENDPOINT){
//    	logDebug("using IMS endpoint from environment variable: ".IMS_MAP_ENDPOINT);
      $imsMapEndpoint = IMS_MAP_ENDPOINT;
      return $imsMapEndpoint;
    }else {
    	logDebug("No IMS endpoint was specified for <".$this->_request->getUri().">");
    	throw new ConfigGraphException("No IMS endpoint was specified for <".$this->_request->getUri().">");
    }
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
//             $url = IMS_MAP_ENDPOINT;
             $url = $this->getImsMapEndpoint();
               $url .= '?rdfFormat=RDF/XML';
	       if ($pattern != '') {
		  $encoded_pattern = urlencode($pattern);
		  if (strpos($pattern, '&') !== FALSE) {
		    $encoded_pattern = str_replace('%26targetUriPattern%3D','&targetUriPattern=', $encoded_pattern);
		  }
                  $url .= '&targetUriPattern='.$encoded_pattern;
	       }
               $url .= '&overridePredicateURI='.urlencode('http://www.w3.org/2004/02/skos/core#exactMatch');
               $url .= '&lensUri=';
               if ($lens==''){
                  $url .= 'Default';
               }
               else{
                  $url .= $lens;
               }

              $url .= '&Uri='.urlencode($input_uri);
              logDebug("IMS Request (expandQueryThroughIMS):\n  ".$url);
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
// Disabled due to issue openphacts/OPS_LinkedDataApi#13
// The responses are handled later in foreach-handleResponse loop
//
//               $this->handleAvailableResponses($multiHandle, $input_uri, $variableInfoMap);
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
       logDebug("IMS Response:\n{$response}");
       curl_close($varInfo['handle']);
       curl_multi_remove_handle($multiHandle, $varInfo['handle']);
       //echo $url;
       $graph = new SimpleGraph() ;
       $graph->add_rdf($response);
       $variableInfoMap[$variableName]['filter'] = $this->buildFilterFromMappings($graph, array($input_uri), $variableName);
   }

   private function expandQueryThroughExpander($query, $params, $input_uri, $lens){
       $expanded = preg_replace("/\*#\*/","}",$query);
       $url = IMS_EXPAND_ENDPOINT;
       $url .= urlencode($expanded) ;
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

       logDebug("IMS Request (expandQueryThroughExpander):\n  ".$url);
       $expanded = simplexml_load_string($response)->expandedQuery ;
       return $expanded;
   }

  /**
   * 3 calls from SparqlWriter.getViewQueryForBatchUriList(...)
   *
   * @return mixed
   */
  function expandBatchQuery( $query , $uriList, $lens) {
	$rdf = "";
	$output['expandedQuery']=$query;
	$output['imsRDF']=$rdf;
	foreach ($this->IMS_interm_variables AS $name => $pattern) {
	    if (strpos($query, $name)!==false){
		$expanded = array();
//		$urlStart = IMS_MAP_ENDPOINT;
        $urlStart = $this->getImsMapEndpoint();
		$urlStart .= '?rdfFormat=N-Triples';
		if ($pattern != '') {
		  $encoded_pattern = urlencode($pattern);
                  if (strpos($pattern, '&') !== FALSE) {
                    $encoded_pattern = str_replace('%26targetUriPattern%3D','&targetUriPattern=', $encoded_pattern);
                  }
                  $urlStart .= '&targetUriPattern='.$encoded_pattern;
                }
                $urlStart .= '&overridePredicateURI='.urlencode('http://www.w3.org/2004/02/skos/core#exactMatch');
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
	$output['imsRDF']=$rdf;
    logDebug("IMS Request (expandBatchQuery) expandedQuery:\n  " . $output['expandedQuery']);
    logDebug("IMS Request (expandBatchQuery) imsRDF:\n  " . $output['imsRDF']);
	return $output;
  }

  private function buildFilterFromMappings($graph, $uriList, $variableName, &$expanded=array()){
    logDebug('$uriList = ' . $uriList);
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
          $filter = " VALUES {$variableName} { <http://www.openphacts.org/api#no_mappings_found> }" ;
      }
      logDebug("FILTER clause: ". $filter);
      return $filter;
  }

  private function getResponse($url, $mimetype){
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch,CURLOPT_HTTPHEADER,array ("Accept: ".$mimetype));
      curl_setopt($ch,CURLOPT_FAILONERROR, true);
      $response = curl_exec($ch);

      curl_close($ch);

      return $response;
  }

}
?>
