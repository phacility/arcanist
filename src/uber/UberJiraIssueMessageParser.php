<?php

// simple parser which treats first non empty line as issue title and
// description starts from next non empty line and goes until end of
// input or when comment starts (last block with `#` is treated as
// comment).
final class UberJiraIssueMessageParser extends Phobject {
   const COMMENT = '#';

   private function __construct() {}

   public static function parse($message) {
     $title = null;
     $description = array();
     $lines = explode("\n", $message);
     foreach ($lines as $line) {
       if (!$title) {
         $line = trim($line);
         if (!empty($line)) {
           $title = $line;
         }
         continue;
       }
       if (!$description) {
         $line = ltrim($line);
         if (empty($line)) {
           continue;
         }
       }
       $description[] = $line;
     }
     // remove lines starting with '# ' from end of description until
     // we find line not starting with such symbols
     if ($description) {
       $line = array_pop($description);
       $comment_detected = false;
       while (($line == '' && !$comment_detected) ||
         strncmp($line, self::COMMENT, 1) == 0) {

         $comment_detected |= strncmp($line, self::COMMENT, 2) == 0;
         $line = array_pop($description);
       }
       // remove last new line if necessary
       if ($line != '') {
         $description[] = $line;
       }
     }
     $description = implode("\n", $description);
     return array('title' => $title, 'description' => $description);
   }
}
