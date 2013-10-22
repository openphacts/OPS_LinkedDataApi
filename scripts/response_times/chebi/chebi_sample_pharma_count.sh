#!/bin/bash

export TIMEFORMAT='%3R'

for cmpd in $(cat sample)
do
	time curl -H 'Cache-Control: no-cache' "https://beta.openphacts.org/compound/chebi/pharmacology/count?app_id=be7ce5a9&app_key=bd7d4c9a98f16fb8f472f507f88b2b74&uri=`echo -ne $cmpd | xxd -plain | tr -d '\n' | sed 's/\(..\)/%\1/g'`" 2>/dev/null | sed 's/[[:print:]]*PharmacologyTotalResults":\([0-9][0-9]*\)[[:print:]]*/\1/' >> chebi_sample_counts.txt
 done
