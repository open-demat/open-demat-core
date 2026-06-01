FROM debian:bookworm-slim

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        apache2 \
        ca-certificates \
        libapache2-mod-shib \
        openssl \
        shibboleth-sp-utils \
    && a2enmod headers proxy proxy_http rewrite shib \
    && a2dissite 000-default \
    && rm -rf /var/lib/apt/lists/*

COPY apache-vhost.conf /etc/apache2/sites-available/open-demat-shibboleth.conf
COPY entrypoint.sh /usr/local/bin/shibboleth-entrypoint

RUN openssl req \
        -newkey rsa:2048 \
        -nodes \
        -keyout /etc/shibboleth/sp-key.pem \
        -x509 \
        -days 3650 \
        -out /etc/shibboleth/sp-cert.pem \
        -subj "/CN=open-demat-dev-sp" \
    && a2ensite open-demat-shibboleth \
    && chmod +x /usr/local/bin/shibboleth-entrypoint

EXPOSE 80

CMD ["/usr/local/bin/shibboleth-entrypoint"]
