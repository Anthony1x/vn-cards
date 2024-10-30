#!/bin/sh

screenshot="$(xdg-user-dir DOCUMENTS)/tmp/ankiscreenie.png"

php_scrpt="$(xdg-user-dir DOCUMENTS)/Dev/vn-cards/main.php --do-not-record"

scrot -a 1280,1440,2560,1440 -F $screenshot

dunstify "Took screenie" -r 6969

php $php_scrpt

rm $screenshot
