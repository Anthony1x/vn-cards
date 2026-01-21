# create ram directory for buffer
mkdir -p /dev/shm/replay

# start ffmpeg recorder (run once; keep it running)
ffmpeg -hide_banner -loglevel warning \
  -f pulse -i alsa_output.pci-0000_00_1f.3.analog-stereo.monitor \
  -ac 2 -ar 48000 \
  -f segment -segment_time 5 -segment_wrap 6 \
  /dev/shm/replay/seg%03d.wav \
  >/dev/null 2>&1 &
