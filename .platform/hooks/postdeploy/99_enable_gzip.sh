#!/bin/bash
set -e
echo "Habilitando gzip en nginx..."
sed -i 's/gzip\s*off;/gzip on;/g' /etc/nginx/nginx.conf
nginx -t && systemctl reload nginx
echo "Gzip habilitado"
