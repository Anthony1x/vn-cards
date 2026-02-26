# vn-cards
Linux tool to mine anki cards, specifically made for visual novels, but can be used to mine anything.

Over time, this has grown to be a general purpose anki tool that can do all sorts of stuff.

## How to use

Rename/copy the `.env.example` to `.env` and redefine the variables to suit your setup.

Simply execute the `scripts/capture.sh [OPTION]` after creating a card.

To record audio, pass the `--record` flag. To stop recording audio, execute the script again (also with the `record` flag).

You could also use [this](https://github.com/Anthony1x/.dotfiles/blob/main/.config/rofi/scripts/anki.sh) rofi script.

Personally I don't use the rofi script anymore since custom shortcuts to take screenshots and record audio respectively suffice for my usecase.

I've personally set it to `Meta+M` to take screenshots, and `Meta+Shift+M` to record audio.
[Qtile example](https://github.com/Anthony1x/.dotfiles/blob/a907525cab9111bc16c4123b6982cba7b4d64492/.config/qtile/keys.py#L21)

If unsure, refer to the source code, or ask me! I'm happy to help.

## Dependencies
* [scrot](https://github.com/dreamer/scrot) to take screenshots
* [PHP](https://www.php.net/) because that's what I used to write this script (:
* [PipeWire](https://pipewire.org/) - I didn't bother to implement PulseAudio support
* Of course, you also need [AnkiConnect](https://ankiweb.net/shared/info/2055492159)

Whilst I have adjusted the repository to work on mulitple setups, I wrote this script for myself first and foremost, so it's not tested to work on a plethora of systems.

Still, feel free to open an issue if something doesn't work the way you expect.

## Credit
Some code taken from [RecordAudioOutput](https://github.com/JayXT/RecordAudioOutput) and anacreons [animecards mpv script](https://anacreondjt.gitlab.io/docs/mpvscript/)

This repository wouldn't exist without these two, so thank you for making my life on linux significantly easier.
