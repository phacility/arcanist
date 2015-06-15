<?php

/**
 * Client for an @{class:ArcanistHgProxyServer}. This client talks to a PHP
 * process which serves as a proxy in front of a Mercurial server process.
 * The PHP proxy allows multiple clients to use the same Mercurial server.
 *
 * This class presents an API which is similar to the hg command-line API.
 *
 * Each client is bound to a specific working copy:
 *
 *   $working_copy = '/path/to/some/hg/working/copy/';
 *   $client = new ArcanistHgProxyClient($working_copy);
 *
 * For example, to run `hg log -l 5` via a client:
 *
 *   $command = array('log', '-l', '5');
 *   list($err, $stdout, $stderr) = $client->executeCommand($command);
 *
 * The advantage of using this complex mechanism is that commands run in this
 * way do not need to pay the startup overhead for hg and the Python runtime,
 * which is often on the order of 100ms or more per command.
 *
 * @task construct  Construction
 * @task config     Configuration
 * @task exec       Executing Mercurial Commands
 * @task internal   Internals
 */
final class ArcanistHgProxyClient extends Phobject {

  private $workingCopy;
  private $server;

  private $skipHello;


/* -(  Construction  )------------------------------------------------------- */


  /**
   * Build a new client. This client is bound to a working copy. A server
   * must already be running on this working copy for the client to work.
   *
   * @param string Path to a Mercurial working copy.
   *
   * @task construct
   */
  public function __construct($working_copy) {
    $this->workingCopy = Filesystem::resolvePath($working_copy);
  }


/* -(  Configuration  )------------------------------------------------------ */


  /**
   * When connecting, do not expect the "capabilities" message.
   *
   * @param bool True to skip the "capabilities" message.
   * @return this
   *
   * @task config
   */
  public function setSkipHello($skip) {
    $this->skipHello = $skip;
    return $this;
  }


/* -(  Executing Merucurial Commands  )-------------------------------------- */


  /**
   * Execute a command (given as a list of arguments) via the command server.
   *
   * @param list<string> A list of command arguments, like "log", "-l", "5".
   * @return tuple<int, string, string> Return code, stdout and stderr.
   *
   * @task exec
   */
  public function executeCommand(array $argv) {
    if (!$this->server) {

      try {
        $server = $this->connectToDaemon();
      } catch (Exception $ex) {
        $this->launchDaemon();
        $server = $this->connectToDaemon();
      }

      $this->server = $server;
    }
    $server = $this->server;

    // Note that we're adding "runcommand" to make the server run the command.
    // Theoretically the server supports other capabilities, but in practice
    // we are only concerned with "runcommand".

    $server->write(array_merge(array('runcommand'), $argv));

    // We'll get back one or more blocks of response data, ending with an 'r'
    // block which indicates the return code. Reconstitute these into stdout,
    // stderr and a return code.

    $stdout = '';
    $stderr = '';
    $err    = 0;

    $done = false;
    while ($message = $server->waitForMessage()) {

      // The $server channel handles decoding of the wire format and gives us
      // messages which look like this:
      //
      //   array('o', '<data...>');

      list($channel, $data) = $message;
      switch ($channel) {
        case 'o':
          $stdout .= $data;
          break;
        case 'e':
          $stderr .= $data;
          break;
        case 'd':
          // TODO: Do something with this? This is the 'debug' channel.
          break;
        case 'r':
          // NOTE: This little dance is because the value is emitted as a
          // big-endian signed 32-bit long. PHP has no flag to unpack() that
          // can unpack these, so we unpack a big-endian unsigned long, then
          // repack it as a machine-order unsigned long, then unpack it as
          // a machine-order signed long. This appears to produce the desired
          // result.
          $err = head(unpack('N', $data));
          $err = pack('L', $err);
          $err = head(unpack('l', $err));
          $done = true;
          break;
      }

      if ($done) {
        break;
      }
    }

    return array($err, $stdout, $stderr);
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * @task internal
   */
  private function connectToDaemon() {
    $errno = null;
    $errstr = null;

    $socket_path = ArcanistHgProxyServer::getPathToSocket($this->workingCopy);
    $socket = @stream_socket_client('unix://'.$socket_path, $errno, $errstr);

    if ($errno || !$socket) {
      throw new Exception(
        pht(
          'Unable to connect socket! Error #%d: %s',
          $errno,
          $errstr));
    }

    $channel = new PhutilSocketChannel($socket);
    $server = new ArcanistHgServerChannel($channel);

    if (!$this->skipHello) {
      // The protocol includes a "hello" message with capability and encoding
      // information. Read and discard it, we use only the "runcommand"
      // capability which is guaranteed to be available.
      $hello = $server->waitForMessage();
    }

    return $server;
  }


  /**
   * @task internal
   */
  private function launchDaemon() {
    $root = dirname(phutil_get_library_root('arcanist'));
    $bin = $root.'/scripts/hgdaemon/hgdaemon_server.php';
    $proxy = new ExecFuture(
      '%s %s --idle-limit 15 --quiet %C',
      $bin,
      $this->workingCopy,
      $this->skipHello ? '--skip-hello' : null);
    $proxy->resolvex();
  }

}
