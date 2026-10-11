<?php

declare(strict_types=1);

namespace App\Library\Domain;

use Throwable;

/**
 * An answer the library gives on purpose: its message is written to be read by whoever asked, a reader or a model.
 * Anything else that is thrown is a failure, and its message stays inside.
 */
interface Refusal extends Throwable
{
}
