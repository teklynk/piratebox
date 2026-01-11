# PirateBox - Offline File Share

This project transforms a Raspberry Pi (or similar Debian-based system) into an offline, anonymous file-sharing network. Users connect to the "PirateBox" Wi-Fi hotspot and are automatically redirected to a browser-based interface for uploading and downloading files.

Inspired by the discontinued [PirateBox](https://en.wikipedia.org/wiki/PirateBox) project, this lightweight implementation uses Nginx, PHP, dnsmasq, and hostapd without requiring a database. It functions as a "captive portal" similar to public Wi-Fi login pages found in hotels or libraries, but instead of requesting credentials, it immediately serves the file-sharing page.

This configuration has been tested on a Raspberry Pi Zero 2 W running Raspberry Pi OS Lite (Trixie).

<div dir="auto">
<a target="_blank" rel="noopener noreferrer" href="https://github.com/teklynk/piratebox/blob/main/pizero_piratebox.jpg?raw=true"><img src="https://github.com/teklynk/piratebox/raw/main/pizero_piratebox.jpg?raw=true" style="max-width: 100%;"></a>
<br>
<a target="_blank" rel="noopener noreferrer" href="https://github.com/teklynk/piratebox/blob/main/Screenshot%20from%202026-01-11%2001-00-39.png?raw=true"><img src="https://github.com/teklynk/piratebox/blob/main/Screenshot%20from%202026-01-11%2001-00-39.png?raw=true" style="max-width: 100%;"></a>
<br>
<a target="_blank" rel="noopener noreferrer" href="https://github.com/teklynk/piratebox/blob/main/Screenshot%20from%202026-01-1 01-02-06.png?raw=true"><img src="https://github.com/teklynk/piratebox/blob/main/Screenshot%20from%202026-01-11%2001-02-06.png?raw=true" style="max-width: 100%;"></a>
<br>
<a target="_blank" rel="noopener noreferrer" href="https://github.com/teklynk/piratebox/blob/main/Screenshot%20from%202026-01-11%2001-03-28.png?raw=true"><img src="https://github.com/teklynk/piratebox/blob/main/Screenshot%20from%202026-01-11%2001-03-28.png?raw=true" style="max-width: 100%;"></a>
</div>

## Features
- **Offline Network**: Creates its own Wi-Fi hotspot (SSID: PirateBox).
- **Captive Portal**: DNS redirection resolves all requests to the local server.
- **File Sharing**: Simple web interface to upload and download files.
- **Messages**: Guestbook style messages. Let people know that you were here.
- **Auto-Cleanup**: Script included to purge uploads and messages (optional via scheduled cron job).

## Prerequisites
- Raspberry Pi with Wi-Fi capability.
- OS: Raspbian / Debian.
- Root/Sudo access.


## Installation

### 1. Install Dependencies
__Tip:__ On the Raspberry Pi Zero 2 W, I used a usb hub that also has ethernet. This allowed me to SSH into the Pi while configuring hostapd.
- Run: `sudo raspi-config` and set all of the localization settings, WIFI region, TimeZone, Keyboard layout, set hostname and enabled SSH.

__Note:__ The time server is not set by default. This can break `apt update` and produce errors on screen. The issue is that the date/time of the repos do not match the date/time of the Raspberry Pi. They are out of sync. These steps should sync your date/time and you can then run apt update. Ignore all online suggestions about downloading certs. It's just a time sync issue. 

```bash
sudo nano /etc/systemd/timesyncd.conf
```

Set the NTP server:
`NTP=time.cloudflare.com`

```bash
sudo systemctl restart systemd-timesyncd
```

```bash
timedatectl
```

Update your system and install the required packages:
```bash
sudo apt update
```
```bash
sudo apt install -y hostapd dnsmasq dhcpcd5 nginx php-fpm git
```

**❗IMPORTANT❗**

Run `sudo rpi-update` to install the latest firmware/kernel. This fixed so many issues that I was having with disconnects. iOS devices would connect and then the kernel would crash once the iOS device disconnected. I went down a rabbit hole trying to find a solution. Turns out, I just needed to update the kernel. Don't let this happen to you.

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
Edit: `/etc/default/hostapd`
Point the daemon to this config in `/etc/default/hostapd`:
```bash
DAEMON_CONF="/etc/hostapd/hostapd.conf"
DAEMON_OPTS=""
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
sudo mkdir -p /var/www/html/public/uploads
sudo mkdir -p /var/www/html/data
sudo chown -R www-data:www-data /var/www/html
sudo chmod 0755 /var/www/html/public/uploads
sudo chmod 0755 /var/www/html/data
```

### 5. Maintenance (Auto-Purge - optional)
A script `purge_uploads.sh` is provided to clean up uploads and messages.
```bash
sudo touch /var/log/purge_uploads.log
```
```bash
sudo crontab -e
```
Every day at midnight
```bash
0 0 * * * /usr/local/bin/purge_uploads.sh > /var/log/purge_uploads.log 2>&1
```

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

### Known Issues and troubleshooting

Run `sudo rpi-update` to install the latest firmware/kernel. 

Set `restart_hostapd.sh` as a cron job to run every hour.