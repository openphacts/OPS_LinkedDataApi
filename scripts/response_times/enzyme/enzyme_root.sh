#!/bin/bash

export TIMEFORMAT='%3R'

time curl -H 'Cache-Control: no-cache' "https://beta.openphacts.org/target/enzyme/root?app_id=be7ce5a9&app_key=bd7d4c9a98f16fb8f472f507f88b2b74" 2>/dev/null 
