<?php


require_once 'lda.inc.php';
require_once 'data_handlers/1step_data_handler.class.php';

class ExternalServiceDataHandler extends OneStepDataHandler{
	
	private $useDatastore = false;
	private $pageUri = false;
	private $endpointUrl = '';
	
	function __construct($Request, $ConfigGraph, $DataGraph, $Viewer, $SparqlWriter, $SparqlEndpoint, $endpointUrl) {
		parent::__construct($Request, $ConfigGraph, $DataGraph, $Viewer, $SparqlWriter, $SparqlEndpoint);
		$this->endpointUrl = $endpointUrl;
	}
	
	function loadData(){
		$uriWithoutExtension = $this->Request->getOrderedUriWithoutExtensionAndReservedParams();
		logDebug("Generating graph name from: {$uriWithoutExtension}");
		$graphName = OPS_API.'/'.hash("crc32", $uriWithoutExtension);
		
		$checkDatastore = $this->decideToCheckTripleStore($uriWithoutExtension);
		if ($checkDatastore==true){
			//build query
			$this->pageUri = $this->Request->getUriWithoutPageParam();
		
			$this->viewQuery = $this->SparqlWriter->getViewQueryForExternalService($graphName, $this->pageUri, $this->viewerUri);
			if (LOG_VIEW_QUERIES) {
				logViewQuery($this->Request, $this->viewQuery);
			}
		
			//query the data store
			$response = $this->SparqlEndpoint->graph($this->viewQuery, PUELIA_RDF_ACCEPT_MIMES);
			if ($response->is_success()){
				$this->DataGraph->add_rdf($response->body);
		
				if (!$this->DataGraph->is_empty()){//no data returned
					$this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);
					//we have data in the datastore, so serve it directly
					return;
				}
				else{
					logDebug("Data not found at: {$this->SparqlEndpoint->uri}, going to external service");
				}
			}
			else{
				logError("Endpoint returned {$response->status_code} {$response->body} View Query <<<{$this->viewQuery}>>> failed against {$this->SparqlEndpoint->uri}");
			}
		}
		
		//match api:uriTemplate, extract parameters and fill in api:externalRequestTemplate
		$externalServiceRequest = $this->ConfigGraph->getExternalServiceRequest();
		logDebug("External service request: ".$externalServiceRequest);
		$rdfData = $this->retrieveRDFDataFromExternalService($externalServiceRequest, '');
		
		if ($this->useDatastore){
			$this->insertRDFDataIntoTripleStore($graphName, $rdfData);
		}
	}
	
	private function decideToCheckTripleStore($pathWithoutExtension){
		$this->useDatastore = $this->ConfigGraph->get_first_literal($this->ConfigGraph->getEndpointUri(), API.'enableCache');
		$this->useDatastore = $this->useDatastore==='true' ? true:false;
	
		return $this->useDatastore;
	}
	
	private function retrieveRDFDataFromExternalService($externalServiceRequest, $rdfData){
		//make request to external service
		$ch = curl_init($externalServiceRequest);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, array ("Accept: application/xml, application/json"));
	
		$fullResponse = curl_exec($ch);
	
		if ($fullResponse==false){
			throw new ErrorException("Request: ".$externalServiceRequest." failed");
		}
	
		if (curl_getinfo($ch, CURLINFO_HTTP_CODE)===HTTP_No_Content){
			throw new EmptyResponseException("Request: ".$externalServiceRequest." returned an empty response");
		}
	
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$response = substr($fullResponse, $headerSize);
	
		//call the appropriate converter by checking api:externalResponseHandler
		$this->pageUri = $this->Request->getUriWithoutPageParam();
		$externalResponseHandler = $this->ConfigGraph->get_first_literal($this->ConfigGraph->getEndpointUri(), API.'externalResponseHandler');
	
		require $externalResponseHandler;
	
		curl_close($ch);
		return $rdfData;
	}
	
	private function insertRDFDataIntoTripleStore($graphName, $rdfData){
		//insert new RDF data in the triple store
		$insertQuery = $this->SparqlWriter->getInsertQueryForExternalServiceData($rdfData, $graphName);
	
		$response = $this->SparqlEndpoint->insert($insertQuery, PUELIA_SPARQL_ACCEPT_MIMES);
		if(!$response->is_success()){
			logError("Endpoint returned {$response->status_code} {$response->body} Insert Query <<<{$insertQuery}>>> failed against {$this->SparqlEndpoint->uri}");
			//even if insert fails we go ahead an give the data to the client
		}
		else{
			logDebug("Created new graph: ".$graphName." for the request ".$this->Request->getUri());
		}
	}
	
	function getPageUri(){
		return $this->pageUri;
	}
	
	
}

?>