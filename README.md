# OPS LinkedDataApi

The Linked Data API for [Open PHACTS](http://www.openphacts.org/).

Based on the [Puelia PHP](https://code.google.com/p/puelia-php/) implementation
of the [Linked Data API](https://github.com/UKGovLD/linked-data-api/blob/wiki/Specification.md) specification.

This is a repository to host [API configuration files](api-config-files/)
 and code extensions

Requirements are php 5.2, with `php_xsl`, `lib_curl`, and `mod_rewrite` and `.htaccess` override enabled.

Make sure that Apache's `DocumentRoot` directory and (all parent directories from /var/ forward) are writable by the user running Apache

The sparql endpoint is set from environment variable `OPS_SPARQL_ENDPOINT` eg `export OPS_SPARQL_ENDPOINT=http://sparql-endpoint:8890/sparql` and read in `deployment.settings.php`.

The IMS API endpoint is set from environment variable `IMS_ENDPOINT` eg `export IMS_ENDPOINT=http://ims-endpoint:8000` and read in `deployment.settings.php`.

The OPS Search API endpoint is set from environment variable `OPS_SEARCH_ENDPOINT` eg `export OPS_SEARCH_ENDPOINT=http://somewhere.com:8839` and read in `deployment.settings.php`.

Memcache location is set from environment variable `MEMCACHE_ENDPOINT` eg `export MEMCACHE_ENDPOINT=localhost`.

By default memcache is not used, to enable it set environment variable `USE_MEMCACHE` eg `export USE_MEMCACHE=TRUE`.

You can also run on localhost without Apache using php -S localhost:3000. Note that this address will only work on localhost on your machine.

You can also run on localhost without Apache using php -S localhost:3000.

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
[Chemical Resolution Service](https://chemistry.openphacts.org/) (these are not
currently available as Docker containers):

 * `CRS` (default: https://crs/api/v1/)
 * `CONCEPTWIKI` (default: http://conceptwiki:8080/web-ws/concept)
 * `USE_MEMCACHE` (TRUE or FALSE - default FALSE)
 * `MEMCACHE_ENDPOINT` (default 'localhost')
 * `IMS_ENDPOINT` (default: 'localhost:3004')
 * `OPS_SPARQL_ENDPOINT` (default: none, assumed to be defined in the config settings for each API call)
 * `OPS_SEARCH_ENDPOINT` (default: none, assumed to be defined in the config settings for the OPS search API call)

The default values for `CRS` and `CONCEPTWIKI` access the aliases `crs` and `conceptwiki`, but
if you don't have your own installation of these services, you might use the
public services, by adding the following to `docker run`:

    --env CRS=https://chemistry.openphacts.org/api/v1/ \
    --env CONCEPTWIKI=http://www.conceptwiki.org/web-ws/concept

You can also add settings for memcache:

 * 'USE_MEMCACHE' (TRUE or FALSE - default FALSE)
 * 'MEMCACHE_ENDPOINT' (default 'localhost')

    --env USE_MEMCACHE=TRUE
    --env MEMCACHE_ENDPOINT=my-memcache

You can also tell the LDAPI where to find the IMS and the sparql endpoint:

    --env IMS_ENDPOINT=http://a.b.c:3000
    --env OPS_SPARQL_ENDPOINT=http://d.e.f:8000
    
You can also set the endpoint to use for searches that will use the  [OPS Search component](https://github.com/openphacts/ops-search/) via the LDAPI:

     --env OPS_SEARCH_ENDPOINT=http://localhost:8839
     
If `OPS_SEARCH_ENDPOINT` is not set then it will use whatever is in the config files.

If using memcache or the OPS Search component via docker then you need to ensure that both the LDAPI and these containers are on the same docker network:

  `docker network create ops-ldapi-network`

then you can refer to the `MEMCACHE_ENDPOINT` or `OPS_SEARCH_ENDPOINT` by it's internal docker network DNS eg `my-memcache`. OPS Search can also have the port included eg `ops-search:8839`.

### Example Docker run

To run (note that this example uses the deprecated docker `--link` command):

    docker run --name ops-linkeddataapi -p 8081:80 \
      --network=ops-ldapi-network \
      --env CRS=https://chemistry.openphacts.org/api/v1/ \
      --env CONCEPTWIKI=http://www.conceptwiki.org/web-ws/concept \
      --env OPS_SEARCH_ENDPOINT=http://localhost:8839 \
      --env USE_MEMCACHE=TRUE \
      --env MEMCACHE_ENDPOINT=my-memcache \
      --link memcached:memcached \
      --link identitymappingservice:ims \
      --link virtuoso:sparql \
      -d openphacts/ops-linkeddataapi

The above will expose the Open PHACTS Linked Data API on
[http://localhost:8081](http://localhost:8081) (or equivalent)
and link to existing Docker containers `memcached`, `identitymappingservice`
and `virtuoso` (which must already be running), and the
public APIs for [CRS](https://chemistry.openphacts.org/) and
[ConceptWiki](http://www.conceptwiki.org/). It will also tell the LDAPI to use the memcache container called `my-memcache` on the docker network `ops-ldapi-network`.

If using a docker network then there is no need to `link` the containers but you must ensure that they are on the same docker network. You can either attach the container at run time (as in the example above) or add it afterwards eg. `docker network connect ops-ldapi-network ops-linkeddataapi`.

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
