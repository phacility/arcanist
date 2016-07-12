<?php

final class ArcanistBaseCommitParserTestCase extends PhutilTestCase {

  public function testBasics() {
    // Verify that the very basics of base commit resolution work.
    $this->assertCommit(
      pht('Empty Rules'),
      null,
      array(
      ));

    $this->assertCommit(
      'Literal',
      'xyz',
      array(
        'runtime' => 'literal:xyz',
      ));
  }

  public function testResolutionOrder() {
    // Rules should be resolved in order: args, local, project, global. These
    // test cases intentionally scramble argument order to test that resolution
    // order is independent of argument order.
    $this->assertCommit(
      pht('Order: Args'),
      'y',
      array(
        'local'   => 'literal:n',
        'project' => 'literal:n',
        'runtime' => 'literal:y',
        'user'    => 'literal:n',
      ));

    $this->assertCommit(
      pht('Order: Local'),
      'y',
      array(
        'project' => 'literal:n',
        'local'   => 'literal:y',
        'user'    => 'literal:n',
      ));

    $this->assertCommit(
      pht('Order: Project'),
      'y',
      array(
        'project' => 'literal:y',
        'user'    => 'literal:n',
      ));

    $this->assertCommit(
      pht('Order: Global'),
      'y',
      array(
        'user' => 'literal:y',
      ));
  }

  public function testLegacyRule() {
    // 'global' should translate to 'user'
    $this->assertCommit(
      pht('"%s" name', 'global'),
      'y',
      array(
        'runtime' => 'arc:global, arc:halt',
        'local'   => 'arc:halt',
        'project' => 'arc:halt',
        'user'    => 'literal:y',
      ));

    // 'args' should translate to 'runtime'
    $this->assertCommit(
      pht('"%s" name', 'args'),
      'y',
      array(
        'runtime' => 'arc:project, literal:y',
        'local'   => 'arc:halt',
        'project' => 'arc:args',
        'user'    => 'arc:halt',
      ));
  }

  public function testHalt() {
    // 'arc:halt' should halt all processing.
    $this->assertCommit(
      pht('Halt'),
      null,
      array(
        'runtime' => 'arc:halt',
        'local'   => 'literal:xyz',
      ));
  }

  public function testYield() {
    // 'arc:yield' should yield to other rulesets.
    $this->assertCommit(
      pht('Yield'),
      'xyz',
      array(
        'runtime' => 'arc:yield, literal:abc',
        'local'   => 'literal:xyz',
      ));

    // This one should return to 'runtime' after exhausting 'local'.
    $this->assertCommit(
      pht('Yield + Return'),
      'abc',
      array(
        'runtime' => 'arc:yield, literal:abc',
        'local'   => 'arc:skip',
      ));
  }

  public function testJump() {
    // This should resolve to 'abc' without hitting any of the halts.
    $this->assertCommit(
      pht('Jump'),
      'abc',
      array(
        'runtime' => 'arc:project, arc:halt',
        'local'   => 'literal:abc',
        'project' => 'arc:user, arc:halt',
        'user'    => 'arc:local, arc:halt',
      ));
  }

  public function testJumpReturn() {
    // After jumping to project, we should return to 'runtime'.
    $this->assertCommit(
      pht('Jump Return'),
      'xyz',
      array(
        'runtime' => 'arc:project, literal:xyz',
        'local'   => 'arc:halt',
        'project' => '',
        'user'    => 'arc:halt',
      ));
  }

  private function assertCommit($desc, $commit, $rules) {
    $parser = $this->buildParser();
    $result = $parser->resolveBaseCommit($rules);
    $this->assertEqual($commit, $result, $desc);
  }

  private function buildParser() {
    // TODO: This is a little hacky because we're using the Arcanist repository
    // itself to execute tests with, but it should be OK until we get proper
    // isolation for repository-oriented test cases.

    $root = dirname(phutil_get_library_root('arcanist'));
    $working_copy = ArcanistWorkingCopyIdentity::newFromPath($root);
    $configuration_manager = new ArcanistConfigurationManager();
    $configuration_manager->setWorkingCopyIdentity($working_copy);
    $repo = ArcanistRepositoryAPI::newAPIFromConfigurationManager(
      $configuration_manager);

    return new ArcanistBaseCommitParser($repo);
  }

}
