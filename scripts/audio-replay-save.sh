#!/usr/bin/env bash
set -euo pipefail

# config - edit if you want
BUFFER_DIR="/dev/shm/replay" # where the background ffmpeg writes segments
SEGMENT_SECONDS=10           # must match your ffmpeg -segment_time
OUTDIR="/tmp"
WANTED_SECONDS=30 # how many seconds to save on keypress
BITRATE="96k"     # opus bitrate (small files). Adjust if you want fuller quality.

# compute how many segments to take (ceiling division)
SEG_COUNT=$(((WANTED_SECONDS + SEGMENT_SECONDS - 1) / SEGMENT_SECONDS))

# find the last SEG_COUNT segment files in chronological order
mapfile -t files < <(find "$BUFFER_DIR" -maxdepth 1 -type f -name 'seg*.wav' -printf "%T@ %p\n" |
    sort -n | awk '{print $2}' | tail -n "$SEG_COUNT")

if [ "${#files[@]}" -eq 0 ]; then
    notify-send -u low -a ankivn -r 6969 "No buffer files found in $BUFFER_DIR" >&2
    exit 1
fi

# build concat list file
TMPLIST=$(mktemp /dev/shm/replay-concat.XXXX)
for f in "${files[@]}"; do
    # guard against spaces/newlines in names
    printf "file '%s'\n" "$f" >>"$TMPLIST"
done

# output file with timestamp

FILE="ankirecording.opus"
OUTFILE="$OUTDIR/$FILE"
EDITED_FILE="$OUTDIR/EDITED_$FILE"

# concat + transcode to opus in a single ffmpeg call
ffmpeg -hide_banner -loglevel error -f concat -safe 0 -i "$TMPLIST" \
    -ss 0 -t "$WANTED_SECONDS" \
    -c:a libopus -b:a "$BITRATE" -vbr on "$OUTFILE"

rm -f "$TMPLIST"

notify-send -u low -a ankivn -r 6969 "Saved: $OUTFILE"

# open immediately in mpv (non-blocking)
mpv --force-window=yes "$OUTFILE"

# Check if an edited file was created (by an mpv script)
if [ -f "$EDITED_FILE" ]; then
    # Delete the original...
    rm -f "$OUTFILE"
    # ...and rename the edit to have the original filename
    mv "$EDITED_FILE" "$OUTFILE"
else
    notify-send -u normal -a ankivn -r 6969 "No edited file found, using original."
fi

# After cutting the file we run the script to add a screenshot and everything to anki
SCRIPT_DIR="$(dirname "$(realpath "$0")")"
"$SCRIPT_DIR/capture.sh"

# Finally, we clean up our mess
rm -f "$OUTFILE"

