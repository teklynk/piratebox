#!/bin/bash
echo $(date) ": Restarting hostapd service..."
sudo service hostapd restart
echo $(date) ": hostapd service restarted"