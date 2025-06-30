<?php

declare(strict_types=1);

const DECK_NAME = 'deck:日本語::Mining';

const FRONT_FIELD = "Expression";
const SENTENCE_AUDIO_FIELD = "SentenceAudio";
const SENTENCE_FIELD = "Sentence";
const IMAGE_FIELD = "Picture";

const START_HERE = 'start_collecting';
const STOP_HERE = 'stop_collecting';

/** Anki collection media path. Ensure Anki username is correct. */
define('PREFIX', getenv("HOME") . "/.local/share/Anki2/User 1/collection.media");

function anki_connect(string $action, array $params)
{
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_PORT => "8765",
        CURLOPT_URL => "http://localhost",
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
        anki_log("cURL reported error: $err, aborting.", Urgency::critical);
        die;
    }

    return json_decode($response);
}

function get_latest_card()
{
    $last_note = anki_connect('findNotes',  ['query' => 'added:1'])->result;

    if (empty($last_note) || is_null($last_note)) {
        anki_log('No recently added cards found! Aborting.', Urgency::critical);
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

function get_all_cards(string $deck = DECK_NAME)
{
    $note_ids = anki_connect('findCards', ['query' => $deck])->result;
    $note_info = anki_connect('cardsInfo', ['cards' => $note_ids])->result;

    return array_filter((array)$note_info, fn($note) => !empty((array)$note));
}

function get_all_notes(string $deck = DECK_NAME)
{
    $res = anki_connect('findCards', ['query' => $deck])->result;
    $note_info = anki_connect('notesInfo', ['notes' => $res])->result;

    return array_filter((array)$note_info, fn($note) => !empty((array)$note));
}

function anki_log(string $message, Urgency $loglevel = Urgency::low)
{
    // See `man notify-send` if you want to know what all these do.
    shell_exec("notify-send -a ankivn -r 6969 -u {$loglevel->name} -t 5000 '$message'");
}

function get_cards_by_tag()
{
    $note_info = get_all_notes();

    $tag_counts = [];

    foreach ($note_info as $note) {
        foreach ($note->tags as $tag) {
            // Remove (or convert) wildcards if necessary. Otherwise, count directly.
            if (!isset($tag_counts[$tag])) {
                $tag_counts[$tag] = 1;
            } else {
                $tag_counts[$tag]++;
            }
        }
    }

    arsort($tag_counts);

    return $tag_counts;
}

function get_sorted_by_freq_or_age()
{
    $cards = get_all_cards();

    $cards = array_filter((array)$cards, fn($note) => !empty((array)$note));
    $cards = array_values($cards);

    $freq = $cards;
    $oldest = $cards;

    usort($freq, fn($note1, $note2) => $note1->fields->freqSort->value <=> $note2->fields->freqSort->value);
    usort($oldest, fn($note1, $note2) => $note1->noteId <=> $note2->noteId);
}

function replace_with_newer_card()
{
    $cards = get_all_cards();

    $cardsByField = [];

    foreach ($cards as $card) {
        $key = $card->fields->{FRONT_FIELD}->value;

        if (!isset($cardsByField[$key])) {
            $cardsByField[$key] = [];
        }

        $cardsByField[$key][] = $card;
    }

    // Filter out keys that have only one card so that you're left with duplicates only
    $duplicates = [];
    foreach ($cardsByField as $key => $group) {
        if (count($group) > 1) {
            $duplicates[$key] = $group;
        }
    }

    foreach ($duplicates as $duplicate) {

        usort($duplicate, fn($note1, $note2) => $note1->cardId <=> $note2->cardId);

        $old = $duplicate[0];
        $new = $duplicate[1];

        $f = $new->fields;

        if ($old->fields->ExpressionReading->value != $f->ExpressionReading->value) {
            var_dump("Difference detected!", $old->fields->{FRONT_FIELD}->value, $old->fields->ExpressionReading->value, $f->ExpressionReading->value);
            continue;
        }

        $res = anki_connect('updateNoteFields', [
            'note' => [
                'id' => $old->note,
                'fields' => [
                    "ExpressionFurigana" => $f->ExpressionFurigana->value,
                    "ExpressionReading" => $f->ExpressionReading->value,
                    "ExpressionAudio" => $f->ExpressionAudio->value,
                    'SelectionText' => $f->SelectionText->value,
                    'MainDefinition' => $f->MainDefinition->value,
                    'Sentence' => $f->Sentence->value,
                    'SentenceFurigana' => $f->SentenceFurigana->value,
                    'SentenceAudio' => $f->SentenceAudio->value,
                    'Picture' => $f->Picture->value,
                    'Glossary' => $f->Glossary->value,
                    'Hint' => $f->Hint->value,
                    'IsWordAndSentenceCard' => $f->IsWordAndSentenceCard->value,
                    'IsClickCard' => $f->IsClickCard->value,
                    'IsSentenceCard' => $f->IsSentenceCard->value,
                    'PitchPosition' => $f->PitchPosition->value,
                    'PitchCategories' => $f->PitchCategories->value,
                    'Frequency' => $f->Frequency->value,
                    'FreqSort' => $f->FreqSort->value,
                    'MiscInfo' => $f->MiscInfo->value,
                ]
            ]
        ]);

        $res2 = anki_connect('deleteNotes', [
            'notes' => [$new->note]
        ]);

        var_dump("Done. Output: ", $res, $res2);
    }
}

enum Urgency: string
{
    case low = 'low';
    case normal = 'normal';
    case critical = 'critical';
}
