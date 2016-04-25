#!/bin/bash

export TIMEFORMAT='%3R'

for class in $(cat sample)
do
	time curl -H 'Cache-Control: no-cache' "https://beta.openphacts.org/target/enzyme/node?app_id=be7ce5a9&app_key=bd7d4c9a98f16fb8f472f507f88b2b74&uri=`echo -ne $class | xxd -plain | tr -d '\n' | sed 's/\(..\)/%\1/g'`" 2>/dev/null 
done
