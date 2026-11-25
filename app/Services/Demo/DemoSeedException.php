<?php

namespace App\Services\Demo;

use RuntimeException;

/** A seed precondition or verification failed. The message is safe to print (no secrets). */
final class DemoSeedException extends RuntimeException {}
