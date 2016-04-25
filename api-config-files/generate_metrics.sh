#!/bin/bash

if [ -z "$1" -o -z "$2" ]
then
echo "Run from the same directory as swagger-2.0.json . Usage : ./generate_metrics [version] [3scale-provider-key]"
exit 1
fi

if [ -f rules.lua.tmp ]
then
rm rules.lua.tmp
fi

if [ -f curl.tmp ]
then 
rm curl.tmp
fi

counter=1
for path in `grep '"/' swagger-2.0.json | sed -e 's,.*"/,/2.1/,' -e 's,".*,,' `
do
name=`sed -n 's,^ *"summary,,p' swagger-2.0.json|sed -e 's,[^"]*",,' -e 's,[^"]*",,' -e 's,\[PREVIEW\] ,,' -e 's,".*,,' | grep -v 'Activity Units for Type' | sed -n "$counter"p | sed -e 's,Information,Info,' -e 's,:,,' -e "s,$, ($1),"`
id=`echo $name | sed -e 's,.*,\L&,' -e 's,[()],,g' -e 's,[\. ],_,g' -e 's,_\([0-9]_[0-9]\),-\1,'`
echo 'curl -v  -X POST "https://openphacts-admin.3scale.net/admin/api/services/1006371755042/metrics/2555417663272/methods.xml" -d "provider_key='$2'&friendly_name='$name'&system_name='$id'&unit=hits"' >> curl.tmp
echo '
     local m =  ngx.re.match(path,[=[^'`echo $path | sed 's,\.,\\\.,'`'\?]=])
     if (m and (method == "GET" or method=="POST")) then
     -- rule: '$path' --
         table.insert(matched_rules, "'$path'")

         usage_t["'$id'"] = set_or_inc(usage_t, "'$id'", 1)
         found = true
     end

' >> rules.lua.tmp
counter=$((counter+1))
done
