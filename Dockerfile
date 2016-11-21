FROM php:5.6-apache
MAINTAINER Stian Soiland-Reyes <soiland-reyes@cs.manchester.ac.uk>

# Install apache/PHP for REST API
WORKDIR /usr/src
RUN tar xJfv php.tar.xz 

RUN ln -s php-* php && cd /usr/src/php/ext/
WORKDIR /tmp
RUN curl -L http://pecl.php.net/get/memcached | tar zxfv - && mv memcached-* /usr/src/php/ext/memcached
RUN curl -L http://pecl.php.net/get/memcache | tar zxfv - && mv memcache-* /usr/src/php/ext/memcache
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
	libcurl4-openssl-dev libxslt1-dev libmemcached-dev libz-dev && \
  docker-php-ext-install xsl memcache memcached
# curl and json already installed?  
RUN a2enmod rewrite

RUN rm -rf /var/www/html
#Install Linked Data API
ADD . /var/www/html
WORKDIR /var/www/html


#### Hostname modifications


# SPARQL server
RUN sed -i "s,<http://[^>]*/sparql/>,<http://sparql:8890/sparql/>,g" api-config-files/*ttl
# http://ops-ims-15:8080/QueryExpander
RUN sed -i "s,http://[^>]*/QueryExpander/,http://ims:8080/QueryExpander/,g" api-config-files/*ttl
RUN sed -i "s|'IMS_MAP_ENDPOINT'.*|'IMS_MAP_ENDPOINT', 'http://ims:8080/QueryExpander/mapBySetRDF');|" deployment.settings.php
RUN sed -i "s|'IMS_EXPAND_ENDPOINT'.*|'IMS_EXPAND_ENDPOINT', 'http://ims:8080/QueryExpander/expandXML?query=');|" deployment.settings.php
RUN sed -i "s|'PUELIA_MEMCACHE_HOST'.*|'PUELIA_MEMCACHE_HOST', 'memcached');|" deployment.settings.php
# Safe local hostnames by default
RUN sed -i "s,http://[^/]*/web-ws/concept,http://conceptwiki:8080/web-ws/concept,g" api-config-files/*ttl
RUN sed -i "s,http.*/JSON.ashx,http://crs/JSON.ashx,g" api-config-files/*ttl deployment.settings.php
## but will be replaced with system environment variables:

#ENV CONCEPTWIKI http://www.conceptwiki.org/web-ws/concept
ENV CONCEPTWIKI http://conceptwiki:8080/web-ws/concept
#ENV CRS http://ops2.rsc.org/ops/JSON.ashx
ENV CRS https://crs/api/v1/
# ..as injected by the entrypoint
ADD docker-entrypoint.sh /
RUN chmod 755 /docker-entrypoint.sh
ENTRYPOINT ["/docker-entrypoint.sh"]
# For some reason need to repeat the CMD
CMD ["apache2-foreground"]


# Silence warnings (Issue #13)
RUN echo "display_errors=0" > /usr/local/etc/php/conf.d/ops-warnings.ini
RUN echo "log_errors=1" >> /usr/local/etc/php/conf.d/ops-warnings.ini
RUN echo "html_errors=0" >> /usr/local/etc/php/conf.d/ops-warnings.ini

# Fixes https://github.com/openphacts/GLOBAL/issues/292
RUN echo "[Pcre]" > /usr/local/etc/php/conf.d/ops-pcre.ini
RUN echo "pcre.backtrack_limit=100000000" >> /usr/local/etc/php/conf.d/ops-pcre.ini
RUN echo "pcre.recursion_limit=100000000" >> /usr/local/etc/php/conf.d/ops-pcre.ini


#RUN sed -i '/<\/VirtualHost/ i\ <Directory /var/www/html/>\n  AllowOverride All\n </Directory>' /etc/apache2/sites-available/000-default.conf
#RUN ln -s /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-enabled/000-default.conf

#RUN cat /etc/apache2/apache2.conf | tr "\n" "|||" | \
#      sed 's,\(<Directory /var/www/html/>[^<]*\)AllowOverride None\([^<]*</Directory>\),\1AllowOverride All\2,' | \
#      sed 's/|||/n/g' >/tmp/apache2 && \
#    mv /tmp/apache2 /etc/apache2/apache2.conf
RUN mkdir /var/www/html/logs /var/www/html/cache && \
    chmod 777 /var/www/html/logs /var/www/html/cache && \
    chown -R www-data:www-data /var/www/html



