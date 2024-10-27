<?php

const FRONT_FIELD = "Word";
const SENTENCE_AUDIO_FIELD = "SentenceAudio";
const SENTENCE_FIELD = "Sentence";
const IMAGE_FIELD = "Picture";

// Anki collection media path. Ensure Anki username is correct.
$prefix = getenv("HOME") . "/.local/share/Anki2/User 1/collection.media";

function anki_connect(string $action, array $params)
{
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_PORT => "8765",
        CURLOPT_URL => "http://localhost:8765/findNotes?action=deckNames&version=6",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'action' => $action,
            'params' => [
                ...$params
            ],
            'version' => 6
        ]),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "accept: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
        return false;
    } else
        return json_decode($response);
}

function add_to_last_added(string $image, string $audio, string $text)
{
    $last_note = anki_connect('findNotes',  ['query' => 'added:1'])->result;

    if (count($last_note) === 1) {
        $last_note = $last_note[0];
    } else {
        # Of all notes, the one with the biggest integer is the newest one.
        $last_note = max(...$last_note);
    }

    $note_info = anki_connect('notesInfo', ['notes' => [$last_note]]);

    if (!is_null($note_info)) {
        $word = $note_info->result[0]->fields->{FRONT_FIELD}->value;

        $new_fields = [
            SENTENCE_AUDIO_FIELD => "[sound:$audio]",
            IMAGE_FIELD => "<img src='$image'>",
            SENTENCE_FIELD => $text,
        ];

        anki_connect('updateNoteFields', [
            'note' => [
                'id' => $last_note,
                'fields' => $new_fields
            ]
        ]);

        shell_exec("dunstify 'Successfully added to word: $word'");
    }
}

function get_clipboard()
{
    return shell_exec('xclip -selection clipboard -out');
}

$stamp = time();

$image_tmp = "/home/anthony/Documents/tmp/ankiscreenie.png";
$image = "anthony_custom_$stamp.png";
copy($image_tmp,  $prefix . "/$image");

$audio_tmp = "/home/anthony/Documents/tmp/ankirecording.wav";
$audio = "anthony_custom_$stamp.wav";
copy($audio_tmp, $prefix . "/$audio");

add_to_last_added($image, $audio, get_clipboard());
