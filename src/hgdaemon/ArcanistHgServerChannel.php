<?php

/**
 * Channel to a Mercurial "cmdserver" server. Messages sent to the server
 * look like this:
 *
 *   runcommand\n
 *   8                   # Length, as a 4-byte unsigned long.
 *   log\0
 *   -l\0
 *   5
 *
 * In PHP, the format of these messages is an array of arguments:
 *
 *   array(
 *     'runcommand',
 *     'log',
 *     '-l',
 *     '5',
 *   );
 *
 * The server replies with messages that look like this:
 *
 *   o
 *   1234                # Length, as a 4-byte unsigned long.
 *   <data: 1234 bytes>
 *
 * The first character in a message from the server is the "channel". Mercurial
 * channels have nothing to do with Phutil channels; they are more similar to
 * stdout/stderr. Mercurial has four primary channels:
 *
 *   'o'utput, like stdout
 *   'e'rror, like stderr
 *   'r'esult, like return codes
 *   'd'ebug, like an external log file
 *
 * In PHP, the format of these messages is a pair, with the channel and then
 * the data:
 *
 *   array('o', '<data...>');
 *
 * In general, we send "runcommand" requests, and the server responds with
 * a series of messages on the "output" channel and then a single response
 * on the "result" channel to indicate that output is complete.
 *
 * @task protocol Protocol Implementation
 */
final class ArcanistHgServerChannel extends PhutilProtocolChannel {

  const MODE_CHANNEL = 'channel';
  const MODE_LENGTH  = 'length';
  const MODE_BLOCK   = 'block';

  private $mode                   = self::MODE_CHANNEL;
  private $byteLengthOfNextChunk  = 1;
  private $buf                    = '';
  private $outputChannel;


/* -(  Protocol Implementation  )-------------------------------------------- */


  /**
   * Encode a message for transmission to the server. The message should be
   * formatted as an array, like this:
   *
   *   array(
   *     'runcommand',
   *     'log',
   *     '-l',
   *     '5',
   *   );
   *
   *
   * We will return the cmdserver version of this:
   *
   *   runcommand\n
   *   8                   # Length, as a 4-byte unsigned long.
   *   log\0
   *   -l\0
   *   5
   *
   * @param list<string> List of command arguments.
   * @return string Encoded string for transmission to the server.
   *
   * @task protocol
   */
  protected function encodeMessage($argv) {
    if (!is_array($argv)) {
      throw new Exception(
        pht('Message to Mercurial server should be an array.'));
    }

    $command = head($argv);
    $args = array_slice($argv, 1);

    $args = implode("\0", $args);

    $len = strlen($args);
    $len = pack('N', $len);

    return "{$command}\n{$len}{$args}";
  }


  /**
   * Decode a message received from the server. The message looks like this:
   *
   *   o
   *   1234                # Length, as a 4-byte unsigned long.
   *   <data: 1234 bytes>
   *
   * ...where 'o' is the "channel" the message is being sent over.
   *
   * We decode into a pair in PHP, which looks like this:
   *
   *   array('o', '<data...>');
   *
   * @param string Bytes from the server.
   * @return list<pair<string,string>> Zero or more complete messages.
   *
   * @task protocol
   */
  protected function decodeStream($data) {
    $this->buf .= $data;

    // We always know how long the next chunk is, so this parser is fairly
    // easy to implement.

    $messages = array();
    while ($this->byteLengthOfNextChunk <= strlen($this->buf)) {
      $chunk = substr($this->buf, 0, $this->byteLengthOfNextChunk);
      $this->buf = substr($this->buf, $this->byteLengthOfNextChunk);

      switch ($this->mode) {
        case self::MODE_CHANNEL:
          // We've received the channel name, one of 'o', 'e', 'r' or 'd' for
          // 'output', 'error', 'result' or 'debug' respectively. This is a
          // single byte long. Next, we'll expect a length.

          $this->outputChannel = $chunk;
          $this->byteLengthOfNextChunk = 4;
          $this->mode = self::MODE_LENGTH;
          break;
        case self::MODE_LENGTH:
          // We've received the length of the data, as a 4-byte big-endian
          // unsigned integer. Next, we'll expect the data itself.

          $this->byteLengthOfNextChunk = head(unpack('N', $chunk));
          $this->mode = self::MODE_BLOCK;
          break;
        case self::MODE_BLOCK:
          // We've received the data itself, which is a block of bytes of the
          // given length. We produce a message from the channel and the data
          // and return it. Next, we expect another channel name.

          $message = array($this->outputChannel, $chunk);

          $this->byteLengthOfNextChunk = 1;
          $this->mode = self::MODE_CHANNEL;
          $this->outputChannel = null;

          $messages[] = $message;
          break;
      }
    }

    // Return zero or more messages, which might look something like this:
    //
    //   array(
    //     array('o', '<...>'),
    //     array('o', '<...>'),
    //     array('r', '<...>'),
    //   );

    return $messages;
  }

}
