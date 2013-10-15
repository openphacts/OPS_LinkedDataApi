<?php

$json = $DataGraph->to_simple_json($this->pageUri) ;
if($callback = $Request->getParam('_callback')){
#    $callback = preg_replace("/[^_a-zA-Z0-9\.]/", "", $callback);
    echo "{$callback}({$json})"; 
} else {
    echo $json;
}
?>
