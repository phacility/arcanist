<?php

final class ArcanistCommentRemover extends Phobject {

  /**
   * Remove comment lines from a commit message. Strips trailing lines only,
   * and requires "#" to appear at the beginning of a line for it to be
   * considered a comment.
   */
  public static function removeComments($body) {
    $body = rtrim($body);

    $lines = phutil_split_lines($body);
    $lines = array_reverse($lines);

    foreach ($lines as $key => $line) {
      if (preg_match('/^#/', $line)) {
        unset($lines[$key]);
        continue;
      }

      break;
    }

    $lines = array_reverse($lines);
    $lines = implode('', $lines);
    $lines = rtrim($lines)."\n";

    return $lines;
  }

}
