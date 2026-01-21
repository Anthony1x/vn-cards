<?php

declare(strict_types=1);

require_once 'main.php';


$stamp = time();

$image_tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ankiscreenie.avif';

if (file_exists($image_tmp)) {
    $image = "anthony_custom_$stamp.avif";
    copy($image_tmp,  PREFIX . "/$image");
}

$audio_tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ankirecording.opus';

if (file_exists($audio_tmp)) {
    $audio = "anthony_custom_$stamp.opus";
    copy($audio_tmp, PREFIX . "/$audio");
}

$ankiclient->add_to_last_added($image, $audio ?? null);
