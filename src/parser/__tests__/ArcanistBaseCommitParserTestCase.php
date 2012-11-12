<?php

final class ArcanistBaseCommitParserTestCase extends ArcanistTestCase {

  public function testBasics() {

    // Verify that the very basics of base commit resolution work.

    $this->assertCommit(
      'Empty Rules',
      null,
      array(
      ));

    $this->assertCommit(
      'Literal',
      'xyz',
      array(
        'args' => 'literal:xyz',
      ));
  }

  public function testResolutionOrder() {

    // Rules should be resolved in order: args, local, project, global. These
    // test cases intentionally scramble argument order to test that resolution
    // order is independent of argument order.

    $this->assertCommit(
      'Order: Args',
      'y',
      array(
        'local'   => 'literal:n',
        'project' => 'literal:n',
        'args'    => 'literal:y',
        'global'  => 'literal:n',
      ));

    $this->assertCommit(
      'Order: Local',
      'y',
      array(
        'project' => 'literal:n',
        'local'   => 'literal:y',
        'global'  => 'literal:n',
      ));

    $this->assertCommit(
      'Order: Project',
      'y',
      array(
        'project' => 'literal:y',
        'global'  => 'literal:n',
      ));

    $this->assertCommit(
      'Order: Global',
      'y',
      array(
        'global'  => 'literal:y',
      ));
  }

  public function testHalt() {

    // 'arc:halt' should halt all processing.

    $this->assertCommit(
      'Halt',
      null,
      array(
        'args' => 'arc:halt',
        'local' => 'literal:xyz',
      ));
  }

  public function testYield() {

    // 'arc:yield' should yield to other rulesets.

    $this->assertCommit(
      'Yield',
      'xyz',
      array(
        'args'  => 'arc:yield, literal:abc',
        'local' => 'literal:xyz',
      ));

    // This one should return to 'args' after exhausting 'local'.

    $this->assertCommit(
      'Yield + Return',
      'abc',
      array(
        'args' => 'arc:yield, literal:abc',
        'local' => 'arc:skip',
      ));
  }

  public function testJump() {

    // This should resolve to 'abc' without hitting any of the halts.

    $this->assertCommit(
      'Jump',
      'abc',
      array(
        'args'    => 'arc:project, arc:halt',
        'local'   => 'literal:abc',
        'project' => 'arc:global, arc:halt',
        'global'  => 'arc:local, arc:halt',
      ));
  }

  public function testJumpReturn() {

    // After jumping to project, we should return to 'args'.

    $this->assertCommit(
      'Jump Return',
      'xyz',
      array(
        'args'    => 'arc:project, literal:xyz',
        'local'   => 'arc:halt',
        'project' => '',
        'global'  => 'arc:halt',
      ));
  }

  private function assertCommit($desc, $commit, $rules) {
    $parser = $this->buildParser();
    $result = $parser->resolveBaseCommit($rules);
    $this->assertEqual($commit, $result, $desc);
  }


  private function buildParser() {
    // TODO: This is a little hacky beacuse we're using the Arcanist repository
    // itself to execute tests with, but it should be OK until we get proper
    // isolation for repository-oriented test cases.

    $root = dirname(phutil_get_library_root('arcanist'));
    $copy = ArcanistWorkingCopyIdentity::newFromPath($root);
    $repo = ArcanistRepositoryAPI::newAPIFromWorkingCopyIdentity($copy);

    return new ArcanistBaseCommitParser($repo);
  }

}
