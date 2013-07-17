#!/bin/bash

unamestr=$(uname)
if [[ "$unamestr" == 'Linux' ]] 
then
	sed -i 's,~2,<span style=\\"BACKGROUND-COLOR: #FFFFCC\\">,' "$1"
else
	sed -i '' 's,~2,<span style=\\"BACKGROUND-COLOR: #FFFFCC\\">,' "$1"
fi

