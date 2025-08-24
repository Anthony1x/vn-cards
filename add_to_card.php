<?php

declare(strict_types=1);

require_once 'main.php';

$do_not_record = false;

if (in_array('--do-not-record', $argv)) {
    $do_not_record = true;
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

$ankiclient->add_to_last_added($image, $audio ?? null);
