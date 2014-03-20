<?php
$headers=array();
$lines=array();
$curr_line=array();

switch($this->ConfigGraph->getEndpointType()){
    case API.'ListEndpoint' : 
    case API.'IntermediateExpansionEndpoint':
    case API.'BatchEndpoint':
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

function getLine ($input, $curr_line, $prefix="", $parent_key="", $resource="") {
	global $headers, $curr_line, $lines;
        if (isset($input['isPrimaryTopicOf']) && !isset($curr_line['Request URL'])){
                $headers[] = 'Request URL';
                $curr_line['Request URL'] = $input['isPrimaryTopicOf'];
                unset($input['isPrimaryTopicOf']);
        }
	foreach ($input as $key => $value) {
                if (!is_array($value) && $key != 'inDataset') {
			$dataset_props=getDatasetProps($input['inDataset']);
			if (preg_match('/^[0-9][0-9]*$/', $key)){
				$key=$prefix.$parent_key;
				$value=getID($resource).':'.$value;
			}
			elseif ($key == '_about') {
				$uri=$value;
				$value=getID($value);
				if(!is_numeric($parent_key)) {
                                	if ($dataset_props !== FALSE)
						if ($parent_key=="") {
                                        		$key = $dataset_props . ' ID';
                                        		$prefix=$dataset_props . ':';
						}
                                		else {
							$key = $parent_key;
							$prefix=$dataset_props . ':';
							$key=$prefix.$key;
							$value=getID($resource).':'.$value;
						}
                               		elseif (isset($input['inDataset'])) {
                                        	$key = $input['inDataset'];
						$value=getID($resource).':'.$value;
                                	}
					else {
                                                $key = $prefix.$parent_key;
						if ($key!="") {
                                                	$value=getID($resource).':'.$uri;
						}
						elseif (count($curr_line)==0) {
							$key="URI";
							$value=$uri;
						}
                                        }
				}
				else {
                                        if($dataset_props !== FALSE) {
                                                $key = $parent_key;
                                                $prefix=$dataset_props . ':';
						$value=getID($resource).':'.$value;
                                        }
                                        elseif (isset($input['inDataset'])) {
                                                $key = $input['inDataset'];
						$value=getID($resource).':'.$value;
                                        }
					else {
						$key = $parent_key;
						$value=getID($resource).':'.$value;
					}
					$key=$prefix.$key;
				}
				$resource=$uri;
			}
			else {
				$key=$prefix.$key;
				$value=getID($resource).':'.$value;
			}
			if ($key!="" && $key!='_about' && !is_array($key) && (strpos($value, ":")===FALSE || substr($value, strpos($value, ":"))  != $resource ) ) {
				if (!in_array($key, $headers)) {
                                	$headers[] = $key;
                        	}
				elseif (isset($curr_line[$key]) && substr($curr_line[$key],0,1)!='{') {
					$value = '{'.$curr_line[$key].'}, {'.$value.'}';
				}
				elseif (isset($curr_line[$key])) {
					$value = $curr_line[$key] . ', {' . $value . '}';
				}
				$curr_line[$key]=$value;
			}
		}
	}
	foreach ($input as $key => $value) {
		if (is_numeric($key)) {
			$key=$parent_key;
		}
                if (is_array($value)) {
                        getLine($value, $curr_line, $prefix, $key, $resource);
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

function getID($uri){
	$id=FALSE;
	if (filter_var($uri, FILTER_VALIDATE_URL)) {
        	if (strpos($uri,'#')!==FALSE) {
                	$id = substr($uri, strrpos($uri, '#')+1, strlen($uri));
                } else {
                        $id = substr($uri, strrpos($uri, '/')+1, strlen($uri));
                }
        }
	return $id;
}

function getDatasetProps($dataset_uri) {
        $dataset_names = array (
                'http://linkedlifedata.com/resource/drugbank' => 'DrugBank',
                'http://ops.rsc-us.org' => 'OCRS',
                'http://purl.uniprot.org' => 'Uniprot',
                'http://purl.uniprot.org/enzyme' => 'EnzymeClassification',
                'http://www.conceptwiki.org' => 'ConceptWiki',
                'http://www.ebi.ac.uk/chebi' => 'ChEBI',
                'http://www.ebi.ac.uk/chembl' => 'ChEMBL',
                'http://www.geneontology.org' => 'GeneOntology',
                'http://www.openphacts.org/goa' => 'GOA',
                'http://www.wikipathways.org' => 'WikiPathways',
        );
        if (isset($dataset_names[$dataset_uri])){
                return $dataset_names[$dataset_uri];
        }
        return false;
}
//echo $json; 
?>
