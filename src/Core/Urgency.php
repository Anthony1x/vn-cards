<?php

declare(strict_types=1);

namespace App\Core;

enum Urgency: string
{
    case low = 'low';
    case normal = 'normal';
    case critical = 'critical';
}
