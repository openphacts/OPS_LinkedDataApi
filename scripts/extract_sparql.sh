#!/bin/sh

config_dir="$1"
tmp_dir="$2"
output_dir="$3"
if [[ `diff -N --exclude=*.sh --exclude=*.bak --exclude=*.json --exclude=".*" --exclude="colorCoding" --exclude="docs" "$config_dir" "$tmp_dir"` ]] ; then
    today=`date "+%Y%m%d"`
    mkdir $output_dir/$today
    echo "Changes detected, dumping SPARQL queries to: $output_dir/$today"
    rm $tmp_dir/*.ttl
    cp $config_dir/*.ttl $tmp_dir/
    for file in $tmp_dir/*.ttl
    do
	echo "Processing: $file"
	urls=`grep "api:exampleRequestPath" $file  | sed -e 's,^.*api:exampleRequestPath *",http://ops2.few.vu.nl,' -e 's,".*$,\&_format=ttl\&_metadata=execution,'`
	for url in $urls
	do
		if [[ ! "$url" == *\?* ]] ; then
			url=`echo "$url" | sed 's,&,?,'`
		fi
		response=`curl -s -S $url`
		while [[ $response == "" ]]
		do
		    response=`curl -s -S $url`
		done
		if [[ "$response" == *\<html* ]] ; then
			echo "Turtle output not received from: $url"
		fi
		method_name=`echo $url | sed -e 's,http://ops2.few.vu.nl/,,' -e 's,/,_,g' -e 's,\?.*,,'`
		if [[ "$response" == *selectionQuery* ]] ; then
			filename="$method_name"_select
			oldname=$filename
			count=2
			while [[ -f $output_dir/$today/$filename.txt ]]
			do
				filename="$oldname"_"$count"
				((count++))
			done
			echo "$response" | sed -n '/^_:selectionQuery/,/""" ./p' | sed -e 's,_:selectionQuery rdf:value ,,' -e 's,"""[ \.]*,,' > $output_dir/$today/$filename.txt
		fi
		filename="$method_name"_view
		oldname=$filename
		count=2
		while [[ -f $output_dir/$today/$filename.txt ]]
		do
			filename="$oldname"_"$count"
			((count++))
		done
		echo "$response" | sed -n '/^_:viewingQuery/,/""" ./p' | sed -e 's,_:viewingQuery rdf:value ,,' -e 's,"""[ \.]*,,' > $output_dir/$today/$filename.txt
	done
    done
else
   echo "No changes detected"
fi
