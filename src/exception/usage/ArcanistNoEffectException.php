<?php

/**
 * Thrown when lint or unit tests have no effect, i.e. no paths are affected
 * by any linter or no unit tests provide coverage.
 *
 * @group workflow
 */
final class ArcanistNoEffectException extends ArcanistUsageException {
}
