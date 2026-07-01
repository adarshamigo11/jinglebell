#!/bin/bash
set -e

# Disable conflicting MPM modules; only mpm_prefork should be active
a2dismod -f mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# Railway injects PORT at runtime — default to 8080 if not set
PORT="${PORT:-8080}"

# Write ports.conf with the runtime PORT value
cat > /etc/apache2/ports.conf <<EOF
Listen ${PORT}
EOF

# Write the VirtualHost config with the runtime PORT value
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

exec apache2-foreground
