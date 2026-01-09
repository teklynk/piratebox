#!/bin/bash

# PirateBox Installer Script
# Run this script as root: sudo ./installer.sh

set -e

if [ "$(id -u)" -ne 0 ]; then
    echo "Error: This script must be run as root."
    echo "Usage: sudo ./installer.sh"
    exit 1
fi

echo "========================================"
echo "   PirateBox Installer Started"
echo "========================================"

# Time Sync Fix (Crucial for apt update)
echo "[+] Configuring Time Sync (NTP)..."
if ! grep -q "NTP=time.cloudflare.com" /etc/systemd/timesyncd.conf; then
    sed -i 's/#NTP=/NTP=time.cloudflare.com/' /etc/systemd/timesyncd.conf
    sed -i 's/NTP=/NTP=time.cloudflare.com/' /etc/systemd/timesyncd.conf
    systemctl restart systemd-timesyncd
    echo "    Waiting 5 seconds for time sync..."
    sleep 5
fi

# Install Dependencies
echo "[+] Updating system and installing dependencies..."
apt-get update
apt-get install -y hostapd dnsmasq dhcpcd5 nginx php-fpm
sudo rpi-update

# Network Configuration
echo "[+] Configuring Network..."

# dhcpcd
if ! grep -q "interface wlan0" /etc/dhcpcd.conf; then
    echo "    Configuring static IP for wlan0 in /etc/dhcpcd.conf..."
    cat <<EOF >> /etc/dhcpcd.conf

interface wlan0
static ip_address=10.0.0.1/24
nohook wpa_supplicant
EOF
    systemctl enable dhcpcd
    systemctl restart dhcpcd
fi

# hostapd
echo "    Creating /etc/hostapd/hostapd.conf..."
cat <<EOF > /etc/hostapd/hostapd.conf
interface=wlan0
driver=nl80211
ssid=PirateBox
hw_mode=g
channel=6
ieee80211n=1
wmm_enabled=1
auth_algs=1
wpa=0
country_code=US
EOF

# Point hostapd to config
sed -i 's|#DAEMON_CONF=""|DAEMON_CONF="/etc/hostapd/hostapd.conf"|' /etc/default/hostapd
sed -i 's|DAEMON_CONF=""|DAEMON_CONF="/etc/hostapd/hostapd.conf"|' /etc/default/hostapd

systemctl unmask hostapd
systemctl enable hostapd

# dnsmasq
echo "    Configuring dnsmasq..."
[ -f /etc/dnsmasq.conf ] && cp /etc/dnsmasq.conf /etc/dnsmasq.conf.bak
cat <<EOF > /etc/dnsmasq.conf
interface=wlan0
dhcp-range=10.0.0.10,10.0.0.250,12h
address=/#/10.0.0.1
EOF
systemctl restart dnsmasq

# Web Server Configuration
echo "[+] Configuring Web Server..."

# Nginx
if [ -f "etc/nginx/sites-available/default" ]; then
    echo "    Copying Nginx configuration..."
    cp "etc/nginx/sites-available/default" /etc/nginx/sites-available/default
else
    echo "    WARNING: etc/nginx/sites-available/default not found in repo. Skipping Nginx config copy."
fi

# PHP
PHP_VER=$(ls /etc/php/ | sort -V | tail -n 1)
echo "    Detected PHP version: $PHP_VER"
PHP_INI="/etc/php/$PHP_VER/fpm/php.ini"

if [ -f "$PHP_INI" ]; then
    echo "    Updating php.ini settings..."
    sed -i 's/^upload_max_filesize.*/upload_max_filesize = 120M/' "$PHP_INI"
    sed -i 's/^post_max_size.*/post_max_size = 130M/' "$PHP_INI"
    sed -i 's/^max_execution_time.*/max_execution_time = 300/' "$PHP_INI"
    sed -i 's/^display_errors.*/display_errors = Off/' "$PHP_INI"
    sed -i 's/^log_errors.*/log_errors = On/' "$PHP_INI"
    # Add disable_functions if needed, though sed on a long list is tricky.
fi

# Application Deployment
echo "[+] Deploying Application files..."
mkdir -p /var/www/html/uploads
cp -r var/www/html/* /var/www/html/
chown -R www-data:www-data /var/www/html
chmod 0755 /var/www/html/uploads

# Maintenance Scripts & Cron
echo "[+] Installing Maintenance Scripts..."
cp purge_uploads.sh /usr/local/bin/
chmod +x /usr/local/bin/purge_uploads.sh
cp restart_hostapd.sh /usr/local/bin/
chmod +x /usr/local/bin/restart_hostapd.sh

echo "[+] Setting up Cron Jobs..."
(crontab -l 2>/dev/null; echo "0 0 * * * /usr/local/bin/purge_uploads.sh > /var/log/purge_uploads.log 2>&1") | sort -u | crontab -
(crontab -l 2>/dev/null; echo "0 * * * * /usr/local/bin/restart_hostapd.sh > /dev/null 2>&1") | sort -u | crontab -

# Disable Services
echo "[+] Disabling unnecessary services..."
systemctl disable bluetooth.service 2>/dev/null || true
systemctl stop wpa_supplicant.service 2>/dev/null || true
systemctl disable wpa_supplicant.service 2>/dev/null || true
systemctl mask wpa_supplicant.service 2>/dev/null || true

echo "========================================"
echo "   Installation Complete!"
echo "   Please reboot your system: sudo reboot"
echo "========================================"