#!/bin/sh
set -eu

CONFIG_FILE="${GARAGE_CONFIG_FILE:-/etc/garage.toml}"
BUCKET="${GARAGE_BUCKET:-documents}"
KEY_NAME="${GARAGE_KEY_NAME:-open-demat-core}"
CAPACITY="${GARAGE_CAPACITY:-1G}"

garage_cmd() {
  garage -c "$CONFIG_FILE" "$@"
}

echo "Waiting for Garage..."
until garage_cmd status >/tmp/garage-status.txt 2>/tmp/garage-status.err; do
  sleep 2
done

NODE_ID="$(awk '
  /^[0-9a-fA-F]{64}@/ {
    split($1, parts, "@");
    print parts[1];
    exit;
  }
  /^[0-9a-fA-F]{64}[[:space:]]/ {
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
garage_cmd key create "$KEY_NAME" >/tmp/garage-key.txt 2>/dev/null || true
garage_cmd bucket allow --read --write --owner "$BUCKET" --key "$KEY_NAME" 2>/dev/null || true

echo
echo "Garage bucket ready: $BUCKET"
echo "Garage key info:"
garage_cmd key info "$KEY_NAME"
echo
echo "Use these Symfony values:"
echo "MINIO_ENDPOINT=http://127.0.0.1:3900"
echo "MINIO_REGION=garage"
echo "MINIO_BUCKET=$BUCKET"
echo "MINIO_USE_PATH_STYLE=1"
