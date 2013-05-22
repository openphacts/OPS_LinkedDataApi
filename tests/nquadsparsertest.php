<?php

require_once 'lda.inc.php';
require_once 'graphs/graphstore.class.php';
require_once 'lib/arc/ARC2.php';
require_once 'lib/arc/parsers/ARC2_NQuadsParser.php';

class NQuadsParserTest extends PHPUnit_Framework_TestCase {
    
    private $graphStore;
    
    function setUp(){
       $this->graphStore = new GraphStore(false);
    }
    
    function tearDown(){
    
    }
    
    function test_parse_correct_nquads_one_graph(){
        $rdf = "<http://www.openphacts.org/api#compound1> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://purl.org/linked-data/api/vocab#API> <http://context1> .
<http://www.openphacts.org/api#compound2> <http://www.w3.org/2000/01/rdf-schema#label> \"Compound\"@en <http://context1> .
<http://www.openphacts.org/api#compound3> <http://purl.org/linked-data/api/vocab#sparqlEndpoint> <http://localhost:8890/sparql/> <http://context2> .";
        
        $this->graphStore->add_rdf($rdf);
        
        $testGraph1 = $this->graphStore->getGraph('http://context1');
        $this->assertTrue($testGraph1!=false);
        $this->assertTrue(count($testGraph1->get_subjects())==2);
        
        $obj1 = $testGraph1->get_first_resource('http://www.openphacts.org/api#compound1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type');     
        print $obj1;
        $this->assertEquals($obj1,'http://purl.org/linked-data/api/vocab#API');
        
        $testGraph2 = $this->graphStore->getGraph('http://context2');
        $this->assertTrue($testGraph2!=false);
        $this->assertTrue(count($testGraph2->get_subjects())==1);
        
        $obj2 = $testGraph1->get_first_literal('http://www.openphacts.org/api#compound2', 'http://www.w3.org/2000/01/rdf-schema#label');
        $this->assertEquals($obj2,'Compound');
        
        $testGraph3 = $this->graphStore->getGraph('http://context3');
        $this->assertFalse($testGraph3!=false);
    }
    
    function test_merge_graphs_from_2_parsing_sessions(){
        
    }
    
    function test_parse_correct_nquads_several_graph(){
    
    }
    
    function test_parse_incorrect_nquads_missing_context(){
        
    }
    
    function test_parse_incorrect_nquads_(){
    
    }
}

?>