<?php

$do_not_record = false;
$test_env = false;

if (in_array('--do-not-record', $argv)) {
    $do_not_record = true;
}

if (in_array('--test-env', $argv)) {
    $test_env = true;
}

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
        shell_exec("dunstify 'cURL reported error: $err, aborting.'");
        die;
    }

    return json_decode($response);
}

function add_to_last_added(string $image, ?string $audio = null, ?string $text = null)
{
    // Fuck yeah php
    global $do_not_record, $test_env;

    $last_note = anki_connect('findNotes',  ['query' => 'added:1'])->result;

    if (empty($last_note) || is_null($last_note)) {
        shell_exec("dunstify 'No recently added cards found! Aborting.'");
        die;
    }

    # If there is more than one note, the one with the biggest integer is the newest one.
    $last_note = count($last_note) === 1 ? $last_note[0] :  max(...$last_note);

    $note_info = anki_connect('notesInfo', ['notes' => [$last_note]]);

    if (is_null($note_info)) {
        shell_exec("dunstify 'No note info! Aborting.'");
        die;
    }

    $word = $note_info->result[0]->fields->{FRONT_FIELD}->value;

    $new_fields = [
        IMAGE_FIELD => "<img src='$image'>",
        SENTENCE_AUDIO_FIELD => $do_not_record ? null : "[sound:$audio]",
        SENTENCE_FIELD => $do_not_record ? null : $text,
    ];

    // Remove null values from the array. This will also filter out the clipboard content if there is none set.
    $new_fields = array_filter($new_fields);

    // Do not actually update cards if we're just testing the script
    if ($test_env === false) {
        anki_connect('updateNoteFields', [
            'note' => [
                'id' => $last_note,
                'fields' => $new_fields
            ]
        ]);
    }

    shell_exec("dunstify 'Successfully added to word: $word'");
}

function get_clipboard()
{
    return shell_exec('xclip -selection clipboard -out');
}

if ($test_env === true) {
    add_to_last_added('', '', '');
    return;
}

$stamp = time();

$image_tmp = "/home/anthony/Documents/tmp/ankiscreenie.webp";
$image = "anthony_custom_$stamp.webp";
copy($image_tmp,  $prefix . "/$image");

if ($do_not_record === false) {
    $audio_tmp = "/home/anthony/Documents/tmp/ankirecording.wav";
    $audio = "anthony_custom_$stamp.wav";
    copy($audio_tmp, $prefix . "/$audio");
}

add_to_last_added($image, $audio, null);
