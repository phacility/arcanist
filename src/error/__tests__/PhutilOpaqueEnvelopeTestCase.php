<?php

final class PhutilOpaqueEnvelopeTestCase extends PhutilTestCase {

  public function testOpaqueEnvelope() {

    // NOTE: When run via "arc diff", this test's trace may include portions of
    // the diff itself, and thus this source code. Since we look for the secret
    // in traces later on, split it apart here so that invocation via
    // "arc diff" doesn't create a false test failure.
    $secret = 'hunter'.'2';

    // Also split apart this "signpost" value which we are not going to put in
    // an envelope. We expect to be able to find it in the argument lists in
    // stack traces, and don't want a false positive.
    $signpost = 'shaman'.'3';

    $envelope = new PhutilOpaqueEnvelope($secret);

    $this->assertFalse(strpos(var_export($envelope, true), $secret));

    $this->assertFalse(strpos(print_r($envelope, true), $secret));

    ob_start();
    var_dump($envelope);
    $dump = ob_get_clean();

    $this->assertFalse(strpos($dump, $secret));

    try {
      $this->throwTrace($envelope, $signpost);
    } catch (Exception $ex) {
      $trace = $ex->getTrace();

      // NOTE: The entire trace may be very large and contain complex
      // recursive datastructures. Look at only the last few frames: we expect
      // to see the signpost value but not the secret.
      $trace = array_slice($trace, 0, 2);
      $trace = print_r($trace, true);

      $this->assertTrue(strpos($trace, $signpost) !== false);
      $this->assertFalse(strpos($trace, $secret));
    }

    $backtrace = $this->getBacktrace($envelope, $signpost);
    $backtrace = array_slice($backtrace, 0, 2);

    $this->assertTrue(strpos($trace, $signpost) !== false);
    $this->assertFalse(strpos(print_r($backtrace, true), $secret));

    $this->assertEqual($secret, $envelope->openEnvelope());
  }

  private function throwTrace($v, $w) {
    throw new Exception('!');
  }

  private function getBacktrace($v, $w) {
    return debug_backtrace();
  }

}
