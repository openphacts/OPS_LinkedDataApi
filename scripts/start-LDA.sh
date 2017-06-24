#!/usr/bin/env bash

    IRS_ENDPOINT=http://alpha.openphacts.org:8839 \
    IMS_ENDPOINT=http://alpha.openphacts.org:3004 \
    OPS_SPARQL_ENDPOINT=http://alpha.openphacts.org:8890/sparql \
php -S localhost:3008 -t $HOME/gh/openphacts/OPS_LinkedDataApi
