#!/bin/sh

screenshot="$(xdg-user-dir DOCUMENTS)/tmp/ankiscreenie.webp"

php_script="$(xdg-user-dir DOCUMENTS)/Dev/vn-cards/add_to_card.php --do-not-record"

# Take screenshot and size it down while we're at it
scrot -a 1280,1440,2560,1440 - | magick - -resize x600 $screenshot

dunstify "Took screenie" -r 6969

php $php_script

rm $screenshot
