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


?>