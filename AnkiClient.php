<?php

class AnkiClient
{
    private static ?self $instance = null;
    private const string ANKI_PORT = "8765";
    private const string ANKI_URL = "http://localhost";

    public static function get_instance(): self
    {
        if (self::$instance === null)
            self::$instance = new self();

        return self::$instance;
    }

    /**
     * The core of this app. All calls to anki go through this function.
     *
     * @param string $action
     * @param array $params
     * @return mixed
     */
    public function anki_connect(string $action, array $params)
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

        if ($err) {
            anki_log("cURL reported error: $err, aborting.", Urgency::critical);
            die;
        }

        return json_decode($response);
    }

    public function get_latest_card()
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

    public function add_tags_to_card($card, string ...$tags)
    {
        return self::anki_connect('updateNote', [
            'note' => [
                'id' => $card,
                'tags' => $tags
            ]
        ]);
    }

    public function get_all_cards(string $deck = DECK_NAME)
    {
        $note_ids = self::anki_connect('findCards', ['query' => $deck])->result;
        $note_info = self::anki_connect('cardsInfo', ['cards' => $note_ids])->result;

        return array_filter((array)$note_info, fn($note) => !empty((array)$note));
    }

    public function get_all_notes(string $deck = DECK_NAME)
    {
        $res = self::anki_connect('findCards', ['query' => $deck])->result;
        $note_info = self::anki_connect('notesInfo', ['notes' => $res])->result;

        return array_filter((array)$note_info, fn($note) => !empty((array)$note));
    }

    /**
     *
     * @param bool $with_frequency
     * @return ($with_frequency is false ? array<string, int> : array<string, array{Count: int, Freq: int}>)
     */
    public function get_cards_by_tag(bool $with_frequency = false)
    {
        $note_info = self::get_all_notes();

        if ($with_frequency) {
            $tag_data = [];
            foreach ($note_info as $note) {
                // Ensure the FreqSort field exists and has a numeric value. Default to 9999999 if not.
                $freq_value = (int)($note->fields->FreqSort->value ?? 9999999);

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
     *
     * @param int $days How many days back to check for cards to replace. Can technically be omitted, but saves tons of time.
     */
    function replace_with_newer_card(int $days = 1)
    {
        $recent_note_ids = self::anki_connect('findNotes', ['query' => DECK_NAME . ' added:' . $days])->result;

        if (empty($recent_note_ids)) {
            echo "No recently added notes to check for duplicates.\n";
            return;
        }

        // 2. Get the info for ONLY these recent notes.
        foreach (self::anki_connect('notesInfo', ['notes' => $recent_note_ids])->result as $recent_note) {

            $key_field_value = $recent_note->fields->{FRONT_FIELD}->value;

            $safe_key_value = addslashes($key_field_value);

            // 3. For each recent note, ask Anki to find ALL notes (old and new) with the same front field.
            $duplicate_note_ids = self::anki_connect('findNotes', ['query' => DECK_NAME . ' ' . FRONT_FIELD . ':' . $safe_key_value])->result;

            // 4. If Anki finds more than one note, we have a duplicate group to process.
            if (count($duplicate_note_ids) < 2)
                continue;

            echo "Duplicate found for: '{$safe_key_value}'\n";

            $duplicate_group_info = self::anki_connect('notesInfo', ['notes' => $duplicate_note_ids])->result;

            // Sort the group by note ID (older notes have smaller IDs).
            usort($duplicate_group_info, fn($a, $b) => $a->noteId <=> $b->noteId);

            $old = $duplicate_group_info[0];
            $new = end($duplicate_group_info);

            $f = $new->fields;

            if ($old->fields->ExpressionReading->value != $f->ExpressionReading->value) {
                echo "  -> Reading difference detected. Expression: " . $old->fields->{FRONT_FIELD}->value .
                    " | Reading 1: " . $old->fields->ExpressionReading->value .
                    " | Reading 2: " . $f->ExpressionReading->value . "\n";
                echo "  -> Skipping merge.\n";

                continue;
            }

            echo "  -> Merging new data into the old card...\n";
            $res = self::anki_connect('updateNoteFields', [
                'note' => [
                    'id' => $old->noteId,
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
                throw new Exception("Error updating note fields.");
            }

            self::add_tags_to_card($old->noteId, "Retag");

            echo "  -> Deleting the newer, redundant note...\n";
            $res = self::anki_connect('deleteNotes', [
                'notes' => [$new->noteId]
            ]);

            if (!is_null($res->error)) {
                var_dump($res);
                throw new Exception("Error deleting the new note.");
            }
        }

        echo "Duplicate check complete.\n";
    }

    public function add_to_last_added(string $image, ?string $audio = null)
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

    public function get_cards_with_frequency()
    {
        $displayWidth = function (string $string) {
            $width = 0;
            for ($i = 0; $i < mb_strlen($string, 'UTF-8'); $i++) {
                $char = mb_substr($string, $i, 1, 'UTF-8');
                // Check for double-width characters (Hiragana, Katakana, and Kanji)
                if (preg_match('/[ぁ-んァ-ヶ一-龯]/u', $char)) {
                    $width += 2;
                } else {
                    $width += 1;
                }
            }
            return $width;
        };

        $cards = $this->get_cards_by_tag(with_frequency: true);

        $max_display_width = 0;
        foreach (array_keys($cards) as $key) {
            $max_display_width = max($max_display_width, $displayWidth($key));
        }

        foreach ($cards as $key => $value) {
            $current_display_width = $displayWidth($key);
            $padding_length = $max_display_width - $current_display_width;
            $padding = str_repeat(' ', $padding_length);
            echo $key . $padding . " |\t{$value['Count']}\t| {$value['Freq']}\n";
        }
    }
}
