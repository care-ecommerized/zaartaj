<?php

namespace App\Currency;

use RuntimeException;

/**
 * Thrown when a conversion is asked to use a currency that is missing, inactive,
 * or carries a non-positive rate — i.e. money we cannot safely reason about.
 */
class CurrencyException extends RuntimeException {}
