# OPS LinkedDataApi

A repository to host API configuration files, and code extensions

Requirements are php 5.2, with `php_xsl`, `lib_curl`, and `mod_rewrite` and `.htaccess` override enabled.

Make sure that Apache's `DocumentRoot` directory and (all parent directories from /var/ forward) are writable by the user running Apache

## Docker image

This application is also available as a [Docker](https://www.docker.com/) image:
[openphacts/ops-linkeddataapi](https://registry.hub.docker.com/u/openphacts/ops-linkeddataapi/)

This image will provide the Open PHACTS API on HTTP port `80`, which can be exposed
using the `-p` docker parameter.

This image relies on linking to these other docker images as the following aliases:

 * `memcached`: [memcached](https://hub.docker.com/_/memcached/)
 * `ims`: [openphacts/identitymappingservice/](https://hub.docker.com/r/openphacts/identitymappingservice/)
 * `sparql`: [stain/virtuoso](https://hub.docker.com/r/stain/virtuoso/)

Details about starting and populating those are provided at their respective READMEs.
The simplest option to start up the Open PHACTS platform is usually the
[ops-platform-setup](https://github.com/openphacts/ops-platform-setup/tree/master/docker) configuration for [Docker Compose](https://docs.docker.com/compose/).

Additionally these environment variables define the external services for
[ConceptWiki](http://conceptwiki.org/) and
[Chemical Resolution Service](https://ops.rsc.org/) (these are not
currently available as Docker containers):

 * `CRS` (default: https://crs/api/v1/)
 * `CONCEPTWIKI` (default: http://conceptwiki:8080/web-ws/concept)

The default values for these access the aliases `crs` and `conceptwiki`, but
if you don't have your own installation of these services, you might use the
public services, by adding the following to `docker run`:

    --env CRS=https://ops.rsc.org/api/v1/ \
    --env CONCEPTWIKI=http://www.conceptwiki.org/web-ws/concept

### Example Docker run

To run:

    docker run --name ops-linkeddataapi -p 8081:80 \
      --env CRS=https://ops.rsc.org/api/v1/ \
      --env CONCEPTWIKI=http://www.conceptwiki.org/web-ws/concept \
      --link memcached:memcached \
      --link identitymappingservice:ims \
      --link virtuoso:sparql \
      -d openphacts/ops-linkeddataapi

The above will expose the Open PHACTS Linked Data API on
[http://localhost:8081](http://localhost:8081) (or equivalent)
and link to existing Docker containers `memcached`, `identitymappingservice`
and `virtuoso` (which must already be running), and the
public APIs for [CRS](https://ops.rsc.org/) and
[ConceptWiki](http://www.conceptwiki.org/).
