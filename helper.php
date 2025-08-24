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

/**
 * This does not follow DRY principles at all, but I haven't found a better way whilst ensuring the LSP is happy.
 * @return void
 * @throws \Exception
 */
function define_keys()
{
    $env = parse_ini_file('.env');

    $necessary_keys = [
        $env['DECK_NAME'],
        $env['FRONT_FIELD'],
        $env['SENTENCE_AUDIO_FIELD'],
        $env['SENTENCE_FIELD'],
        $env['IMAGE_FIELD'],
        $env['PREFIX']
    ];

    if (array_any($necessary_keys, fn($const) => !$const)) {
        $message = "At least one necessary key is missing! Consult the example .env, aborting";
        anki_log($message, Urgency::critical);
        throw new Exception($message);
    }

    define('DECK_NAME',             $env['DECK_NAME']);
    define('FRONT_FIELD',           $env['FRONT_FIELD']);
    define('SENTENCE_AUDIO_FIELD',  $env['SENTENCE_AUDIO_FIELD']);
    define('SENTENCE_FIELD',        $env['SENTENCE_FIELD']);
    define('IMAGE_FIELD',           $env['IMAGE_FIELD']);
    define('PREFIX',                $env['PREFIX']);
}
