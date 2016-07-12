<?php

/**
 * Server which @{class:ArcanistHgProxyClient} clients connect to. This
 * server binds to a Mercurial working copy and creates a Mercurial process and
 * a unix domain socket in that working copy. It listens for connections on
 * the socket, reads commands from them, and forwards their requests to the
 * Mercurial process. It then returns responses to the original clients.
 *
 * Note that this server understands the underlying protocol and completely
 * decodes messages from both the client and server before re-encoding them
 * and relaying them to their final destinations. It must do this (at least
 * in part) to determine where messages begin and end. Additionally, this proxy
 * sends and receives the Mercurial cmdserver protocol exactly, without
 * any extensions or sneakiness.
 *
 * The advantage of this mechanism is that it avoids the overhead of starting
 * a Mercurial process for each Mercurial command, which can exceed 100ms per
 * invocation. This server can also accept connections from multiple clients
 * and serve them from a single Mercurial server process.
 *
 * @task construct  Construction
 * @task config     Configuration
 * @task server     Serving Requests
 * @task client     Managing Clients
 * @task hg         Managing Mercurial
 * @task internal   Internals
 */
final class ArcanistHgProxyServer extends Phobject {

  private $workingCopy;
  private $socket;
  private $hello;

  private $quiet;

  private $clientLimit;
  private $lifetimeClientCount;

  private $idleLimit;
  private $idleSince;

  private $skipHello;

  private $doNotDaemonize;


/* -(  Construction  )------------------------------------------------------- */


  /**
   * Build a new server. This server is bound to a working copy. The server
   * is inactive until you @{method:start} it.
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
   * Disable status messages to stdout. Controlled with `--quiet`.
   *
   * @param bool  True to disable status messages.
   * @return this
   *
   * @task config
   */
  public function setQuiet($quiet) {
    $this->quiet = $quiet;
    return $this;
  }


  /**
   * Configure a client limit. After serving this many clients, the server
   * will exit. Controlled with `--client-limit`.
   *
   * You can use `--client-limit 1` with `--xprofile` and `--do-not-daemonize`
   * to profile the server.
   *
   * @param int Client limit, or 0 to disable limit.
   * @return this
   *
   * @task config
   */
  public function setClientLimit($limit) {
    $this->clientLimit = $limit;
    return $this;
  }


  /**
   * Configure an idle time limit. After this many seconds idle, the server
   * will exit. Controlled with `--idle-limit`.
   *
   * @param int Idle limit, or 0 to disable limit.
   * @return this
   *
   * @task config
   */
  public function setIdleLimit($limit) {
    $this->idleLimit = $limit;
    return $this;
  }


  /**
   * When clients connect, do not send the "capabilities" message expected by
   * the Mercurial protocol. This deviates from the protocol and will only work
   * if the clients are also configured not to expect the message, but slightly
   * improves performance. Controlled with --skip-hello.
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


  /**
   * Configure whether the server runs in the foreground or daemonizes.
   * Controlled by --do-not-daemonize. Primarily useful for debugging.
   *
   * @param bool True to run in the foreground.
   * @return this
   *
   * @task config
   */
  public function setDoNotDaemonize($do_not_daemonize) {
    $this->doNotDaemonize = $do_not_daemonize;
    return $this;
  }


/* -(  Serving Requests  )--------------------------------------------------- */


