<?php

namespace Lyricphp;

class Client {

    const HTTP_REQUEST_TIMEOUT = -1;

    protected $charset = 'UTF-8';
    public $responses;
    protected $options = array();
    protected $headers = array();

    public function __construct() {
        $this->responses = new \stdClass();
    }

    protected function defaultOptions() {
        return array(
            'headers' => array(
                'User-Agent' => 'Drupal',
            ),
            'method' => 'GET',
            'data' => NULL,
            'max_redirects' => 3,
            'timeout' => 30.0,
            'context' => NULL,
        );
    }

    public function setOption($name, $value) {

        $this->options[$name] = $value;
    }

    public function setHeaders($name, $value) {

        $this->options['headers'][$name] = $value;
        $this->headers = $this->options['headers'];
    }

    public function setPostData($data) {

        $this->options['data'] = $this->urlEncode($data);
    }

    public function slowRequest($url, $method = 'GET', $params = array()) {


        // Parse the URL and make sure we can handle the schema.
        $uri = @parse_url($url);

        if ($uri == FALSE) {
            $this->responses->error = 'unable to parse URL';
            $this->responses->code = -1001;
            return $this->responses;
        }

        if (!isset($uri['scheme'])) {
            $this->responses->error = 'missing schema';
            $this->responses->code = -1002;
            return $this->responses;
        }

        $this->timer_start(__FUNCTION__);

        // Merge the default options.
        $this->options['method'] = $method;
        $this->options += $this->defaultOptions();

        // stream_socket_client() requires timeout to be a float.
        $this->options['timeout'] = (float) $this->options['timeout'];



        switch ($uri['scheme']) {
            case 'http':
            case 'feed':
                $port = isset($uri['port']) ? $uri['port'] : 80;
                $socket = 'tcp://' . $uri['host'] . ':' . $port;
                // RFC 2616: "non-standard ports MUST, default ports MAY be included".
                // We don't add the standard port to prevent from breaking rewrite rules
                // checking the host that do not take into account the port number.
                $this->options['headers']['Host'] = $uri['host'] . ($port != 80 ? ':' . $port : '');
                break;

            case 'https':
                // Note: Only works when PHP is compiled with OpenSSL support.
                $port = isset($uri['port']) ? $uri['port'] : 443;
                $socket = 'ssl://' . $uri['host'] . ':' . $port;
                $this->options['headers']['Host'] = $uri['host'] . ($port != 443 ? ':' . $port : '');
                break;

            default:
                $this->responses->error = 'invalid schema ' . $uri['scheme'];
                $this->responses->code = -1003;
                return $this->responses;
        }

        if (empty($this->options['context'])) {
            $fp = @stream_socket_client($socket, $errno, $errstr, $this->options['timeout']);
        } else {
            // Create a stream with context. Allows verification of a SSL certificate.
            $fp = @stream_socket_client($socket, $errno, $errstr, $this->options['timeout'], STREAM_CLIENT_CONNECT, $this->options['context']);
        }

        // Make sure the socket opened properly.
        if (!$fp) {
            // When a network error occurs, we use a negative number so it does not
            // clash with the HTTP status codes.
            $this->responses->code = -$errno;
            $this->responses->error = trim($errstr) ? trim($errstr) : t('Error opening socket @socket', array('@socket' => $socket));


            return $this->responses;
        }

        // Construct the path to act on.
        $path = isset($uri['path']) ? $uri['path'] : '/';
        if (isset($uri['query'])) {
            $path .= '?' . $uri['query'];
        }

        // Only add Content-Length if we actually have any content or if it is a POST
        // or PUT request. Some non-standard servers get confused by Content-Length in
        // at least HEAD/GET requests, and Squid always requires Content-Length in
        // POST/PUT requests.
        if (!empty($params) && $this->options['method'] == 'POST') {
            $this->setPostData($params);

            $content_length = strlen($this->options['data']);
        } else {
            $content_length = 0;
        }
        if ($content_length > 0 || $this->options['method'] == 'POST' || $this->options['method']
                == 'PUT') {
            $this->options['headers']['Content-Length'] = $content_length;
        }

        // If the server URL has a user then attempt to use basic authentication.
        if (isset($uri['user'])) {
            $this->options['headers']['Authorization'] = 'Basic ' . base64_encode($uri['user'] . (isset($uri['pass']) ? ':' . $uri['pass'] : ':'));
        }


        $request = $this->options['method'] . ' ' . $path . " HTTP/1.1\r\n";
        foreach ($this->options['headers'] as $name => $value) {
            $request .= $name . ': ' . trim($value) . "\r\n";
        }
        $request .= "\r\n" . $this->options['data'];
        $this->responses->request = $request;
        // Calculate how much time is left of the original timeout value.
        $timeout = $this->options['timeout'] - $this->timer_read(__FUNCTION__) / 1000;
        if ($timeout > 0) {
            stream_set_timeout($fp, floor($timeout), floor(1000000 * fmod($timeout, 1)));
            fwrite($fp, $request);
        }
        // Fetch response. Due to PHP bugs like http://bugs.php.net/bug.php?id=43782
        // and http://bugs.php.net/bug.php?id=46049 we can't rely on feof(), but
        // instead must invoke stream_get_meta_data() each iteration.
        $info = stream_get_meta_data($fp);
        $alive = !$info['eof'] && !$info['timed_out'];
        $response = '';

        while ($alive) {
            // Calculate how much time is left of the original timeout value.
            $timeout = $this->options['timeout'] - $this->timer_read(__FUNCTION__) / 1000;
            if ($timeout <= 0) {
                $info['timed_out'] = TRUE;
                break;
            }
            stream_set_timeout($fp, floor($timeout), floor(1000000 * fmod($timeout, 1)));
            $chunk = fread($fp, 1024);
            $response .= $chunk;
            $info = stream_get_meta_data($fp);
            $alive = !$info['eof'] && !$info['timed_out'] && $chunk;
        }
        fclose($fp);

        if ($info['timed_out']) {
            $this->responses->code = self::HTTP_REQUEST_TIMEOUT;
            $this->responses->error = 'request timed out';
            return $this->responses;
        }
        // Parse response headers from the response body.
        // Be tolerant of malformed HTTP responses that separate header and body with
        // \n\n or \r\r instead of \r\n\r\n.
        list($response, $this->responses->data) = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
        $response = preg_split("/\r\n|\n|\r/", $response);

        // Parse the response status line.
        $response_status_array = self::parseResponseStatus(trim(array_shift($response)));
        $this->responses->protocol = $response_status_array['http_version'];
        $this->responses->status_message = $response_status_array['reason_phrase'];
        $code = $response_status_array['response_code'];

        $this->responses->headers = array();

        // Parse the response headers.
        while ($line = trim(array_shift($response))) {
            list($name, $value) = explode(':', $line, 2);
            $name = strtolower($name);
            if (isset($this->responses->headers[$name]) && $name == 'set-cookie') {
                // RFC 2109: the Set-Cookie response header comprises the token Set-
                // Cookie:, followed by a comma-separated list of one or more cookies.
                $this->responses->headers[$name] .= ',' . trim($value);
            } else {
                $this->responses->headers[$name] = trim($value);
            }
        }

        $responses = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Time-out',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Large',
            415 => 'Unsupported Media Type',
            416 => 'Requested range not satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Time-out',
            505 => 'HTTP Version not supported',
        );
        // RFC 2616 states that all unknown HTTP codes must be treated the same as the
        // base code in their class.
        if (!isset($responses[$code])) {
            $code = floor($code / 100) * 100;
        }
        $this->responses->code = $code;

