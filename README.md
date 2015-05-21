# OPS LinkedDataApi

A repository to host API configuration files, and code extensions

Requirements are php 5.2, with `php_xsl`, `lib_curl`, and `mod_rewrite` and `.htaccess` override enabled.

Make sure that Apache's `DocumentRoot` directory and (all parent directories from /var/ forward) are writable by the user running Apache

## Docker image

This application is also available as a [Docker](https://www.docker.com/) image: .
[openphacts/ops-linkeddataapi/](https://registry.hub.docker.com/u/openphacts/ops-linkeddataapi/)

This image relies on linking to these other docker images as the following aliases:
 * `memcached`: [memcached](https://registry.hub.docker.com/_/memcached)
 * `ims`: [openphacts/identitymappingservice/](https://registry.hub.docker.com/u/openphacts/identitymappingservice/)
 * `sparql`: [stain/virtuoso](https://registry.hub.docker.com/u/stain/virtuoso/)

Details about starting and populating those are provided at their respective READMEs.

This image will provide the Open PHACTS API on HTTP port `80`, which can be exposed
using the `-p` docker parameter.

Running:
  
    docker run --name ops-linkeddataapi -p 8081:80 \
      --link memcached:memcached \
      --link identitymappingservice:ims \
      --link virtuoso:sparql \
      -d openphactsops-linkeddataapi

The above will expose the Open PHACTS Linked Data API on 
[http://localhost:8081](http://localhost:8081) (or equivalent).
