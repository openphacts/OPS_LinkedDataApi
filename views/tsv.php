<?php
$headers=array();
$lines=array();
$curr_line=array();

switch($this->ConfigGraph->getEndpointType()){
    case API.'ListEndpoint' : 
        $pageUri = $this->Request->getUriWithPageParam();
	$json = $DataGraph->to_simple_json($pageUri) ;
        $array = json_decode($json, true);
	writeOutput(getDataForList($array['result']['items']));
        break;
    /*case API.'ExternalHTTPService' :
	$pageUri = $this->Request->getUriWithPageParam();
        $json = $DataGraph->to_simple_json($pageUri) ;
        $array = json_decode($json, true);
        writeOutput(getDataForList($array['result']['items']));
        break;*/
    case API.'ItemEndpoint' :
    default:
        $pageUri = $this->Request->getUri();
	$json = $DataGraph->to_simple_json($pageUri) ;
	$array = json_decode($json, true);
	writeOutput(getDataForItem($array['result']['primaryTopic']));
        break;
}

function getDataForList ( $input ) {
        global $headers, $curr_line, $lines;
        getLines($input, array());
        $output = array();
        $output['headers'] = $headers;
        $output['lines'] = $lines;
        return $output;
}

function getDataForItem ( $input ) {
	global $headers, $curr_line, $lines;
	getLine($input, array());
	$lines[]=$curr_line;
	$output = array();
	$output['headers'] = $headers;
	$output['lines'] = $lines;
	return $output;
}

function getLines ($input, $curr_line) {
        global $headers, $curr_line, $lines;
        foreach ($input as $key => $value) {
                if (preg_match('/^[0-9][0-9]*$/', $key)) {
                        if (is_array($value)) {
                                getLine($value, $curr_line);
                                unset($input['$key']);
                                $lines[] = $curr_line;
                                $curr_line = array();
                        }
                }
        }
}

function getLine ($input, $curr_line) {
	global $headers, $curr_line, $lines;
	foreach ($input as $key => $value) {
		if (is_array($value)) {
			getLine($value, $curr_line);
		}
		elseif (preg_match('/^[0-9][0-9]*$/', $key)) {
			if (is_array($value)) {
				getLine($value, $curr_line);
				unset($input['$key']);
				$lines[] = $curr_line;
				$curr_line = array();
				var_dump($lines);
				getLine($input, $curr_line);
				break;
			}	
		}
		elseif ($key != 'inDataset' && !preg_match('/^[0-9][0-9]*$/', $key)){
			if ($key == '_about') {
				$key = $input['inDataset'];
			}
			if (!in_array($key, $headers)) {
	                	$headers[] = $key;
	                }
			$curr_line[$key]=$value;
		}
	}
}

function writeOutput ($processed_data) {
	foreach ($processed_data['headers'] as $col_name) {
		echo $col_name . '	';
	}
	echo '
';
	foreach ($processed_data['lines'] as $line) {
		foreach ($processed_data['headers'] as $col_name) {
			echo str_replace(array("\r", "\n", "\r\n"), " ", $line[$col_name] ) . '	';
		}
		echo '
';
	}
}
//echo $json; 
?>
