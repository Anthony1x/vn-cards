<?php

const FRONT_FIELD = "Expression";
const SENTENCE_AUDIO_FIELD = "SentenceAudio";
const SENTENCE_FIELD = "Sentence";
const IMAGE_FIELD = "Picture";

const START_HERE = 'start_collecting';
const STOP_HERE = 'stop_collecting';

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

function get_latest_card()
{
    $last_note = anki_connect('findNotes',  ['query' => 'added:1'])->result;

    if (empty($last_note) || is_null($last_note)) {
        shell_exec("dunstify 'No recently added cards found! Aborting.'");
        die;
    }

    # If there is more than one note, the one with the biggest integer is the newest one.
    $last_note = count($last_note) === 1 ? $last_note[0] :  max(...$last_note);

    return $last_note;
}


function add_tags_to_card($card, string ...$tags)
{
    return anki_connect('updateNote', [
        'note' => [
            'id' => $card,
            'tags' => $tags
        ]
    ]);
}

function get_all_cards()
{
    $res = anki_connect('findCards', ['query' => 'deck:日本語::Mining'])->result;

    $note_info = anki_connect('notesInfo', ['notes' => $res])->result;

    return array_filter((array)$note_info, fn($note) => !empty((array)$note));
}

function al_log(string $message, int $id = 6969, ?int $loglevel = null)
{
    shell_exec("dunstify '$message' -r $id");
}
