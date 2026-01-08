# -------------------------------------------------
# PirateBox - captive-portal
# -------------------------------------------------
server {
    client_max_body_size 150M;

    listen 80 default_server;
    server_name _;
    root /var/www/html;
    index index.php index.html;

    # Apple (iOS/macOS)
    location = /hotspot-detect.html {
        try_files /captive.html =404;
    }
    location = /success.html {
        try_files /captive.html =404;
    }

    # Android (Google 204 check)
    # location = /generate_204 {
    #    return 204;
    # }

    # Windows NCSI
    location = /ncsi.txt {
        default_type text/plain;
        return 200 "Microsoft NCSI";
    }

    location / {
        # Serve real files directly; otherwise hand off to index.php
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        try_files $uri =404;

        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_index index.php;

        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT   $document_root;

        # Adjust socket path if you use a different PHP version
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
        access_log off;
        log_not_found off;
    }
}
