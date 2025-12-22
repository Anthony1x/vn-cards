<?php

declare(strict_types=1);

require_once "main.php";

/** @var AnkiClient $ankiclient */
// $ankiclient->replace_with_newer_card();

$ankiclient->get_cards_with_frequency();
