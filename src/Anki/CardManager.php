<?php

declare(strict_types=1);

namespace App\Anki;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Urgency;
use Exception;
use RuntimeException;

class CardManager
{
    private AnkiConnect $anki;
    private array $_cached_notes = [];
    private array $_cached_cards = [];

    public function __construct(AnkiConnect $anki)
    {
        $this->anki = $anki;
    }

    public function getLatestNoteId(): int
    {
        $result = $this->anki->send('findNotes', ['query' => 'added:1'])->result;

        if (empty($result)) {
            $msg = 'No recently added cards found!';
            Logger::log($msg, Urgency::critical);
            throw new RuntimeException($msg);
        }

        return (int)(count($result) === 1 ? $result[0] : max(...$result));
    }

    public function addTags(int $noteId, string ...$tags): void
    {
        $this->anki->send('updateNote', [
            'note' => [
                'id' => $noteId,
                'tags' => $tags
            ]
        ]);
    }

    public function getAllCards(string $deck): array
    {
        if (isset($this->_cached_cards[$deck])) {
            return $this->_cached_cards[$deck];
        }

        $card_ids = $this->anki->send('findCards', ['query' => $deck])->result;
        $card_info = $this->anki->send('cardsInfo', ['cards' => $card_ids])->result;

        $this->_cached_cards[$deck] = array_filter((array)$card_info, fn($card) => !empty((array)$card));
        return $this->_cached_cards[$deck];
    }

    public function getAllNotes(string $deck): array
    {
        if (isset($this->_cached_notes[$deck])) {
            return $this->_cached_notes[$deck];
        }

        $note_ids = $this->anki->send('findNotes', ['query' => $deck])->result;
        $note_info = $this->anki->send('notesInfo', ['notes' => $note_ids])->result;

        $this->_cached_notes[$deck] = array_values(array_filter((array)$note_info, fn($note) => !empty((array)$note)));
        return $this->_cached_notes[$deck];
    }

    public function updateNoteFields(int $noteId, array $fields): void
    {
        $res = $this->anki->send('updateNoteFields', [
            'note' => [
                'id' => $noteId,
                'fields' => $fields
            ]
        ]);

        if (!empty($res->error)) {
            throw new Exception("Error updating note fields: " . $res->error);
        }
    }

    public function deleteNotes(array $noteIds): void
    {
        $res = $this->anki->send('deleteNotes', [
            'notes' => $noteIds
        ]);

        if (!empty($res->error)) {
            throw new Exception("Error deleting notes: " . $res->error);
        }
    }

    public function reassignPlaceholderFrequencies(): void
    {
        $json_path = __DIR__ . '/../../assigned_frequencies.json';
        $assigned_frequencies = [];
        if (file_exists($json_path)) {
            $assigned_frequencies = json_decode(file_get_contents($json_path), true) ?: [];
        }

        $deck = Config::get('DECK_NAME');
        $notes = $this->getAllNotes($deck);
        $placeholder_notes = [];

        foreach ($notes as $note) {
            $freq_value = (int)($note->fields->FreqSort->value ?? 9999999);
            if ($freq_value === 9999999) {
                $placeholder_notes[] = $note;
            }
        }

        if (empty($placeholder_notes)) {
            echo "No placeholder notes found.\n";
            return;
        }

        $min_freq = 10000;
        $max_freq = 50000;

        echo "Valid frequency range: $min_freq to $max_freq\n";
        echo "Found " . count($placeholder_notes) . " placeholder notes.\n";

        $updated_count = 0;
        foreach ($placeholder_notes as $note) {
            $note_id = (string)$note->noteId;
            $new_freq = $assigned_frequencies[$note_id] ?? rand($min_freq, $max_freq);
            $assigned_frequencies[$note_id] = $new_freq;

            $this->updateNoteFields((int)$note->noteId, [
                'FreqSort' => (string)$new_freq
            ]);
            $updated_count++;
        }

        file_put_contents($json_path, json_encode($assigned_frequencies, JSON_PRETTY_PRINT));
        echo "Successfully updated $updated_count notes.\n";
    }

