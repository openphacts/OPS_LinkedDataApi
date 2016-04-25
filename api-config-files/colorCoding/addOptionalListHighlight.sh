#!/bin/bash

unamestr=$(uname)
if [[ "$unamestr" == 'Linux' ]] 
then
	sed -i 's,~3,<span style=\\"BACKGROUND-COLOR: #FFCC99\\">,' "$1"
else
	sed -i '' 's,~3,<span style=\\"BACKGROUND-COLOR: #FFCC99\\">,' "$1"
fi

