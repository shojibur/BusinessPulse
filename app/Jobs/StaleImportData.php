<?php

namespace App\Jobs;

use Exception;

/**
 * A record this import meant to supersede is no longer active: the data changed under the job.
 */
final class StaleImportData extends Exception {}