    public function getCompounds(int $characters = 4): array
    {
        $deck = Config::get('DECK_NAME');
        $frontField = Config::get('FRONT_FIELD');
        $notes = $this->getAllNotes($deck);
        $compounds = [];

        foreach ($notes as $note) {
            $value = $note->fields->{$frontField}->value ?? '';
            if (preg_match('/^\p{Han}{' . $characters . '}$/u', $value)) {
                $compounds[] = $value;
            }
        }

        return array_unique($compounds);
    }

    public function contractDots(): void
    {
        $deck = Config::get('DECK_NAME');
        $notes = $this->getAllNotes($deck);

        foreach ($notes as $note) {
            $input1 = $note->fields->Sentence->value;
            $input2 = $note->fields->SentenceFurigana->value;

            $pattern = '/・{4,}/u';
            $replace = '・';

            if (!str_contains($input1, $replace) && !str_contains($input2, $replace)) {
                continue;
            }

            $output1 = preg_replace($pattern, $replace, $input1);
            $output2 = preg_replace($pattern, $replace, $input2);

            if ($input1 !== $output1 || $input2 !== $output2) {
                $this->updateNoteFields((int)$note->noteId, [
                    'Sentence' => $output1,
                    'SentenceFurigana' => $output2
                ]);
                echo "Contracted dots for note ID: {$note->noteId}\n";
            }
        }
    }

    public function getCardsWithoutBoldFurigana(): array
    {
        $deck = Config::get('DECK_NAME');
        $notes = $this->getAllNotes($deck);

        return array_filter($notes, fn($note) => !str_contains($note->fields->Sentence->value, "<b>"));
    }

    public function getMediaStats(): array
    {
        $deck = Config::get('DECK_NAME');
        $notes = $this->getAllNotes($deck);

        $imageField = Config::get('IMAGE_FIELD');
        $audioField = Config::get('SENTENCE_AUDIO_FIELD');

        $stats = [
            'image_no_audio' => 0,
            'image_audio' => 0,
            'neither' => 0,
            'audio_no_image' => 0,
            'total' => count($notes)
        ];

        foreach ($notes as $note) {
            $hasImage = !empty($note->fields->{$imageField}->value) && $note->fields->{$imageField}->value !== '<img src="">';
            $hasAudio = !empty($note->fields->{$audioField}->value);

            if ($hasImage && !$hasAudio) {
                $stats['image_no_audio']++;
            } elseif ($hasImage && $hasAudio) {
                $stats['image_audio']++;
            } elseif (!$hasImage && !$hasAudio) {
                $stats['neither']++;
            } elseif (!$hasImage && $hasAudio) {
                $stats['audio_no_image']++;
            }
        }

        return $stats;
    }

    public function getDueStats(string $time): array
    {
        $targetTimestamp = strtotime($time);
        if ($targetTimestamp === false) {
            throw new RuntimeException("Invalid time string: $time");
        }

        $now = time();
        $diffSeconds = $targetTimestamp - $now;
        $diffDays = (int)ceil($diffSeconds / 86400);

        $deck = Config::get('DECK_NAME');
        
        $queryOnDay = "$deck is:review prop:due=$diffDays";
        $resOnDay = $this->anki->send('findCards', ['query' => $queryOnDay]);
        
        $queryCumulative = "$deck is:review prop:due>=$diffDays";
        $resCumulative = $this->anki->send('findCards', ['query' => $queryCumulative]);

        return [
            'diffDays' => $diffDays,
            'onDay' => count($resOnDay->result),
            'cumulative' => count($resCumulative->result)
        ];
    }

