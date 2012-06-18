<dt>Filtering on defined properties</dt>
<dd>
    <?php
        $properties = array(
		'param=value' => 'Select resources whose param has the specified value. "param" must be the api:name of a rdf:Property declared in the corresponding API configuration.',
		'min-param=value' => 'Select resources whose param is greater than or equal to the specified value',
		'max-param=value' => 'Select resources whose param is less than or equal to the specified value',
		'minEx-param=value' => 'Select resources whose param is greater than the specified value',
		'maxEx-param=value' => 'Select resources whose param is less than the specified value'
        );

    ?>


    <dl class="variables">
<?php foreach($properties as $key => $description):  
    ?>
    <dt><?php echo $key ?></dt>
    <dd><?php echo $description ?></dd>
<?php endforeach ?>
  </dl>
</dd>

<dt>Built-in Variables</dt>
<dd>
    <?php 
	$reservedParams = array(
        	'_page' => 'A number; the page that should be viewed' ,
                '_pageSize' => 'A number; the number of items per page',
                '_sort' => 'A comma-separated list of property paths to values that should be sorted on. A - prefix on a property path indicates a descending search',
                '_format'=> 'The api:name of the Formatter to use' ,
        	'_view' => 'The api:name of the Viewer to use' ,
        	'_template'=> 'A template to insert in the CONSTRUCT clause of the viewer' ,
        	'_where' => 'A "GroupGraphPattern?":http://www.w3.org/TR/rdf-sparql-query/#GroupPatterns (without the wrapping {}s) to insert in a SPARQL query',
        	'_orderBy' => 'A space-separated list of OrderConditions to insert in a SPARQL query',
        	'_select' => 'A SELECT clause to insert in a SPARQL query',
        	'_metadata' => 'A comma separated list of names of metadata graphs to show: site,formats,views,all,execution' ,
		'_lang' => 'A comma-separated list of languages (not used in OpenPHACTS)', 
	        'callback' => 'for JSONP'
        );

    ?>
        

    <dl class="variables">
<?php foreach($reservedParams as $key => $description): 
    ?>
    <dt><?php echo $key ?></dt>
    <dd><?php echo $description ?></dd>
<?php endforeach ?>
  </dl>
</dd>
