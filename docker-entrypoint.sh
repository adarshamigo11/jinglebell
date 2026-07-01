#!/bin/bash
set -e

# Disable conflicting MPMs (only mpm_prefork works with mod_php)
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true
a2enmod rewrite headers 2>/dev/null || true

# Write ports.conf with Railway's PORT variable (available at runtime)
PORT=${PORT:-8080}
echo "Listen ${PORT}" > /etc/apache2/ports.conf

# Write VirtualHost config with the runtime PORT
cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:${PORT}>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Start Apache in foreground
exec apache2-foreground
