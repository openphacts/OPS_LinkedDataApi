#!/bin/bash
sed -i 's,\(<span[^;]*\);,\1</span>;,' $1
sed -i 's,\(<span[^;]*\)\.,\1</span>\.,' $1
#!/bin/bash

unamestr=$(uname)
if [[ "$unamestr" == 'Linux' ]] 
then
	sed -i 's,\(<span[^;]*\);,\1</span>;,' $1
	sed -i 's,\(<span[^;]*\)\.,\1</span>\.,' $1
else
	sed -i '' 's,\(<span[^;]*\);,\1</span>;,' $1
	sed -i '' 's,\(<span[^;]*\)\.,\1</span>\.,' $1
fi