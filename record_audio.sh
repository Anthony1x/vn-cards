#!/bin/sh

screenshot="$(xdg-user-dir DOCUMENTS)/tmp/ankiscreenie.webp"
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
    # Take screenshot and size it down while we're at it
    scrot -a 1280,1440,2560,1440 - | magick - -resize x600 $screenshot

    dunstify "Recording..." -r 6969

    # -P sets properties. We set the `sink` property to true to record all desktop audio.
    # It would probably be better to just record the audio of the window we actually want to record,
    # but that sounds way harder to do, so we don't.
    pw-record -P '{ stream.capture.sink=true }' $audio
fi
