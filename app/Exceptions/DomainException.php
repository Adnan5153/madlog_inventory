<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a business-rule violation is hit. The HTTP layer maps this
 * to a 422 response with the exception message in the session as an error.
 */
class DomainException extends RuntimeException {}
