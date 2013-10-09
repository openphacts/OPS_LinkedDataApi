#!/bin/bash
#$1 - server_name/version (no trailing slash) e.g. https://beta.openphacts.org/1.3
#$2 - path to api-config-files directory e.g. from the current directory use ../api-config-files

grep api:exampleRequestPath $2/*.ttl | cut -d '"' -f 2 >unitTestRequests
while read line
do
	url=$1$line'&app_id=81aaf1fe&app_key=17db324ee4f3552169ebcdfd3df8e7d8'
	curl -v -X GET "$url" >/dev/null
done <unitTestRequests
