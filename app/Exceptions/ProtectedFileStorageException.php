<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A protected file could not be written to its storage disk.
 *
 * Thrown before any stored_files row exists, so a failed write can never
 * leave a database record that points at a file which is not there.
 */
class ProtectedFileStorageException extends RuntimeException {}
