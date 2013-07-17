#!/bin/bash

unamestr=$(uname)
if [[ "$unamestr" == 'Linux' ]] 
then
	sed -i 's,~1,<span style=\\"BACKGROUND-COLOR: #33CC99\\">,' "$1"
else
	sed -i '' 's,~1,<span style=\\"BACKGROUND-COLOR: #33CC99\\">,' "$1"
fi
