<?php

declare(strict_types=1);

namespace App\Core;

class Logger
{
    public static function log(string $message, Urgency $loglevel = Urgency::low): void
    {
        // See `man notify-send` if you want to know what all these do.
        $safe_message = escapeshellarg($message);
        shell_exec("notify-send -a ankivn -r 6969 -u {$loglevel->name} -t 5000 $safe_message");
    }
}
