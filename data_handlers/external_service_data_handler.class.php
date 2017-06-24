<?php


require_once 'lda.inc.php';
require_once 'data_handlers/1step_data_handler.class.php';

class ExternalServiceDataHandler extends OneStepDataHandler{

	private $useDatastore = false;
	private $pageUri = false;

	function __construct(DataHandlerParams $dataHandlerParams) {
		parent::__construct($dataHandlerParams);
	}

	function loadData(){
		$uriWithoutExtension = $this->Request->getOrderedUriWithoutExtensionAndReservedParams();
		logDebug("Generating graph name from: {$uriWithoutExtension}");
		$graphName = OPS_API.'/'.hash("sha256", $uriWithoutExtension);

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
              logSparqlError("VIEW query in ExternalServiceDataHandler.loadData()",
                  $response, $this->viewQuery, $this->SparqlEndpoint->uri);
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
		if (empty($response)){
		    throw new EmptyResponseException("No results returned from the backing service");
		}

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
          $msg = "INSERT query in ExternalServiceDataHandler.insertRDFDataIntoTripleStore(graphName={$graphName})";
          logSparqlError($msg,
              $response, $insertQuery, $this->SparqlEndpoint->uri);

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
