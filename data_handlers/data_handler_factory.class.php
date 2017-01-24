<?php

require_once 'data_handlers/2step_data_handler.class.php';
require_once 'data_handlers/item_data_handler.class.php';
require_once 'data_handlers/external_service_data_handler.class.php';
require_once 'data_handler_components/sparql_selector.class.php';
require_once 'data_handler_components/request_selector.class.php';
require_once 'data_handler_components/single_expansion_viewer.class.php';
require_once 'data_handler_components/multiple_expansion_viewer.class.php';

/*
 * Only used in LinkedDataApiResponse.process() to create the DataHandler.
 */

class DataHandlerFactory{
	
	public static function createListDataHandler($dataHandlerParams){		
		$sparqlSelector = new SparqlSelector($dataHandlerParams->Request, $dataHandlerParams->SparqlWriter, $dataHandlerParams->SparqlEndpoint);
		$singleExpansionViewer = new SingleExpansionViewer($dataHandlerParams);
		
		return new TwoStepDataHandler($sparqlSelector, $singleExpansionViewer);
	}
	
	public static function createBatchDataHandler($dataHandlerParams){
		$requestSelector = new RequestSelector($dataHandlerParams->Request);
		$multipleExpansionViewer = new MultipleExpansionViewer($dataHandlerParams);
		
		return new TwoStepDataHandler($requestSelector, $multipleExpansionViewer);
	}
	
	public static function createIntermediateExpansionDataHandler($dataHandlerParams){
		
		$sparqlSelector = new SparqlSelector($dataHandlerParams->Request, $dataHandlerParams->SparqlWriter, $dataHandlerParams->SparqlEndpoint);
		$multipleExpansionViewer = new MultipleExpansionViewer($dataHandlerParams, PAGINATION_ON);
		
		return new TwoStepDataHandler($sparqlSelector, $multipleExpansionViewer);
	}
	
	public static function createItemDataHandler($dataHandlerParams){
		return new ItemDataHandler($dataHandlerParams);
	}
	
	public static function createExternalServiceDataHandler($dataHandlerParams){
		return new ExternalServiceDataHandler($dataHandlerParams);
	}
	
}

?>