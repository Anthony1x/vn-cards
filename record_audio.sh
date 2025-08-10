#!/bin/sh

screenshot="/tmp/ankiscreenie.webp"
audio="/tmp/ankirecording.wav"

php_script="$(xdg-user-dir DOCUMENTS)/Dev/vn-cards/add_to_card.php"

if pgrep pw-record; then
    # Stop recording audio
    pkill pw-record

    notify-send -u low -a ankivn -r 6969 "Recording finished"

    # Add to anki card using php script
    php $php_script

    # Remove downsized screenshot
    rm $screenshot
    rm $audio
else
    # Take screenshot and size it down while we're at it
    scrot -a 1280,1440,2560,1440 $screenshot -t 0x600

    # Remove original size screenshot
    rm $screenshot

    # Rename thumbnail screenshot to regular screenshot
    mv "/tmp/ankiscreenie-thumb.webp" $screenshot

    # -t 0 Makes it so the notification never disappears.
    # NOTE: This broke at some point for unknown reasons. So we just set the timeout ridiculously high
    notify-send -u low -a ankivn -r 6969 "Recording..." -t 99999

    sleep 0.2
    xdotool mousemove 3670 2650 click 1
    # -P sets properties. We set the `sink` property to true to record all desktop audio.
    # It would probably be better to just record the audio of the window we actually want to record,
    # but that sounds way harder to do, so we don't.
    pw-record -P '{ stream.capture.sink=true }' $audio
fi
