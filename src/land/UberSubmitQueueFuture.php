<?php

final class UberSubmitQueueFuture extends FutureProxy {
	protected function didReceiveResult($result) {
		list($status, $body, $headers) = $result;
		if ($status->isError()) {
			throw $status;
		}

		$raw = $body;
		$shield = 'for(;;);';
    if (!strncmp($raw, $shield, strlen($shield))) {
      $raw = substr($raw, strlen($shield));
    }

    $data = null;
    try {
      $data = phutil_json_decode($raw);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht(
          'Host returned HTTP/200, but invalid JSON data in response to '.
          'a SubmitQueue method call.'),
        $ex);
    }
    return $data['url'];
	}
}
