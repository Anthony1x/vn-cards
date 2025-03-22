<?php

declare(strict_types=1);

require_once 'anki_connect.php';

$cards = get_cards_by_tag();

var_dump($cards);