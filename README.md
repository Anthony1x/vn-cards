# vn-cards
Linux tool to mine anki cards, specifically made for visual novels, but can be used to mine anything.

## How to use:
Execute the shell script. First execution starts recording the audio, second execution stops it
(refer to the source code to understand how it works)

I recommend creating a keyboard shortcut to execute the script.
I've personally set it to `Meta+M`.

## Dependencies:
* [scrot](https://github.com/dreamer/scrot)
* [PHP](https://www.php.net/)
* [PipeWire](https://pipewire.org/)
* Optionally [dunst](https://github.com/dunst-project/dunst) (remove/modify lines referencing it if you don't use it)
* Of course, you also need to be using [AnkiConnect](https://ankiweb.net/shared/info/2055492159)

Adjust the scripts to fit your needs (i.e. screen position and resolution) as they are made to specifically fit my use cases as-is.

Specifically, you definitely need to replace the `pw-record` target option.

You can figure out your primary output device by running `pw-cli ls Node`.

Paste in the `object.serial` identifier, not the primary one.

You also need to replace the temporary file's names in in the last lines of `main.php`.

As you can maybe tell, I didn't write the script with public use exactly in mind.

Maybe I will make it more generic in the future, but, you know, it works ¯\_(ツ)_/¯

## Credit
Some code taken from [RecordAudioOutput](https://github.com/JayXT/RecordAudioOutput) and anacreons [animecards mpv script](https://anacreondjt.gitlab.io/docs/mpvscript/)

This repository wouldn't exist without these two, so thank you for making my life on linux significantly easier.