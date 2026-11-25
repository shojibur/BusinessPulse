<?php

namespace App\Services\Imports;

use RuntimeException;

/**
 * An import action that is not allowed in the current state. The message is safe to show the user.
 */
class ImportActionException extends RuntimeException {}