  /**
   * Start the server. This method returns after the client limit or idle
   * limit are exceeded. If neither limit is configured, this method does not
   * exit.
   *
   * @return null
   *
   * @task server
   */
  public function start() {
    // Create the unix domain socket in the working copy to listen for clients.
    $socket = $this->startWorkingCopySocket();
    $this->socket = $socket;

    if (!$this->doNotDaemonize) {
      $this->daemonize();
    }

    // Start the Mercurial process which we'll forward client requests to.
    $hg = $this->startMercurialProcess();
    $clients = array();

    $this->log(null, pht('Listening'));
    $this->idleSince = time();
    while (true) {
      // Wait for activity on any active clients, the Mercurial process, or
      // the listening socket where new clients connect.
      PhutilChannel::waitForAny(
        array_merge($clients, array($hg)),
        array(
          'read'    => $socket ? array($socket) : array(),
          'except'  => $socket ? array($socket) : array(),
        ));

      if (!$hg->update()) {
        throw new Exception(pht('Server exited unexpectedly!'));
      }

      // Accept any new clients.
      while ($socket && ($client = $this->acceptNewClient($socket))) {
        $clients[] = $client;
        $key = last_key($clients);
        $client->setName($key);

        $this->log($client, pht('Connected'));
        $this->idleSince = time();

        // Check if we've hit the client limit. If there's a configured
        // client limit and we've hit it, stop accepting new connections
        // and close the socket.

        $this->lifetimeClientCount++;

        if ($this->clientLimit) {
          if ($this->lifetimeClientCount >= $this->clientLimit) {
            $this->closeSocket();
            $socket = null;
          }
        }
      }

      // Update all the active clients.
      foreach ($clients as $key => $client) {
        if ($this->updateClient($client, $hg)) {
          // In this case, the client is still connected so just move on to
          // the next one. Otherwise we continue below and handle the
          // disconnect.
          continue;
        }

        $this->log($client, pht('Disconnected'));
        unset($clients[$key]);

        // If we have a client limit and we've served that many clients, exit.

        if ($this->clientLimit) {
          if ($this->lifetimeClientCount >= $this->clientLimit) {
            if (!$clients) {
              $this->log(null, pht('Exiting (Client Limit)'));
              return;
            }
          }
        }
      }

      // If we have an idle limit and haven't had any activity in at least
      // that long, exit.
      if ($this->idleLimit) {
        $remaining = $this->idleLimit - (time() - $this->idleSince);
        if ($remaining <= 0) {
          $this->log(null, pht('Exiting (Idle Limit)'));
          return;
        }
        if ($remaining <= 5) {
          $this->log(null, pht('Exiting in %d seconds', $remaining));
        }
      }
    }
  }


  /**
   * Update one client, processing any commands it has sent us. We fully
   * process all commands we've received here before returning to the main
   * server loop.
   *
   * @param ArcanistHgClientChannel The client to update.
   * @param ArcanistHgServerChannel The Mercurial server.
   *
   * @task server
   */
  private function updateClient(
    ArcanistHgClientChannel $client,
    ArcanistHgServerChannel $hg) {

    if (!$client->update()) {
      // Client has disconnected, don't bother proceeding.
      return false;
    }

    // Read a command from the client if one is available. Note that we stop
    // updating other clients or accepting new connections while processing a
    // command, since there isn't much we can do with them until the server
    // finishes executing this command.
    $message = $client->read();
    if (!$message) {
      return true;
    }

    $this->log($client, '$ '.$message[0].' '.$message[1]);
    $t_start = microtime(true);

    // Forward the command to the server.
    $hg->write($message);

    while (true) {
      PhutilChannel::waitForAny(array($client, $hg));

      if (!$client->update() || !$hg->update()) {
        // If either the client or server has exited, bail.
        return false;
      }

      $response = $hg->read();
      if (!$response) {
        continue;
      }

      // Forward the response back to the client.
      $client->write($response);

      // If the response was on the 'r'esult channel, it indicates the end
      // of the command output. We can process the next command (if any
      // remain) or go back to accepting new connections and servicing
      // other clients.
      if ($response[0] == 'r') {
        // Update the client immediately to try to get the bytes on the wire
        // as quickly as possible. This gives us slightly more throughput.
        $client->update();
        break;
      }
    }

    // Log the elapsed time.
    $t_end = microtime(true);
    $t = 1000000 * ($t_end - $t_start);
    $this->log($client, pht('< %sus', number_format($t, 0)));

    $this->idleSince = time();

    return true;
  }


/* -(  Managing Clients  )--------------------------------------------------- */