        switch ($code) {
            case 200: // OK
            case 201: // Created
            case 202: // Accepted
            case 203: // Non-Authoritative Information
            case 204: // No Content
            case 205: // Reset Content
            case 206: // Partial Content
            case 304: // Not modified
                break;
            case 301: // Moved permanently
            case 302: // Moved temporarily
            case 307: // Moved temporarily
                $location = $this->responses->headers['location'];
                $this->options['timeout'] -= $this->timer_read(__FUNCTION__) / 1000;
                if ($this->options['timeout'] <= 0) {
                    $this->responses->code = self::HTTP_REQUEST_TIMEOUT;
                    $this->responses->error = 'request timed out';
                } elseif ($this->options['max_redirects']) {
                    // Redirect to the new location.
                    $this->options['max_redirects'] --;
                    $this->Request($location, $this->options);
                    $this->responses->redirect_code = $code;
                }
                if (!isset($this->responses->redirect_url)) {
                    $this->responses->redirect_url = $location;
                }
                break;
            default:
                $this->responses->error = $this->responses->status_message;
        }

        return $this->responses;
    }

    public static function parseResponseStatus($response) {
        $response_array = explode(' ', trim($response), 3);
        // Set up empty values.
        $result = array(
            'reason_phrase' => '',
        );
        $result['http_version'] = $response_array[0];
        $result['response_code'] = $response_array[1];
        if (isset($response_array[2])) {
            $result['reason_phrase'] = $response_array[2];
        }
        return $result;
    }

    public function timer_start($name) {
        global $timers;

        $timers[$name]['start'] = microtime(TRUE);
        $timers[$name]['count'] = isset($timers[$name]['count']) ? ++$timers[$name]['count'] : 1;
    }

    public function timer_read($name) {
        global $timers;

        if (isset($timers[$name]['start'])) {
            $stop = microtime(TRUE);
            $diff = round(($stop - $timers[$name]['start']) * 1000, 2);

            if (isset($timers[$name]['time'])) {
                $diff += $timers[$name]['time'];
            }
            return $diff;
        }
        return $timers[$name]['time'];
    }

    protected function urlEncode($paras = array()) {


        $str = '';

        foreach ($paras as $k => $v) {

            $str .= "$k=" . urlencode($this->characet($v, $this->charset)) . "&";
        }
        return substr($str, 0, -1);
    }

    public function characet($data, $targetCharset = 'UTF-8') {


        if (!empty($data)) {

            if (strcasecmp($this->charset, $targetCharset) != 0) {

                $data = mb_convert_encoding($data, $targetCharset);
            }
        }


        return $data;
    }

    public function Request($url, $method = 'GET', $params = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $this->headers += array(
            'User-Agent' => 'Lyric Crawler',
        );

        foreach ($this->headers as $name => $value) {

            $headers[] = $name . ': ' . $value;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


        if ($method == "POST" && isset($params)) {
            curl_setopt($ch, CURLOPT_POST, true);

            $postBody = "";
            $multipart = NULL;
            $encodeParams = Array();

            foreach ($params as $k => $v) {
                if ("@" != substr($v, 0, 1)) {

                    $postBody .= "$k=" . urlencode($this->characet($v, $this->charset)) . "&";
                    $encodeParams[$k] = $this->characet($v, $this->charset);
                } else {
                    $multipart = true;
                    $encodeParams[$k] = new \CURLFile(substr($v, 1));
                }
            }

            if ($multipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encodeParams);
                $headers = array('content-type: multipart/form-data;charset=' . $this->charset);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, substr($postBody, 0, -1));
                $headers = array('content-type: application/x-www-form-urlencoded;charset=' . $this->charset);
            }
        }
        $res = curl_exec($ch);

        if (curl_errno($ch)) {

            throw new Exception(curl_error($ch), 0);
        } else {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->responses->code = $code;
            if (200 !== $code) {
                throw new Exception($res, $code);
            }
        }

        curl_close($ch);
        $this->responses->data = $res;
        return $this->responses;
    }

}
