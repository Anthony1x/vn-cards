<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Anki\AnkiConnect;
use App\Anki\CardManager;
use App\Core\Config;

$anki = new AnkiConnect();
$cardManager = new CardManager($anki);

$stamp = time();
$prefix = Config::get('PREFIX');

$image_tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ankiscreenie.avif';
$image = null;

if (file_exists($image_tmp)) {
    $image = "anthony_custom_$stamp.avif";
    copy($image_tmp, "$prefix/$image");
}

$audio_tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ankirecording.opus';
$audio = null;

if (file_exists($audio_tmp)) {
    $audio = "anthony_custom_$stamp.opus";
    copy($audio_tmp, "$prefix/$audio");
}

if ($image) {
    $cardManager->addToLastAdded($image, $audio);
} else {
    echo "No image found in temp directory.\n";
}
