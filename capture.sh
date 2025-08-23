#!/bin/sh

#==============================================================================
# Unified script to capture a screenshot and optionally record audio for an Anki card.
#
# USAGE:
#   - Screenshot only: ./capture_card.sh
#   - Toggle audio recording: ./capture_card.sh --record
#
#==============================================================================

# --- Configuration ---
screenshot="/tmp/ankiscreenie.webp"
audio="/tmp/ankirecording.wav"
php_script="$(xdg-user-dir DOCUMENTS)/Dev/vn-cards/add_to_card.php"


# --- Helper Function ---
# Takes a screenshot of a predefined area, creates a thumbnail,
# and replaces the original with the thumbnail.
take_screenshot() {
    # -a selects an area, -t creates a thumbnail of the given size (0x600)
    scrot -a 1280,1440,2560,1440 "$screenshot" -t 0x600

    # Remove the original, full-resolution screenshot
    rm "$screenshot"

    # Rename the generated thumbnail to be our main screenshot file
    mv "/tmp/ankiscreenie-thumb.webp" "$screenshot"
}


# --- Main Logic ---
# Check if the first argument is the record flag
if [ "$1" = "-r" ] || [ "$1" = "--record" ]; then

    # --- RECORD MODE ---
    # Toggles audio recording.

    # Check if pw-record is already running
    if pgrep pw-record; then
        # --- Stop Recording ---
        pkill pw-record
        notify-send -u low -a ankivn -r 6969 "Recording finished"

        # Process the captured screenshot and audio
        php "$php_script"

        # Clean up temporary files
        rm "$screenshot" "$audio"
    else
        # --- Start Recording ---
        take_screenshot
        notify-send -u low -a ankivn -r 6969 "Recording..." -t 99999

        # A brief pause and mouse action, specific to your workflow
        sleep 0.2
        xdotool mousemove 3670 2650 click 1

        # Start recording all desktop audio
        pw-record -P '{ stream.capture.sink=true }' "$audio"
    fi

else

    # --- SCREENSHOT-ONLY MODE ---
    # The default action when no flags are provided.

    take_screenshot
    notify-send -u low -a ankivn -r 6969 "Took screenie"

    # Process the screenshot without audio
    php "$php_script" --do-not-record

    # Clean up the screenshot
    rm "$screenshot"

fi