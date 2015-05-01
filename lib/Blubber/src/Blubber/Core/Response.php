<?php
/**
 * Copyright (c)2015 Andrew Heebner
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Blubber\Core;

use Blubber\App;
use Blubber\Core\Transport\JSON;
use Blubber\Exceptions\HTTPException;

/**
 * Response class
 *
 * Used for sending standard HTTP responses back to the client
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2015, Andrew Heebner
 * @license     MIT
 * @package     Blubber
 */
class Response
{

    private $_content       = [];
    private $_headers       = [];
    private $_httpCode      = 200;
    private $_noBody        = ['head', 'options'];

    private $_validCodes    = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        449 => 'Retry With',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended'
    ];


    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Set additional headers for the response
     *
     * @param  array  $headers
     * @return Response
     */
    public function headers($headers = [])
    {
        $this->_headers = array_merge($this->_headers, $headers);

        return $this;
    }

    /**
     * Set the HTTP status code
     *
     * @param int $httpCode
     */
    public function status($httpCode)
    {
        $this->_httpCode = isset($this->_validCodes[$httpCode]) ? $httpCode : 200;
    }

    /**
     * Set the content for the response
     *
     * @param array $content
     */
    public function content(array $content = [])
    {
        $this->_content = $content;
    }

    /**
     * Get the added headers for the response
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * Get the message for this response
     *
     * @return string
     */
    public function getContent()
    {
        return $this->_content;
    }

    /**
     * Write status code and data to a buffer for an HTTP packet
     *
     * @param  int    $httpCode
     * @param  array  $content
     * @return Response
     */
    public function write($httpCode, array $content = [])
    {
        if (array_key_exists($httpCode, $this->_validCodes)) {
            $this->_httpCode = $httpCode;
            $this->_content  = $content;
        }

        return $this;
    }

    /**
     * Send header and content buffer to client
     *
     * @param  array $addedHeaders
     * @throws HTTPException
     * @return void
     */
    public function send($addedHeaders = [])
    {
        $content = self::getContent();
        $data = JSON::encode($content);

        $reqMethod = strtolower(Request::getRequestMethod());
        $headers = array_merge($this->_headers, $addedHeaders);

        $calcContentHeaders = function($data) {
            return [
                'Content-Length' => strlen($data)
            ];
        };

        // if the content has no body, switch the http code to a 204 No Content status
        $statusCode = (!in_array($reqMethod, $this->_noBody)) ? $this->_httpCode : 204;

        http_response_code($statusCode);

        $headers['Status']        = sprintf('%d %s', $statusCode, $this->_validCodes[$statusCode]);
        $headers['X-Powered-By']  = sprintf('%s/%s (%s)', \Blubber\PROJECT_NAME, \Blubber\PROJECT_VERSION, \Blubber\PROJECT_URL);

        if ($this->_validForSend($reqMethod) && !empty($content)) {
            $headers['Content-Language'] = I18n::getLang();
            $headers['Content-Type']     = JSON::getContentType();
            $headers += $calcContentHeaders($data);
        }

        $headers['X-Request-ID'] = Request::getRequestId();

        // get the execution time; useful for debugging
        if (array_key_exists('REQUEST_TIME_FLOAT', $_SERVER)) {
            $headers['X-Request-Time'] = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        }

        // security headers
        $headers['X-Frame-Options'] = 'sameorigin';
        $headers['X-XSS-Protection'] = '1; mode=block';
        $headers['X-Content-Type-Options'] = 'nosniff';

        if (Request::isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=15768000; includeSubDomains';
        }

        // process and output all of our response headers
        self::_processHeaders($headers);

        if ($this->_validForSend($reqMethod) && !empty($content)) {
            if (App::getOption('use_output_compression') &&
                extension_loaded('zlib') &&
                array_key_exists('gzip', Request::getAcceptEncoding()))
            {
                // clear output buffers
                while (ob_get_level()) ob_end_clean();

                ob_start();
                ob_start('ob_gzhandler');
                echo $data;

                ob_end_flush(); // clear inner
                self::_processHeaders($calcContentHeaders(ob_get_contents()));
                ob_end_flush(); // clear outer
                exit;
            }

            echo $data;
        }

        exit;
    }

    /**
     * Make sure we can send the proper information
     *
     * @param string $method
     * @return bool
     */
    private function _validForSend($method)
    {
        return (!in_array($method, $this->_noBody) || $this->_httpCode != 304);
    }

    /**
     * Process an array of headers to be sent
     *
     * @param  array $headers
     * @return void
     */
    private function _processHeaders(array $headers)
    {
        foreach ($headers as $k => $v) {
            header(sprintf('%s: %s', $k, $v), true);
        }
    }

}
