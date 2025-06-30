#!/bin/sh

screenshot="$(xdg-user-dir DOCUMENTS)/tmp/ankiscreenie.webp"

php_script="$(xdg-user-dir DOCUMENTS)/Dev/vn-cards/add_to_card.php --do-not-record"

# Take screenshot and size it down while we're at it
scrot -a 1280,1440,2560,1440 $screenshot -t 0x600
rm $screenshot
mv "$(xdg-user-dir DOCUMENTS)/tmp/ankiscreenie-thumb.webp" $screenshot

notify-send -u low -a ankivn -r 6969 "Took screenie" -r 6969

php $php_script

rm $screenshot