  /**
   * @task client
   */
  public static function getPathToSocket($working_copy) {
    return $working_copy.'/.hg/hgdaemon-socket';
  }


  /**
   * @task client
   */
  private function startWorkingCopySocket() {
    $errno = null;
    $errstr = null;

    $socket_path = self::getPathToSocket($this->workingCopy);
    $socket_uri  = 'unix://'.$socket_path;

    $socket = @stream_socket_server($socket_uri, $errno, $errstr);
    if ($errno || !$socket) {
      Filesystem::remove($socket_path);
      $socket = @stream_socket_server($socket_uri, $errno, $errstr);
    }

    if ($errno || !$socket) {
      throw new Exception(
        pht(
          'Unable to start socket! Error #%d: %s',
          $errno,
          $errstr));
    }

    $ok = stream_set_blocking($socket, 0);
    if ($ok === false) {
      throw new Exception(pht('Unable to set socket nonblocking!'));
    }

    return $socket;
  }


  /**
   * @task client
   */
  private function acceptNewClient($socket) {
    // NOTE: stream_socket_accept() always blocks, even when the socket has
    // been set nonblocking.
    $new_client = @stream_socket_accept($socket, $timeout = 0);
    if (!$new_client) {
      return null;
    }

    $channel = new PhutilSocketChannel($new_client);
    $client = new ArcanistHgClientChannel($channel);

    if (!$this->skipHello) {
      $client->write($this->hello);
    }

    return $client;
  }


/* -(  Managing Mercurial  )------------------------------------------------- */


  /**
   * Starts a Mercurial process which can actually handle requests.
   *
   * @return ArcanistHgServerChannel  Channel to the Mercurial server.
   * @task hg
   */
  private function startMercurialProcess() {
    // NOTE: "cmdserver.log=-" makes Mercurial use the 'd'ebug channel for
    // log messages.

    $future = new ExecFuture(
      'HGPLAIN=1 hg --config cmdserver.log=- serve --cmdserver pipe');
    $future->setCWD($this->workingCopy);

    $channel = new PhutilExecChannel($future);
    $hg = new ArcanistHgServerChannel($channel);

    // The server sends a "hello" message with capability and encoding
    // information. Save it and forward it to clients when they connect.
    $this->hello = $hg->waitForMessage();

    return $hg;
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * Close and remove the unix domain socket in the working copy.
   *
   * @task internal
   */
  public function __destruct() {
    $this->closeSocket();
  }

  private function closeSocket() {
    if ($this->socket) {
      @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
      @fclose($this->socket);
      Filesystem::remove(self::getPathToSocket($this->workingCopy));
      $this->socket = null;
    }
  }

  private function log($client, $message) {
    if ($this->quiet) {
      return;
    }

    if ($client) {
      $message = sprintf(
        '[%s] %s',
        pht('Client %s', $client->getName()),
        $message);
    } else {
      $message = sprintf(
        '[%s] %s',
        pht('Server'),
        $message);
    }

    echo $message."\n";
  }

  private function daemonize() {
    // Keep stdout if it's been redirected somewhere, otherwise shut it down.
    $keep_stdout = false;
    $keep_stderr = false;
    if (function_exists('posix_isatty')) {
      if (!posix_isatty(STDOUT)) {
        $keep_stdout = true;
      }
      if (!posix_isatty(STDERR)) {
        $keep_stderr = true;
      }
    }

    $pid = pcntl_fork();
    if ($pid === -1) {
      throw new Exception(pht('Unable to fork!'));
    } else if ($pid) {
      // We're the parent; exit. First, drop our reference to the socket so
      // our __destruct() doesn't tear it down; the child will tear it down
      // later.
      $this->socket = null;
      exit(0);
    }

    // We're the child; continue.

    fclose(STDIN);

    if (!$keep_stdout) {
      fclose(STDOUT);
      $this->quiet = true;
    }

    if (!$keep_stderr) {
      fclose(STDERR);
    }
  }

}
