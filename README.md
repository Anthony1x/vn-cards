# vn-cards
Linux tool to mine anki cards, specifically made for visual novels, but can be used to mine anything.

Over time, this has grown to be a general purpose anki tool that can do all sorts of stuff.

IMPORTANT: This tool is currently optimised for use with the Lapis Note Type.
If you use anything else, things will break, and you *will* need to dig through the code
to replace field names.

## How to use:
Ideally, use [this](https://github.com/Anthony1x/.dotfiles/blob/main/.config/rofi/scripts/anki.sh) rofi script

If you don't or can't use rofi, refer to the source code to understand how it works.

I recommend creating a keyboard shortcut to execute the script.
I've personally set it to `Meta+M` to open the rofi menu, and `Meta+Shift+M` to record audio directly.

## Dependencies:
* [scrot](https://github.com/dreamer/scrot) to take screenshots
* [ImageMagick](https://imagemagick.org) to downscale & convert the screenshot to webp
* [PHP](https://www.php.net/) because that's what I used to write this script (:
* [PipeWire](https://pipewire.org/) - I didn't bother to implement PulseAudio support
* Of course, you also need [AnkiConnect](https://ankiweb.net/shared/info/2055492159)

Things you may need to adjust to fit your own needs:
* Scrot's screen position and resolution
* Consts defined at the top of the `anki_connect.php` file

I only really wrote this script for myself, so it's not tested to work on a plethora of systems.

Still, feel free to open an issue if something doesn't work the way you expect.

## Credit
Some code taken from [RecordAudioOutput](https://github.com/JayXT/RecordAudioOutput) and anacreons [animecards mpv script](https://anacreondjt.gitlab.io/docs/mpvscript/)

This repository wouldn't exist without these two, so thank you for making my life on linux significantly easier.