    public function addToLastAdded(string $image, ?string $audio = null): void
    {
        $lastNoteId = $this->getLatestNoteId();
        $note_info = $this->anki->send('notesInfo', ['notes' => [$lastNoteId]]);

        if (empty($note_info->result)) {
            $msg = 'No note info! Aborting.';
            Logger::log($msg, Urgency::critical);
            throw new RuntimeException($msg);
        }

        $note = $note_info->result[0];
        $frontField = Config::get('FRONT_FIELD');
        $imageField = Config::get('IMAGE_FIELD');
        $audioField = Config::get('SENTENCE_AUDIO_FIELD');

        $word = $note->fields->{$frontField}->value;
        $current_image = $note->fields->{$imageField}->value;

        if (!empty($current_image) && $current_image !== '<img src="">') {
            $msg = "Image field in newest card ({$word}) is not empty! Aborting";
            Logger::log($msg, Urgency::critical);
            throw new RuntimeException($msg);
        }

        $new_fields = [
            $imageField => "<img src='$image'>",
            $audioField => $audio ? "[sound:$audio]" : null,
        ];

        $new_fields = array_filter($new_fields);

        $this->updateNoteFields($lastNoteId, $new_fields);

        Logger::log("Successfully added to word: $word");
    }

    public function getDeduplicationPreview(int $days = 1): int
    {
        $eligibleCount = 0;
        $duplicates = $this->findDuplicateGroups($days);

        foreach ($duplicates as $group) {
            $old = $group['old'];
            $new = $group['new'];

            if ($old->fields->ExpressionReading->value === $new->fields->ExpressionReading->value) {
                $eligibleCount++;
            }
        }

        return $eligibleCount;
    }

    private function findDuplicateGroups(int $days = 1): array
    {
        $deck = Config::get('DECK_NAME');
        $frontField = Config::get('FRONT_FIELD');
        $recent_note_ids = $this->anki->send('findNotes', ['query' => "$deck added:$days"])->result;

        if (empty($recent_note_ids)) {
            return [];
        }

        $notes = $this->anki->send('notesInfo', ['notes' => $recent_note_ids])->result;
        $groups = [];
        $processed = [];

        foreach ($notes as $recent_note) {
            $key_field_value = $recent_note->fields->{$frontField}->value;
            if (isset($processed[$key_field_value])) continue;
            
            $safe_key_value = addslashes($key_field_value);
            $duplicate_note_ids = $this->anki->send('findNotes', [
                'query' => "$deck $frontField:\"$safe_key_value\""
            ])->result;

            if (count($duplicate_note_ids) < 2) {
                $processed[$key_field_value] = true;
                continue;
            }

            $duplicate_group_info = $this->anki->send('notesInfo', ['notes' => $duplicate_note_ids])->result;
            usort($duplicate_group_info, fn($a, $b) => $a->noteId <=> $b->noteId);

            $groups[] = [
                'old' => $duplicate_group_info[0],
                'new' => end($duplicate_group_info)
            ];
            $processed[$key_field_value] = true;
        }

        return $groups;
    }

    public function replaceWithNewerCard(int $days = 1, array $extraTags = []): void
    {
        $frontField = Config::get('FRONT_FIELD');
        $duplicates = $this->findDuplicateGroups($days);

        foreach ($duplicates as $group) {
            $old = $group['old'];
            $new = $group['new'];
            $key_field_value = $old->fields->{$frontField}->value;

            if ($old->fields->ExpressionReading->value !== $new->fields->ExpressionReading->value) {
                echo "Duplicate found for: '{$key_field_value}'\n";
                echo "  -> Reading difference detected. Skipping merge.\n";
                continue;
            }

            echo "Duplicate found for: '{$key_field_value}'\n";
            echo "  -> Merging new data into the old card...\n";
            
            $fieldsToUpdate = [];
            foreach ($new->fields as $fieldName => $fieldData) {
                if ($fieldName !== $frontField) {
                    $fieldsToUpdate[$fieldName] = $fieldData->value;
                }
            }

            $this->updateNoteFields((int)$old->noteId, $fieldsToUpdate);
            
            $tagsToAdd = array_unique(array_merge(["Retag"], $extraTags));
            $this->addTags((int)$old->noteId, ...$tagsToAdd);

            echo "  -> Deleting the newer, redundant note...\n";
            $this->deleteNotes([(int)$new->noteId]);
        }

        echo "Duplicate check complete.\n";
    }
}
