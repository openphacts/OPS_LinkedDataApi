#!/bin/bash
sed -i 's,\(<span[^;]*\);,\1</span>;,' $1
sed -i 's,\(<span[^;]*\)\.,\1</span>\.,' $1
