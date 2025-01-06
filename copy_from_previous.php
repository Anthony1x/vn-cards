<?php

/// Copy audio and picture info from the card immediately before the current one.
/// This prevents polluting Anki with multiple copies of the same audio / picture.

require_once 'anki_connect.php';

$all_cards = get_all_cards();

$collecting = false;
$cards_to_update = [];

foreach ($all_cards as $key => $card) {
    $tags = $card->tags;

    $start_collecting = in_array(START_HERE, $tags);
    $stop_collecting = in_array(STOP_HERE, $tags);

    // Check for errors first

    if ($stop_collecting && $collecting === false) {
        $expr = $get_expression($card);
        anki_log("Stop tag without start tag found on card: $expr!", Urgency::critical);
        return;
    }

    if ($start_collecting && $collecting !== false) {
        $expr = $get_expression($card);
        $a = $collecting['debug_expr_val'];
        anki_log("Two start tags without stop tag found on card: $expr! Start tag is $a", Urgency::critical);
        return;
    }

    // At this point we should be safe

    if ($collecting) {
        $cards_to_update[] = $card;
    }

    if ($start_collecting) {
        $collecting = [
            IMAGE_FIELD => $card->fields->{IMAGE_FIELD}->value,
            SENTENCE_AUDIO_FIELD => $card->fields->{SENTENCE_AUDIO_FIELD}->value,
        ];
    }

    if ($stop_collecting) {

        $cards_expr = array_map(fn($card) => $card->fields->Expression->value, $cards_to_update);
        $cards_str = implode(', ', $cards_expr);

        anki_log("Tagging these cards: $cards_str");

        foreach ($cards_to_update as $card_to_update) {
            anki_connect('updateNoteFields', [
                'note' => [
                    'id' => $card_to_update->noteId,
                    'fields' => $collecting
                ]
            ]);
        }

        $collecting = false;
        $cards_to_update = [];
    }
}

if ($collecting !== false) {
    anki_log("Loop finished, but no final stop tag found!");
    die();
}

// TODO: Now we can remove all instances of the tags.
