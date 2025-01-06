# vn-cards
Linux tool to mine anki cards, specifically made for visual novels, but can be used to mine anything.

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

Adjust the scripts to fit your needs (i.e. screen position and resolution) as they are made to specifically fit my use cases as-is.

As you can maybe tell, I didn't write the script with public use exactly in mind.

Maybe I will make it more generic in the future, but, you know, it works ¯\\\_(ツ)_/¯

## Credit
Some code taken from [RecordAudioOutput](https://github.com/JayXT/RecordAudioOutput) and anacreons [animecards mpv script](https://anacreondjt.gitlab.io/docs/mpvscript/)

This repository wouldn't exist without these two, so thank you for making my life on linux significantly easier.
