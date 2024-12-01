<?php

// Tags

require_once 'anki_connect.php';

if (in_array('--start', $argv)) {
    $tag_to_add = START_HERE;
}

if (in_array('--stop', $argv)) {
    $tag_to_add = STOP_HERE;
}

if (is_null($tag_to_add)) {
    al_log("No option specified: Need --start or --stop");
}

$last_note = get_latest_card();

add_tags_to_card($last_note, $tag_to_add);

$expr = anki_connect('notesInfo', ['notes' => [$last_note]])->result[0]->fields->Expression->value;
al_log("Added $tag_to_add to card: $expr");
