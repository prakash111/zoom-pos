<?php

namespace App\Exceptions;

/**
 * The remote server confirmed the account/credentials are valid, but this
 * device failed to write the local offline mirror (Plan/Company/User rows,
 * sync credentials). Deliberately distinct from "invalid credentials" or
 * "server unreachable" — those two look identical to the user (login just
 * fails), but this one is a different problem with a different fix: the
 * account is fine, retrying the same password will not help, the local
 * SQLite state needs repairing (e.g. a fresh migrate).
 */
class DesktopLocalProvisioningException extends \RuntimeException {}
