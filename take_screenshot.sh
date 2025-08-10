#!/bin/sh

screenshot="/tmp/ankiscreenie.webp"
php_script="$(xdg-user-dir DOCUMENTS)/Dev/vn-cards/add_to_card.php --do-not-record"


# Take screenshot and size it down while we're at it
scrot -a 1280,1440,2560,1440 $screenshot -t 0x600

# Remove original size screenshot
rm $screenshot

# Rename thumbnail screenshot to regular screenshot
mv "/tmp/ankiscreenie-thumb.webp" $screenshot

notify-send -u low -a ankivn -r 6969 "Took screenie" -r 6969

php $php_script

# Remove downsized screenshot
rm $screenshot


