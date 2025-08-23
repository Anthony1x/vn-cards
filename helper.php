<?php

function define_keys()
{
    $env = parse_ini_file('.env');

    $consts = [
        define('DECK_NAME', $env['DECK_NAME']),
        define('FRONT_FIELD', $env['FRONT_FIELD']),
        define('SENTENCE_AUDIO_FIELD', $env['SENTENCE_AUDIO_FIELD']),
        define('SENTENCE_FIELD', $env['SENTENCE_FIELD']),
        define('IMAGE_FIELD', $env['IMAGE_FIELD']),
        define('PREFIX', $env['PREFIX']),
    ];

    if (array_any($consts, fn($const) => !$const)) {
        throw new Exception("At least one necessary key is missing! Consult the example .env");
    }
}