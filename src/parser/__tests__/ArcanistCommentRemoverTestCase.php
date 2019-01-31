<?php

final class ArcanistCommentRemoverTestCase extends PhutilTestCase {

  public function testRemover() {
    $test = <<<EOTEXT
Here is a list:

  # Stuff
  # More Stuff

The end.

# Instructional comments.
# Appear here.
# At the bottom.
EOTEXT;

    $expect = <<<EOTEXT
Here is a list:

  # Stuff
  # More Stuff

The end.

EOTEXT;

    $this->assertEqual($expect, ArcanistCommentRemover::removeComments($test));

    $test = <<<EOTEXT
Subscribers:
#projectname

# Instructional comments.
EOTEXT;

    $expect = <<<EOTEXT
Subscribers:
#projectname

EOTEXT;

    $this->assertEqual($expect, ArcanistCommentRemover::removeComments($test));
  }

}
