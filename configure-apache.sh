#!/bin/bash

# Set default port if PORT environment variable is not set
export APACHE_PORT=${PORT:-8000}

echo "Configuring Apache to listen on port $APACHE_PORT on all interfaces (0.0.0.0)"

# Create ports.conf dynamically
cat > /etc/apache2/ports.conf << EOF
Listen 0.0.0.0:$APACHE_PORT

<IfModule ssl_module>
    Listen 0.0.0.0:443 ssl
</IfModule>

<IfModule mod_gnutls.c>
    Listen 0.0.0.0:443 ssl
</IfModule>
EOF

# Update VirtualHost configuration with correct port
sed -i "s/\${APACHE_PORT}/$APACHE_PORT/g" /etc/apache2/sites-available/000-default.conf

# Set Apache environment variable for VirtualHost
export APACHE_PORT=$APACHE_PORT

echo "Apache configured to listen on 0.0.0.0:$APACHE_PORT"
