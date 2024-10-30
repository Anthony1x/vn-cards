#!/bin/sh

screenshot="$(xdg-user-dir DOCUMENTS)/tmp/ankiscreenie.png"
audio="$(xdg-user-dir DOCUMENTS)/tmp/ankirecording.wav"

php_scrpt="$(xdg-user-dir DOCUMENTS)/Dev/vn-cards/main.php"

if pgrep pw-record; then
    # Stop recording audio
    pkill pw-record

    dunstify "Recording finished" -r 6969

    # Add to anki card using php script
    php $php_scrpt

    rm $screenshot
    rm $audio
else
    dunstify "Recording..." -r 6969

    scrot -a 1280,1440,2560,1440 -F $screenshot

    pw-record --target 57 $audio
fi
