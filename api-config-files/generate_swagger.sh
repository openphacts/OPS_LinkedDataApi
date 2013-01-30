echo '{
  "basePath": "http://api.2445580102272.proxy.3scale.net:80",
  "apiVersion": "v1",
  "apis": [' 
for file in ./ops_*.ttl
do
	echo '    {'
	sed -n 's,[[:space:]]*api:uriTemplate[[:space:]]*,      "path": ,p' $file | sed 's/;/,/' | sed 's/[?{][[:print:]]*"/"/' 
	echo '      "operations": [
        {
          "httpMethod": "GET",'
	sed -n '/a [[:print:]]*Endpoint/,/api:name/s/[[:space:]]*api:name/          "summary": /p' $file | sed 's/;/,/' 
	sed -n '/a [[:print:]]*Endpoint/,/api:description/s/[[:space:]]*api:description/          "description": /p' $file | sed 's/;/,/'
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
		if [[ "$var" != "<#input>" ]]
		then
			echo '            {'
			sed -n "/^[[:space:]]*$var/s/[[:space:]]*[[:print:]]*api:name/              *name*: /p" $file | sed 's,*,",g' | sed 's/.$/,/'
			sed -n "/^[[:space:]]*$var/,/api:value/s/[[:space:]]*[[:print:]]*api:value/              *description*: /p" $file | sed 's,*,",g' | sed 's/[[:space:]]*.[[:space:]]*$/ ,/'
			if [[ "$var" == "_:act_type" ]]
			then
				echo '              "required": true,'
				echo '              "paramType": "path",'
				echo '              "dataType": "string"'
			elif [[ "$var" == "chembl-ops:normalisedValue" ]]
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
				echo '              "dataType": "string"'
			fi
	        	echo '            },'
		fi
	done
	if [[ `sed -n '/api:ListEndpoint/p' $file` ]]
	then
	      echo '            {
              "name": "_page",
              "description": "A number; the page that should be viewed",
              "dataType": "integer",
              "paramType": "query"
            },
            {
              "name": "_pageSize",
              "description": "Your access application key",
              "dataType": "integer",
              "paramType": "query"
            },'

	      echo '            {
              "name": "_orderBy",
              "description": "The desired variable to sort by. Multiple values can be specified seperated by spaces. Direction of sort can be specified with ASC(?var) and DESC(?var). Default is ascending",
              "allowableValues": {
                "values": ['
		for sparql_var in `sed 's,[[:space:]],\n,g' $file | sed 's/[(){}]//g' |sed -n '/^?/p' | sed 's/[\.;,]$//' | sort | uniq`
		do
			echo '                  "'$sparql_var'"',
			if [[ "$sparql_var" == `sed 's,[[:space:]],\n,g' $file | sed 's/[(){}]//g' |sed -n '/^?/p' | sed 's/[\.;,]$//' | sort | uniq | tail -n 1` ]]
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
              "name": "callback",
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
