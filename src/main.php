<?php

declare(strict_types=1);

require_once('helper.php');

define_keys();

require_once('AnkiClient.php');

$ankiclient = AnkiClient::get_instance();