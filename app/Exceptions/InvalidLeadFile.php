<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An uploaded lead spreadsheet that cannot be used. The message is safe to show to the admin.
 */
class InvalidLeadFile extends RuntimeException {}
