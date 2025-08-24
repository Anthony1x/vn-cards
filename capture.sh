#!/bin/sh

#==============================================================================
# Unified script to capture a screenshot and optionally record audio for an Anki card.
#
# USAGE:
#   - Screenshot only: ./capture.sh
#   - Toggle audio recording: ./capture.sh --record
#
#==============================================================================

# --- Load Environment Variables ---
script_dir=$(dirname "$0")
env_file="$script_dir/.env"

if [ -f "$env_file" ]; then
  source "$env_file"
else
    notify-send -u critical -a ankivn -r 6969 "Error: .env file not found at $env_file - consult the example .env"
  exit 1
fi

# --- Configuration ---
screenshot="/tmp/ankiscreenie.webp"
audio="/tmp/ankirecording.wav"

# --- Helper Function ---
# Takes a screenshot of a predefined area, creates a thumbnail,
# and replaces the original with the thumbnail.
take_screenshot() {
    # -a selects an area, -t creates a thumbnail of the given size (0x600)
    scrot -a $SCROT_POSITION "$screenshot" -t 0x600

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