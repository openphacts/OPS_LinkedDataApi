<?php

require_once 'graphs/linkeddataapigraph.class.php';

class GraphStore {
    
    private $config;
    private $graphArray = array();
    
    function __construct($config, $graphs=false)
    {
        $this->config = $config;
        if ($graphs){
            $this->graphArray = $graphs;
        }
    }
    
    public function add_rdf($rdf, $base=false){
        require_once 'lib/arc/parsers/ARC2_NQuadsParser.php';
        
        $nquadsParser = new ARC2_NQuadsParser(array(), $this);
        $nquadsParser->parse($base, $rdf);
        
        foreach ($nquadsParser->getGraphArray() as $graphName => $triples){
            if (!isset($this->graphArray[$graphName])){
                $this->graphArray[$graphName] = new LinkedDataApiGraph(false, $this->config);
            }
            $this->graphArray[$graphName]->_add_arc2_triple_list($triples);
        }
    }
    
    function addGraph($graphName, $newGraph){
        $this->graphArray[$graphName] = $newGraph;
    }
    
    function getGraph($graphName){
        if (isset($this->graphArray[$graphName])){
            return $this->graphArray[$graphName];
        }
        else
            return false;
    }
    
    function getGraphArray(){
        return $graphArray;
    }
}

?>