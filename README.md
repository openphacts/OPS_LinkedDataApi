# OPS LinkedDataApi

The Linked Data API for [Open PHACTS](http://www.openphacts.org/).

Based on the [Puelia PHP](https://code.google.com/p/puelia-php/) implementation
of the [Linked Data API](https://github.com/UKGovLD/linked-data-api/blob/wiki/Specification.md) specification.

This is a repository to host [API configuration files](api-config-files/)
 and code extensions

Requirements are php 5.2, with `php_xsl`, `lib_curl`, and `mod_rewrite` and `.htaccess` override enabled.

Make sure that Apache's `DocumentRoot` directory and (all parent directories from /var/ forward) are writable by the user running Apache

The sparql endpoint is set from an environment variable `export OPS_SPARQL_ENDPOINT=http://sparql-endpoint:8890/sparql` and read in `deployment.settings.php`.

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

## Swagger

The API includes a [Swagger](http://swagger.io/) definition in JSON
for the exposed services, available at `/swagger` - for example
http://localhost:8081/swagger

Note that the hostname and port returned as `basePath` here
depends on the URL the swagger file is accessed at. If you are
exposing the API via a proxy, the swagger definition might be
wrongly exposing the internal URI.

To override the `basePath`, set the `BASE_PATH` environment variable.

For example:

    docker run -p 8081:80 \
      --env BASE_PATH=http://cool.example.com/ \
      -d openphacts/ops-linkeddataapi
