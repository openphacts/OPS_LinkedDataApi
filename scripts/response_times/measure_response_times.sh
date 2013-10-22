for dir in $(find . -type d -name '??*')
do
	cd $dir
	for script in $(ls *.sh 2>/dev/null)
	do
		out_file=`echo $script | sed 's/\.sh/_times.txt/'`
		echo Generating: $out_file
		if [ -f $out_file ];
		then
			echo "File $out_file exists. Skipping"
		else
			source $script 1>/dev/null 2>>$out_file &
		fi
	done
	cd ..
done
