<?php

final class PhageExecWorkflow
  extends PhageWorkflow {

  public function getWorkflowName() {
    return 'exec';
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('argv')
        ->setHelp(pht('Command to execute.'))
        ->setWildcard(true),
    );
  }

  public function getWorkflowInformation() {
    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Execute a Phage subprocess.'));
  }

  protected function runWorkflow() {
    $argv = $this->getArgument('argv');
    if (!$argv) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a command to execute using one or more arguments.'));
    }

    // This workflow is just a thin wrapper around running a subprocess as
    // its own process group leader.
    //
    // If the Phage parent process executes a subprocess and that subprocess
    // does not turn itself into a process group leader, sending "^C" to the
    // parent process will also send the signal to the subprocess. Phage
    // handles SIGINT as an input and we don't want it to propagate to children
    // by default.
    //
    // Some versions of Linux have a binary named "setsid" which does the same
    // thing, but this binary doesn't exist on macOS.

    // NOTE: This calls is documented as ever being able to fail. For now,
    // trust the documentation?

    $pid = posix_getpid();

    $pgid = posix_getpgid($pid);
    if ($pgid === false) {
      throw new Exception(pht('Call to "posix_getpgid(...)" failed!'));
    }

    // If this process was run directly from a shell with "phage exec ...",
    // we'll already be the process group leader. In this case, we don't need
    // to become the leader of a new session (and the call will fail if we
    // try).

    $is_leader = ($pid === $pgid);
    if (!$is_leader) {
      $sid = posix_setsid();
      if ($sid === -1) {
        throw new Exception(pht('Call to "posix_setsid()" failed!'));
      }
    }

    return phutil_passthru('exec -- %Ls', $argv);
  }

}
