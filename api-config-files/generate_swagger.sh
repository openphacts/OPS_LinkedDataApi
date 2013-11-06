echo '{
  "basePath": "https://beta.openphacts.org/1.3/",
  "apiVersion": "v1.3",
  "apis": [' 
for file in ./*.ttl
do
	echo '    {'
	path=`sed -n 's,[[:space:]]*api:uriTemplate[[:space:]]*,      "path": ,p' $file | sed 's/;/,/' | sed 's/[?{][[:print:]]*"/"/'`
	if [[ "$path"="/pharmacology/filters/units/" ]]
	then
		echo $path"{act_type}
	else
		echo $path
	fi
	echo '      "operations": [
        {
          "httpMethod": "GET",'
	sed -n '/a [[:print:]]*Endpoint/,/api:name/s/[[:space:]]*api:name/          "summary": /p' $file | sed 's/;/,/' 
        sed -n '/a api:ExternalHTTPService/,/api:name/s/[[:space:]]*api:name/          "summary": /p' $file | sed 's/;/,/'
	sed -n -e '/a [[:print:]]*Endpoint/,/api:description/s/[[:space:]]*api:description/          "description": /p' $file | sed 's/"[[:space:]]*[,.;][[:space:]]*$/<br><hr><br>Response template: <br><br><hr><br> /'| tr '\n' ' '
	sed -n -e '/a api:ExternalHTTPService/,/api:description/s/[[:space:]]*api:description/          "description": /p' $file | sed 's/"[[:space:]]*[,.;][[:space:]]*$/<br><hr><br>Response template: <br><br><hr><br> /' | tr '\n' ' '
        cat docs/`basename $file .ttl`.doc | sed -e 's,<,\&lt;,g' -e 's,>,\&gt;,g' -e 's,&lt;span,<span,' -e 's,"&gt;,">,' -e 's,&lt;/span&gt;,</span>,' -e 's,$,<br>,' -e 's,\t,\&nbsp;\&nbsp;\&nbsp;\&nbsp;,g' | tr '\n' ' ' | sed 's/$/",\
/'
	sed -n '/a api:API/,/rdfs:label/s/[[:space:]]*rdfs:label \([[:print:]]*\)"[[:print:]]*/          "group": \1"/p' $file | sed 's/$/ ,/'
        echo '          "parameters": ['
	inputLine=`sed -n '/<#input>/p' $file`
	if [[ "$inputLine" != "" ]]
	then
		echo '            {'
	        sed -n "/^[[:space:]]*<#input>/s/[[:space:]]*[[:print:]]*api:name/              *name*: /p" $file | sed 's,*,",g' | sed 's/.$/,/'
	        sed -n "/^[[:space:]]*<#input>/,/api:value/s/[[:space:]]*[[:print:]]*api:value/              *description*: /p" $file | sed 's,*,",g' | sed 's/[[:space:]]*.[[:space:]]*$/ ,/'
	        echo '              "dataType": "string",'
		echo '              "required": true,'
		echo '              "paramType": "query"'
		echo '            },'
	fi
	echo '            {
              "name": "app_id",
              "description": "Your access application id",
              "dataType": "string",
              "paramType": "query",
              "threescale_name": "app_ids"
            },
            {
              "name": "app_key",
              "description": "Your access application key",
              "dataType": "string",
              "paramType": "query",
              "threescale_name": "app_keys"
            },'
	vars=`sed -n 's/[[:space:]]*api:variable[[:space:]]*//p' $file | sed 's,[[:space:]]*;[[:space:]]*$,,'`
	for var in `sed -n 's/[[:space:]]*api:variable[[:space:]]*//p' $file | sed 's,[[:space:]]*;[[:space:]]*$,,' | grep -v '/'`
	do
                if [[ `echo $var | sed 's,:[[:print:]]*$,,'` == ops_api ]]
                then    
                        for sub_var in `sed -n "/^[[:space:]]*$var/,/^[[:space:]]*$/p" $file | sed -n 's,[[:space:]]*api:subType[[:space:]]*,,p'  | sed 's,[[:space:]]*.[[:space:]]*$,,'`
                        do      
				echo '            {'
				echo '              "name": "'`sed -n "/$var[[:space:]]*api:name/p" $file | sed 's/[[:print:]]*[[:space:]][[:space:]]*"//' | sed 's/"[[:print:]]*//'`.`echo $sub_var | sed 's,^[[:print:]]*:,,'`'",'
				sed -n "/^[[:space:]]*$sub_var/,/api:value/s/[[:space:]]*[[:print:]]*api:value/              *description*: /p" $file | sed 's,*,",g' | sed 's/[[:space:]]*.[[:space:]]*$/ ,/'
                                echo '              "paramType": "query",'
				if [[ "$sub_var" == "ops_api_search:Molecule" ]]
				then
					echo '              "required": true,'
				fi
                                echo '              "dataType": "string"'
				echo '            },'
                        done    
		elif [[ "$var" != "<#input>" ]]
		then
			echo '            {'
			sed -n "/^[[:space:]]*$var/s/[[:space:]]*[[:print:]]*api:name/              *name*: /p" $file | sed 's,*,",g' | sed 's/.$/,/'
			sed -n "/^[[:space:]]*$var/,/api:value/s/[[:space:]]*[[:print:]]*api:value/              *description*: /p" $file | sed 's,*,",g' | sed 's/[[:space:]]*.[[:space:]]*$/ ,/'
			if [[ "$var" == "_:act_type" ]]
			then
				echo '              "required": true,'
				echo '              "paramType": "path",'
				echo '              "dataType": "string"'
			elif [[ "$var" == "chembl:standardValue" || "$var" == "chembl:pChembl" ]]
			then
				echo '              "dataType": "double",'
				echo '              "paramType": "query"'
				echo '            },'
				echo '            {'
				sed -n "/^[[:space:]]*$var/s/[[:space:]]*[[:print:]]*api:name/              *name*: /p" $file | sed 's,*,",g' | sed 's/.$/,/' | sed 's,\"name\":[[:space:]]*\",&min-,'
				sed -n "/^[[:space:]]*$var/,/api:value/s/[[:space:]]*[[:print:]]*api:value/              *description*: /p" $file | sed 's,*,",g' | sed 's/[[:space:]]*.[[:space:]]*$/ ,/' | sed 's/equal/greater than or &/'
				echo '              "dataType": "double",'
                                echo '              "paramType": "query"'
                                echo '            },'
                                echo '            {'
                                sed -n "/^[[:space:]]*$var/s/[[:space:]]*[[:print:]]*api:name/              *name*: /p" $file | sed 's,*,",g' | sed 's/.$/,/' | sed 's,\"name\":[[:space:]]*\",&minEx-,'
                                sed -n "/^[[:space:]]*$var/,/api:value/s/[[:space:]]*[[:print:]]*api:value/              *description*: /p" $file | sed 's,*,",g' | sed 's/[[:space:]]*.[[:space:]]*$/ ,/' | sed 's/equal to/greater than/'
                                echo '              "dataType": "double",'
                                echo '              "paramType": "query"'
                                echo '            },'
                                echo '            {'
                                sed -n "/^[[:space:]]*$var/s/[[:space:]]*[[:print:]]*api:name/              *name*: /p" $file | sed 's,*,",g' | sed 's/.$/,/' | sed 's,\"name\":[[:space:]]*\",&max-,'
                                sed -n "/^[[:space:]]*$var/,/api:value/s/[[:space:]]*[[:print:]]*api:value/              *description*: /p" $file | sed 's,*,",g' | sed 's/[[:space:]]*.[[:space:]]*$/ ,/' | sed 's/equal/less than or &/'
                                echo '              "dataType": "double",'
                                echo '              "paramType": "query"'
                                echo '            },'
                                echo '            {'
                                sed -n "/^[[:space:]]*$var/s/[[:space:]]*[[:print:]]*api:name/              *name*: /p" $file | sed 's,*,",g' | sed 's/.$/,/' | sed 's,\"name\":[[:space:]]*\",&maxEx-,'
                                sed -n "/^[[:space:]]*$var/,/api:value/s/[[:space:]]*[[:print:]]*api:value/              *description*: /p" $file | sed 's,*,",g' | sed 's/[[:space:]]*.[[:space:]]*$/ ,/' | sed 's/equal to/less than/'
                                echo '              "dataType": "double",'
                                echo '              "paramType": "query"'
			else 
				echo '              "paramType": "query",'
				if [[ "$var" == "<#smiles>" || "$var" == "<#uuid>" || "$var" == "<#q>" || "$var" == "<#URL>" || "$var" == "chemspider:inchikey" || "$var" == "chemspider:inchi" ]]
				then
					echo '              "required": true,'
				fi
                                echo '              "dataType": "string"'
			fi
	        	echo '            },'
		fi
	done
	if [[ `sed -n '/api:ListEndpoint/p' $file` ]] || [[ `sed -n '/api:IntermediateExpansionEndpoint/p' $file` ]]
	then
	      echo '            {
              "name": "_page",
              "description": "A number; the page that should be viewed",
              "dataType": "integer",
              "paramType": "query"
            },
            {
              "name": "_pageSize",
              "description": "The desired page size. Set to 'all' to retrieve all results in a single page.",
              "dataType": "integer",
              "paramType": "query"
            },'

	      echo '            {
              "name": "_orderBy",
              "description": "The desired variable to sort by. Multiple values can be specified seperated by spaces. Direction of sort can be specified with ASC(?var) and DESC(?var). Default is ascending",
              "allowableValues": {
                "values": ['
		for sparql_var in `sed 's,[[:space:]],\n,g' $file | sed 's/[(){}]//g' |sed -n '/^?/p' | sed 's/[\.;,]$//' | grep -v '</span>' | sort | uniq`
		do
			echo '                  "'$sparql_var'"',
			if [[ "$sparql_var" == `sed 's,[[:space:]],\n,g' $file | sed 's/[(){}]//g' |sed -n '/^?/p' | sed 's/[\.;,]$//' | grep -v '</span>' | sort | uniq | tail -n 1` ]]
			then
				echo '                  "DESC('$sparql_var')"'
			else
				echo '                  "DESC('$sparql_var')"', 
			fi
		done
              echo '                ],
                "valueType": "LIST"
              },
              "dataType": "string",
              "required": false,
              "paramType": "query"
            },'
	fi

	if [[ ! `sed -n '/api:ExternalHTTPService/p' $file` ]]
	then
		echo '            {
              "name": "_lens",
              "description": "The Lens name",
              "dataType": "string",
              "paramType": "query"
            },'
	fi

	echo '            {
              "name": "_format",
              "description": "The desired result format.",
              "allowableValues": {
                "values": [
                  "json",
                  "tsv",
                  "ttl",
                  "xml",
                  "rdf",
                  "rdfjson",
                  "html"
                ],
                "valueType": "LIST"
              },
              "dataType": "string",
              "required": false,
              "paramType": "query"
            },
            {
              "name": "_callback",
              "description": "For JSONP",
              "dataType": "string",
              "paramType": "query"
            },
            {
              "name": "_metadata",
              "description": "Additional metadata to be included with response.",
              "allowableValues": {
                "values": [
                  "execution",
                  "site",
                  "formats",
                  "views",
                  "all"
                ],
                "valueType": "LIST"
              },
              "dataType": "string",
              "required": false,
              "paramType": "query"
            }
          ]
        }
      ]
    },'
done
echo '  ]
}'
