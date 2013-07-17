#!/bin/bash

unamestr=$(uname)
if [[ "$unamestr" == 'Linux' ]] 
then
	sed -i 's,~0,<span style=\\"BACKGROUND-COLOR: #66FFCC\\">,' "$1"
else
	sed -i '' 's,~0,<span style=\\"BACKGROUND-COLOR: #66FFCC\\">,' "$1"
fi
