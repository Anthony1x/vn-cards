<?php

declare(strict_types=1);

require_once 'anki_connect.php';

$do_not_record = false;
$test_env = false;

if (in_array('--do-not-record', $argv)) {
    $do_not_record = true;
}

if (in_array('--test-env', $argv)) {
    $test_env = true;
}

function add_to_last_added(string $image, ?string $audio = null, ?string $text = null)
{
    // Fuck yeah php
    global $do_not_record, $test_env;

    $last_note = get_latest_card();
    $note_info = anki_connect('notesInfo', ['notes' => [$last_note]]);

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

    anki_log("Successfully added to word: $word");
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

$image_tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ankiscreenie.webp';
$image = "anthony_custom_$stamp.webp";
copy($image_tmp,  PREFIX . "/$image");

if ($do_not_record === false) {
    $audio_tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ankirecording.wav';
    $audio = "anthony_custom_$stamp.wav";
    copy($audio_tmp, PREFIX . "/$audio");
}

add_to_last_added($image, $audio ?? null, null);
