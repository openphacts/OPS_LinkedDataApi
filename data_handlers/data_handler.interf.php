<?php

/**
 * Interface DataHandlerInterface -- root class of DataHandlers.
 *
 * Hierarchy:
 * - DataHandlerInterface
 *     - OneStepDataHandler
 *         - ItemDataHandler
 *         - ExternalServiceDataHandler
 *     - TwoStepDataHandler
 *         - "List DataHandler": SparqlSelector + SingleExpansionViewer
 *         - "Batch DataHandler": RequestSelector + MultipleExpansionViewer
 *         - "IntermediateExpansion DataHandler": SparqlSelector + MultipleExpansionViewer
 */
interface DataHandlerInterface {

	function loadData();

}

?>
