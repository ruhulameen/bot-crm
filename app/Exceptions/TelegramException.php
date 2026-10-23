<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Telegram problem with a message that is safe to show to an admin.
 */
class TelegramException extends RuntimeException {}
