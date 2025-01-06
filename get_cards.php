<?php

require_once 'anki_connect.php';

$res = anki_connect('findCards', ['query' => 'deck:current'])->result;

$note_info = anki_connect('notesInfo', ['notes' => $res])->result;

$note_info = array_filter((array)$note_info, fn($note) => !empty((array)$note));

$no_sentence_audio = array_filter((array)$note_info, fn($note) => $note->fields->{SENTENCE_AUDIO_FIELD}->value == '');
$no_image =          array_filter((array)$note_info, fn($note) => $note->fields->{IMAGE_FIELD}->value == '');

$no_sentence_audio = array_map(fn($note) => ['expression' => $note->fields->{FRONT_FIELD}->value], $no_sentence_audio);
$no_image =          array_map(fn($note) => ['expression' => $note->fields->{FRONT_FIELD}->value], $no_image);

echo "CARDS WITHOUT AUDIO: " . count($no_sentence_audio) . "\n";
echo "CARDS WITHOUT IMAGE: " . count($no_image);
