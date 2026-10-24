#!/bin/sh
# Test stand-in for sendmail: stores each message mail() sends, so tests can read it.
mkdir -p /tmp/mail_out; f=/tmp/mail_out/$(date +%s%N)-$$.eml
echo "$@" > "$f.args"; cat > "$f"; exit 0
