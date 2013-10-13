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
	
	function getItemList(){
		$list = array();
		try {
			$this->selectQuery = $this->SparqlWriter->getSelectQueryForUriList();
			if(LOG_SELECT_QUERIES){
				logSelectQuery($this->Request, $this->selectQuery);
			}
		} catch (Exception $e){
			logError("Possible configuration error: ".$e->getMessage());
			throw $e;
		}

		logDebug($this->selectQuery);
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
				foreach($results as $row){
					if(isset($row['item'])) $list[]=$row['item']['value'];
				}
			}

		} else {//unsuccessful response
			logError("Endpoint returned {$response->status_code} {$response->body} Select Query <<<{$this->selectQuery}>>> failed against {$this->SparqlEndpoint->uri}");
			throw new ErrorException("The SPARQL endpoint used by this URI configuration did not return a successful response.");
		}

		return $list;
	}
	
	public function getSelectQuery(){
		return $this->selectQuery;
	}
}


?>