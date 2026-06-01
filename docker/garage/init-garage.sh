#!/bin/sh
set -eu

CONFIG_FILE="${GARAGE_CONFIG_FILE:-/etc/garage.toml}"
BUCKET="${GARAGE_BUCKET:-documents}"
KEY_NAME="${GARAGE_KEY_NAME:-open-demat-core}"
CAPACITY="${GARAGE_CAPACITY:-1G}"
RECREATE_KEY="${GARAGE_RECREATE_KEY:-0}"

garage_cmd() {
  garage -c "$CONFIG_FILE" "$@"
}

echo "Waiting for Garage..."
until garage_cmd status >/tmp/garage-status.txt 2>/tmp/garage-status.err; do
  sleep 2
done

NODE_ID="$(awk '
  /^[0-9a-fA-F]{16,64}@/ {
    split($1, parts, "@");
    print parts[1];
    exit;
  }
  /^[0-9a-fA-F]{16,64}[[:space:]]/ {
    print $1;
    exit;
  }
' /tmp/garage-status.txt)"

if [ -z "$NODE_ID" ]; then
  echo "Unable to detect Garage node id from:"
  cat /tmp/garage-status.txt
  exit 1
fi

echo "Garage node: $NODE_ID"

if ! garage_cmd layout show | grep -q "$NODE_ID"; then
  garage_cmd layout assign -z dc1 -c "$CAPACITY" "$NODE_ID"
  LAYOUT_VERSION="$(garage_cmd layout show | awk '/Current cluster layout version:/ { print $5 }')"
  NEXT_VERSION="${LAYOUT_VERSION:-0}"
  NEXT_VERSION=$((NEXT_VERSION + 1))
  garage_cmd layout apply --version "$NEXT_VERSION"
fi

garage_cmd bucket create "$BUCKET" 2>/dev/null || true

KEY_CREATED=0
if garage_cmd key info "$KEY_NAME" >/tmp/garage-key-info.txt 2>/dev/null; then
  if [ "$RECREATE_KEY" = "1" ]; then
    garage_cmd key delete --yes "$KEY_NAME"
    garage_cmd key create "$KEY_NAME" >/tmp/garage-key.txt
    KEY_CREATED=1
  fi
else
  garage_cmd key create "$KEY_NAME" >/tmp/garage-key.txt
  KEY_CREATED=1
fi

garage_cmd bucket allow --read --write --owner "$BUCKET" --key "$KEY_NAME" 2>/dev/null || true

echo
echo "Garage bucket ready: $BUCKET"
if [ "$KEY_CREATED" = "1" ]; then
  echo "Garage key created:"
  cat /tmp/garage-key.txt
else
  echo "Garage key already exists:"
  garage_cmd key info "$KEY_NAME"
  echo
  echo "Secret key is only shown when the key is created."
  echo "Run with GARAGE_RECREATE_KEY=1 to rotate it and print a new secret."
fi
echo
echo "Use these Symfony values:"
echo "MINIO_ENDPOINT=http://127.0.0.1:3910"
echo "MINIO_REGION=garage"
echo "MINIO_BUCKET=$BUCKET"
echo "MINIO_USE_PATH_STYLE=1"
