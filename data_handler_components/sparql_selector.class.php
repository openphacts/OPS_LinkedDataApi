<?php

require_once 'exceptions.inc.php';

require_once 'data_handler_components/selector.interf.php';
require_once 'sparqlwriter.class.php';

class SparqlSelector implements Selector{
		
	private $Request;
	private $SparqlEndpoint;
	private $SparqlWriter;
	private $selectQuery;
	
	function __construct($Request, $SparqlWriter, $SparqlEndpoint){
		$this->Request = $Request;
		$this->SparqlWriter = $SparqlWriter;
		$this->SparqlEndpoint = $SparqlEndpoint;
	}
	
	function getItemMap(){
		$itemMap = array();
		$itemMap['item'] = array();
		try {
		    $responsePair = $this->SparqlWriter->getSelectQueryForUriList();
			$this->selectQuery = $responsePair['query'];
			if(LOG_SELECT_QUERIES){
				logSelectQuery($this->Request, $this->selectQuery);
			}
		} catch (Exception $e){
			logError("Possible configuration error: ".$e->getMessage());
			throw $e;
		}

		$response = $this->SparqlEndpoint->query($this->selectQuery, PUELIA_SPARQL_ACCEPT_MIMES);

		if($response->is_success()){
			$body = trim($response->body);
			if($body[0]=='{') {//is JSON
				$sparqlResults = json_decode($response->body, true);
				$results = $sparqlResults['results']['bindings'];
			}
			else {// is XML
				$xml = $response->body;
				$results = $this->SparqlEndpoint->parse_select_results($xml);
			}

			if (empty($results)){//throw exception
				throw new EmptyResponseException("The selector did not find data in the triple store");
			}
			else {		    
			    $expansionVariable = $responsePair['expansionVariable'];
			    if (!empty($expansionVariable) && strcmp($expansionVariable, 'item')){
			        $itemMap[$expansionVariable] = array();
			    }
			    
				foreach($results as $row){
					if(isset($row['item'])) $itemMap['item'][]=$row['item']['value'];
					if (!empty($expansionVariable) && isset($row[$expansionVariable]) && strcmp($expansionVariable, 'item')){
					    $itemMap[$expansionVariable][] = $row[$expansionVariable]['value'];
					}
				}
			}

		} else {//unsuccessful response

          logSparqlError("SELECT query in SparqlSelector.getItemMap()",
              $response, $this->selectQuery, $this->SparqlEndpoint->uri);

			throw new ErrorException("The SPARQL endpoint used by this URI configuration did not return a successful response.");
		}

		return $itemMap;
	}
	
	public function getSelectQuery(){
		return $this->selectQuery;
	}
}


?>