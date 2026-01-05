# PirateBox - Offline File Share

This project transforms a Raspberry Pi (or similar Debian-based system) into an offline, anonymous file-sharing network. Users connect to the "PirateBox" Wi-Fi hotspot and are automatically redirected to a browser-based interface for uploading and downloading files.

Inspired by the discontinued [PirateBox](https://en.wikipedia.org/wiki/PirateBox) project, this lightweight implementation uses Nginx, PHP, dnsmasq, and hostapd without requiring a database. It functions as a "captive portal" similar to public Wi-Fi login pages found in hotels or libraries, but instead of requesting credentials, it immediately serves the file-sharing page.

This configuration has been tested on a Raspberry Pi Zero 2 W running Raspberry Pi OS Lite (Trixie).

<div dir="auto">
<a target="_blank" rel="noopener noreferrer" href="https://github.com/teklynk/piratebox/blob/main/ui_piratebox.jpg?raw=true"><img src="https://github.com/teklynk/piratebox/raw/main/ui_piratebox.jpg?raw=true" style="max-width: 100%;"></a>
<br>
<a target="_blank" rel="noopener noreferrer" href="https://github.com/teklynk/piratebox/blob/main/pizero_piratebox.jpg?raw=true"><img src="https://github.com/teklynk/piratebox/raw/main/pizero_piratebox.jpg?raw=true" style="max-width: 100%;"></a>
</div>

## Features
- **Offline Network**: Creates its own Wi-Fi hotspot (SSID: PirateBox).
- **Captive Portal**: DNS redirection resolves all requests to the local server.
- **File Sharing**: Simple web interface to upload and download files.
- **Auto-Cleanup**: Script included to purge uploads daily (optional via cron job).

## Prerequisites
- Raspberry Pi with Wi-Fi capability.
- OS: Raspbian / Debian.
- Root/Sudo access.

## Installation

### 1. Install Dependencies
Update your system and install the required packages:
```bash
sudo apt update
sudo apt install -y hostapd dnsmasq dhcpcd5 nginx php-fpm
```

### 2. Network Configuration

#### Static IP (dhcpcd)
Edit `/etc/dhcpcd.conf` to set a static IP for the wireless interface:
```bash
interface wlan0
static ip_address=10.0.0.1/24
nohook wpa_supplicant
```
Enable and restart the service:
```bash
sudo systemctl enable dhcpcd
sudo systemctl restart dhcpcd
```

#### Access Point (hostapd)
Create `/etc/hostapd/hostapd.conf`:
```ini
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
```
Point the daemon to this config in `/etc/default/hostapd`:
```bash
DAEMON_CONF="/etc/hostapd/hostapd.conf"
```
Unmask and start hostapd:
```bash
sudo systemctl unmask hostapd
sudo systemctl enable hostapd
sudo systemctl start hostapd
```

#### DNS & DHCP (dnsmasq)
Configure `/etc/dnsmasq.conf` to handle IP leasing and redirect all DNS queries to the PirateBox:
```ini
interface=wlan0
dhcp-range=10.0.0.10,10.0.0.250,12h
address=/#/10.0.0.1
```
Restart dnsmasq:
```bash
sudo systemctl restart dnsmasq
```

### 3. Web Server Configuration

#### Nginx
The default Nginx site configuration handles the captive portal redirection and large file uploads.

Copy the provided configuration from `etc/nginx/sites-available/default` to `/etc/nginx/sites-available/default`.

**Note:** The setting `client_max_body_size 150M;` is included in this site configuration. You do not need to add it to `/etc/nginx/nginx.conf` globally unless you prefer a global setting.

#### PHP-FPM
Edit `/etc/php/8.4/fpm/php.ini` to secure the installation and allow larger uploads:
```ini
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
upload_max_filesize = 120M
post_max_size = 130M
max_execution_time = 300
display_errors = Off
log_errors = On
```

**Note:** You may also need to set these values in the pool config (`/etc/php/8.4/fpm/pool.d/www.conf`) if `php.ini` changes don't take effect:
```ini
php_value[upload_max_filesize] = 120M
php_value[post_max_size] = 130M
```

### 4. Application Deployment
Copy the source files (`index.php`, `upload.php`, `styles.css`, images) to `/var/www/html/`.

Create the uploads directory and set permissions:
```bash
cd /var/www/html
sudo mkdir uploads
sudo chown -R www-data:www-data /var/www/html
sudo chmod 0755 uploads
```

### 5. Maintenance (Auto-Purge - optional)
A script `purge_uploads.sh` is provided to clean up uploads.
1. Make it executable: `chmod +x purge_uploads.sh`.
2. Add a cron job (`sudo crontab -e`) to run it daily at midnight:
   `0 0 * * * /bin/bash /path/to/purge_uploads.sh`

### Disable Unnecessary Services

```bash
sudo systemctl disable bluetooth.service

sudo systemctl stop wpa_supplicant.service

sudo systemctl disable wpa_supplicant.service

sudo systemctl mask wpa_supplicant.service
```

You may also want to disable SSH. This will however mean that you will no longer be able to SSH into the device. 

```bash
sudo systemctl disable --now ssh

sudo systemctl mask ssh

sudo systemctl status ssh
```