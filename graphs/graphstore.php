<?php

class GraphStore {
    
    private $graphArray = array();
    
    function __construct($graphs=false)
    {
        if ($graphs){
            $this->graphArray = $graphs;
        }
    }
    
    function addGraph($graphName, $newGraph){
        $this->graphArray[$graphName] = $newGraph;
    }
    
    function getGraph($graphName){
        return $this->graphArray[$graphName];
    }
}

?>