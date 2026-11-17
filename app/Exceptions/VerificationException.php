<?php

namespace App\Exceptions;

use Exception;

/** An onboarding transition that is not allowed in the party's current state. */
class VerificationException extends Exception {}
