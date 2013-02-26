<?php

define(CONCEPTWIKI_PREFIX, 'http://www.conceptwiki.org/concept/');

function addLabelWithLanguage($node, $label, $dataGraph){
    $language=null;
    if (isset($label->{"language"})){
        $language = $label->{"language"}->{"code"};
    }

    if ($label->{"type"} === 'PREFERRED'){
        $dataGraph->add_literal_triple($node, SKOS.'prefLabel', $label->{"text"}, $language);
    }
    else {
        $dataGraph->add_literal_triple($node, SKOS.'altLabel', $label->{"text"}, $language);
    }
}

function addConceptWithLabels($bNode, $jsonElement, $dataGraph){
    $dataGraph->add_resource_triple($bNode, CONCEPTWIKI_PREFIX.'#uuid', $jsonElement->{'uuid'});
    foreach($jsonElement->{'labels'} as $label){
        addLabelWithLanguage($bNode, $label, $dataGraph);
    }
     
    $dataGraph->add_literal_triple($bNode, CONCEPTWIKI_PREFIX.'#deleted',
            (bool)$jsonElement->{'deleted'} ? 'true' : 'false',
            null, XSD.'boolean');
}


?>