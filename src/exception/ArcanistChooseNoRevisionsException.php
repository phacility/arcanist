<?php

/**
 * Thrown when there are no valid revisions to choose from, in a workflow which
 * prompts the user to choose a revision.
 */
final class ArcanistChooseNoRevisionsException extends Exception {}
