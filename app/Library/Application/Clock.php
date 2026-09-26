<?php

declare(strict_types=1);

namespace App\Library\Application;

use DateTimeImmutable;

interface Clock
{
    public function today(): DateTimeImmutable;
}
