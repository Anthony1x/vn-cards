<?php

class AnkiClient
{
    private static ?self $instance = null;
    private const ANKI_PORT = "8765";
    private const ANKI_URL = "http://localhost";

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function anki_connect(string $action, array $params)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_PORT => self::ANKI_PORT,
            CURLOPT_URL => self::ANKI_URL,
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
        $last_note = self::anki_connect('findNotes',  ['query' => 'added:1'])->result;

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
        return self::anki_connect('updateNote', [
            'note' => [
                'id' => $card,
                'tags' => $tags
            ]
        ]);
    }

    function get_all_cards()
    {
        $note_ids = self::anki_connect('findCards', ['query' => DECK_NAME])->result;
        $note_info = self::anki_connect('cardsInfo', ['cards' => $note_ids])->result;

        return array_filter((array)$note_info, fn($note) => !empty((array)$note));
    }

    function get_all_notes()
    {
        $res = self::anki_connect('findCards', ['query' => DECK_NAME])->result;
        $note_info = self::anki_connect('notesInfo', ['notes' => $res])->result;

        return array_filter((array)$note_info, fn($note) => !empty((array)$note));
    }

    function get_cards_by_tag(bool $with_frequency = false)
    {
        $note_info = self::get_all_notes();

        if ($with_frequency) {
            $tag_data = [];
            foreach ($note_info as $note) {
                // Ensure the FreqSort field exists and has a numeric value. Default to 0 if not.
                $freq_value = (int)($note->fields->FreqSort->value ?? 0);

                // If the frequency is the default placeholder, skip this card for stats.
                if ($freq_value === 9999999) {
                    continue; // Move to the next note in the loop
                }

                foreach ($note->tags as $tag) {
                    if (!isset($tag_data[$tag])) {
                        // Initialize with structure for count and total frequency
                        $tag_data[$tag] = ['Count' => 0, 'TotalFreq' => 0];
                    }
                    $tag_data[$tag]['Count']++;
                    $tag_data[$tag]['TotalFreq'] += $freq_value;
                }
            }

            // Calculate the average frequency for each tag
            foreach ($tag_data as $tag => &$data) {
                if ($data['Count'] > 0) {
                    $data['Freq'] = number_format($data['TotalFreq'] / $data['Count'], 0);
                } else {
                    $data['Freq'] = 0;
                }
                // Remove the temporary total frequency key
                unset($data['TotalFreq']);
            }

            // Sort the array by 'Count' in descending order, maintaining key association
            uasort($tag_data, fn($a, $b) => $b['Count'] <=> $a['Count']);

            return $tag_data;
        } else {
            // Original functionality for when $with_frequency is false
            $tag_counts = [];
            foreach ($note_info as $note) {
                foreach ($note->tags as $tag) {
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
    }

    /**
     * Go through all cards in the deck. If the card with the same Expression exists multiple times, replace the contents
     * of the older card with the contents of the newer card.
     *
     * This is to update existing words whilst keeping the cards' review history in tact.
     */
    function replace_with_newer_card()
    {
        $cards = self::get_all_cards();

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
                echo "Difference detected! Expression: " . $old->fields->{FRONT_FIELD}->value .
                    "\t| Reading 1: " . $old->fields->ExpressionReading->value .
                    "\t| Reading 2: " . $f->ExpressionReading->value . "\n";
                continue;
            }

            $res = self::anki_connect('updateNoteFields', [
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

            if (!is_null($res->error)) {
                var_dump($res);
                throw new Exception("There has been an error processing the request.");
            }

            self::add_tags_to_card($old->note, "Retag");

            $res = self::anki_connect('deleteNotes', [
                'notes' => [$new->note]
            ]);

            if (!is_null($res->error)) {
                var_dump($res);
                throw new Exception("There has been an error processing the request.");
            }
        }
    }

    function add_to_last_added(string $image, ?string $audio = null)
    {
        $last_note = self::get_latest_card();
        $note_info = self::anki_connect('notesInfo', ['notes' => [$last_note]]);

        if (is_null($note_info)) {
            anki_log('No note info! Aborting.', Urgency::critical);
            die;
        }

        $word = $note_info->result[0]->fields->{FRONT_FIELD}->value;

        $current_image = $note_info->result[0]->fields->{IMAGE_FIELD}->value;

        if (!empty($current_image) && $current_image != '<img src="">') {
            anki_log("Image field in newest card ({$word}) is not empty! Aborting");
            die;
        }

        $new_fields = [
            IMAGE_FIELD => "<img src='$image'>",
            SENTENCE_AUDIO_FIELD => $audio ? "[sound:$audio]" : null,
        ];

        // Remove null values from the array. This will also filter out the clipboard content if there is none set.
        $new_fields = array_filter($new_fields);

        self::anki_connect('updateNoteFields', [
            'note' => [
                'id' => $last_note,
                'fields' => $new_fields
            ]
        ]);

        anki_log("Successfully added to word: $word");
    }
}
