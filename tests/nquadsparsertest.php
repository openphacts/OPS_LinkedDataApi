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
    
    function test_parse_correct_nquads_several_graphs(){
        $rdf = "<http://www.openphacts.org/api#compound1> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://purl.org/linked-data/api/vocab#API> <http://context1> .
<http://www.openphacts.org/api#compound2> <http://www.w3.org/2000/01/rdf-schema#label> \"Compound\"@en <http://context1> .
<http://www.openphacts.org/api#compound3> <http://purl.org/linked-data/api/vocab#sparqlEndpoint> <http://localhost:8890/sparql/> <http://context2> .";
        
        $this->graphStore->add_rdf($rdf);
        
        $testGraph1 = $this->graphStore->getGraph('http://context1');
        $this->assertTrue($testGraph1!=false);
        $this->assertTrue(count($testGraph1->get_subjects())==2);
        
        $obj1 = $testGraph1->get_first_resource('http://www.openphacts.org/api#compound1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type');     
        $this->assertEquals($obj1,'http://purl.org/linked-data/api/vocab#API');
        
        $testGraph2 = $this->graphStore->getGraph('http://context2');
        $this->assertTrue($testGraph2!=false);
        $this->assertTrue(count($testGraph2->get_subjects())==1);
        
        $obj2 = $testGraph1->get_first_literal('http://www.openphacts.org/api#compound2', 'http://www.w3.org/2000/01/rdf-schema#label');
        $this->assertEquals($obj2,'Compound');
        
        $testGraph3 = $this->graphStore->getGraph('http://context3');
        $this->assertTrue($testGraph3==false);
    }
    
    function test_merge_graphs_from_2_parsing_sessions(){
        $rdf1 = "<http://www.openphacts.org/api#compound1> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://purl.org/linked-data/api/vocab#API> <http://context1> .
<http://www.openphacts.org/api#compound2> <http://www.w3.org/2000/01/rdf-schema#label> \"Compound\"@en <http://context1> .
<http://www.openphacts.org/api#compound3> <http://purl.org/linked-data/api/vocab#sparqlEndpoint> <http://localhost:8890/sparql/> <http://context2> .";
        
        $rdf2 = "<http://www.openphacts.org/api#compound4> <http://purl.org/linked-data/api/vocab#contentNegotiation> <http://purl.org/linked-data/api/vocab#parameterBased> <http://context2> .
<http://www.openphacts.org/api#compound5> <http://purl.org/linked-data/api/vocab#variable> <http://rdf.farmbio.uu.se/chembl/onto/#organism> <http://context1> .";
        
        $this->graphStore->add_rdf($rdf1);
        $this->graphStore->add_rdf($rdf2);
        
        $testGraph1 = $this->graphStore->getGraph('http://context1');
        //test total number of subjects for first graph
        $this->assertTrue(count($testGraph1->get_subjects())==3);
        
        //test 1 triple existing before the merge
        $obj2 = $testGraph1->get_first_literal('http://www.openphacts.org/api#compound2', 'http://www.w3.org/2000/01/rdf-schema#label');
        $this->assertEquals($obj2,'Compound');
        
        //test 1 triple added after the merge
        $obj3 = $testGraph1->get_first_resource('http://www.openphacts.org/api#compound5', 'http://purl.org/linked-data/api/vocab#variable');
        $this->assertEquals($obj3,'http://rdf.farmbio.uu.se/chembl/onto/#organism');
        
        //test total number of subjects for second graph
        $testGraph2 = $this->graphStore->getGraph('http://context2');
        $this->assertTrue(count($testGraph2->get_subjects())==2);
        
        $obj4 = $testGraph2->get_first_resource('http://www.openphacts.org/api#compound3', 'http://purl.org/linked-data/api/vocab#sparqlEndpoint');
        $this->assertEquals($obj4, 'http://localhost:8890/sparql/');
                
        //test 1 triple added after the merge
        $obj5 = $testGraph2->get_first_resource('http://www.openphacts.org/api#compound4', 'http://purl.org/linked-data/api/vocab#contentNegotiation');
        $this->assertEquals($obj5, 'http://purl.org/linked-data/api/vocab#parameterBased');
    }
    
    function test_parse_incorrect_nquads_missing_context(){
        $rdf = "<http://www.openphacts.org/api#compound1> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://purl.org/linked-data/api/vocab#API> <http://context1> .
<http://www.openphacts.org/api#compound2> <http://www.w3.org/2000/01/rdf-schema#label> \"Compound\"@en <http://context1> .
<http://www.openphacts.org/api#compound3> <http://purl.org/linked-data/api/vocab#sparqlEndpoint>  .
<http://www.openphacts.org/api#compound4> <http://purl.org/linked-data/api/vocab#contentNegotiation> <http://purl.org/linked-data/api/vocab#parameterBased> <http://context2> .";
        
        //the parsing stops after the first 2 quads
        $this->graphStore->add_rdf($rdf);
        
        $testGraph1 = $this->graphStore->getGraph('http://context1');
        $this->assertTrue(count($testGraph1->get_subjects())==2);
        
        $testGraph2 = $this->graphStore->getGraph('http://context2');
        $this->assertTrue($testGraph2==false);
    }
    
    function test_parse_incorrect_nquads_missing_closing_angular_bracket(){
            $rdf = "<http://www.openphacts.org/api#compound1> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://purl.org/linked-data/api/vocab#API> <http://context1> .
        <http://www.openphacts.org/api#compound2> <http://www.w3.org/2000/01/rdf-schema#label> \"Compound\"@en <http://context1 .";
            
            //the parsing stops after the first 1 quad
            $this->graphStore->add_rdf($rdf);
            
            $testGraph1 = $this->graphStore->getGraph('http://context1');
            $this->assertTrue(count($testGraph1->get_subjects())==1);
    }
}

?>