#!/bin/sh
# Test stand-in for a broken mail system.
cat >/dev/null; echo "sendmail: mail system down" >&2; exit 75
