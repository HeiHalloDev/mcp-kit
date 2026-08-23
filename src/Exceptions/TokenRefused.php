<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Exceptions;

use InvalidArgumentException;

/**
 * The minter refused to create a token; the message says why in words a
 * person can act on.
 */
final class TokenRefused extends InvalidArgumentException {}
