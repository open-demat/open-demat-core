FROM alpine:3.20

ARG GARAGE_VERSION=v2.2.0

RUN apk add --no-cache ca-certificates wget \
    && wget -O /usr/local/bin/garage "https://garagehq.deuxfleurs.fr/_releases/${GARAGE_VERSION}/x86_64-unknown-linux-musl/garage" \
    && chmod +x /usr/local/bin/garage

COPY init-garage.sh /usr/local/bin/init-garage.sh

ENTRYPOINT ["/bin/sh", "/usr/local/bin/init-garage.sh"]
