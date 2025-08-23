<?php

enum Urgency: string
{
    case low = 'low';
    case normal = 'normal';
    case critical = 'critical';
}

function anki_log(string $message, Urgency $loglevel = Urgency::low)
{
    // See `man notify-send` if you want to know what all these do.
    shell_exec("notify-send -a ankivn -r 6969 -u {$loglevel->name} -t 5000 '$message'");
}


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