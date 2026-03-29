#!/usr/bin/env sh
# Generates a Laravel APP_KEY and writes it into src/.env.
# Run this before building images — no PHP or Laravel needed on the host.

ENV_FILE="$(dirname "$0")/src/.env"

if [ ! -f "$ENV_FILE" ]; then
  echo "Error: $ENV_FILE not found. Copy src/.env.example to src/.env first." >&2
  exit 1
fi

KEY="base64:$(openssl rand -base64 32)"

if grep -q '^APP_KEY=' "$ENV_FILE"; then
  sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" "$ENV_FILE"
else
  echo "APP_KEY=$KEY" >> "$ENV_FILE"
fi

echo "APP_KEY written to $ENV_FILE"
