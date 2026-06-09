<?php

declare(strict_types=1);

namespace App\Domain\Notification\Exceptions;

use LogicException;

class InvalidStatusTransitionException extends LogicException {}
