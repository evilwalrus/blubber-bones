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

use Blubber\Core\Transport\JSON;
use Blubber\Exceptions\HTTPException;

/**
 * Request class
 *
 * Abstract class supplying numerous methods for abstracting the HTTP request
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2015, Andrew Heebner
 * @license     MIT
 * @package     Blubber
 */
abstract class Request
{

    protected static $_validNamespaces = [];
    protected static $_oldNamespaces   = [];
    protected static $_requestPath     = null;
    protected static $_requestHeaders  = [];
    protected static $_data            = null;
    protected static $_requestId       = null;

    /**
     * Return the current request ID
     *
     * @return string
     */
    public static function getRequestId()
    {
        return static::$_requestId;
    }

    /**
     * Return the current request method (GET, POST, ...)
     *
     * @return string
     */
    public static function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Return the requested HTTP version
     *
     * @return float
     */
    public static function getHTTPVersion()
    {
        return (float)substr($_SERVER['SERVER_PROTOCOL'], -3);
    }

    /**
     * Is this request secure?
     *
     * @return bool
     */
    public static function isSecure()
    {
        return (isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'on');
    }

    /**
     * Get the requester's IP address
     *
     * @return null|float
     */
    public static function getRemoteAddr()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Get the requester's IP address (cycle through proxies)
     * @return null|float
     */
    public static function getRealRemoteAddr()
    {
        $remoteAddr = self::getRemoteAddr();

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $remoteAddr = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $remoteAddr = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $remoteAddr;
    }

    /**
     * Get the local IP of the operating machine
     *
     * @return string
     */
    public static function getLocalAddr()
    {
        return gethostbyname(gethostname());
    }

    /**
     * Get the accepted content-types from the client
     *
     * @return array|null
     */
    public static function getAccept()
    {
        return isset($_SERVER['HTTP_ACCEPT']) ?
            self::_parseHeaderValue($_SERVER['HTTP_ACCEPT']) : null;
    }

    /**
     * Get the accepted charsets from the client
     *
     * @return array|null
     */
    public static function getAcceptCharset()
    {
        return isset($_SERVER['HTTP_ACCEPT_CHARSET']) ?
            self::_parseHeaderValue($_SERVER['HTTP_ACCEPT_CHARSET']) : null;
    }

    /**
     * Get the accepted languages from the client
     *
     * @return array|null
     */
    public static function getAcceptLanguage()
    {
        return isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ?
            self::_parseHeaderValue($_SERVER['HTTP_ACCEPT_LANGUAGE']) : null;
    }

    /**
     * Get the accepted encodings from the client
     *
     * @return array|null
     */
    public static function getAcceptEncoding()
    {
        return isset($_SERVER['HTTP_ACCEPT_ENCODING']) ?
            self::_parseHeaderValue($_SERVER['HTTP_ACCEPT_ENCODING']) : null;
    }

    /**
     * Get the requester's user agent
     *
     * @return null|string
     */
    public static function getUserAgent()
    {
        return (self::hasUserAgent()) ? $_SERVER['HTTP_USER_AGENT'] : null;
    }

    /**
     * Check if the request has a user agent
     *
     * @return bool
     */
    public static function hasUserAgent()
    {
        return (isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT']));
    }

    /**
     * Return all request headers
     *
     * @return array
     */
    public static function getHeaders()
    {
        return static::$_requestHeaders;
    }

    /**
     * Return a specified request header
     *
     * @param  string $header
     * @return null|string
     */
    public static function getHeader($header)
    {
        return isset(static::$_requestHeaders[$header]) ? static::$_requestHeaders[$header] : null;
    }

    /**
     * Returns the complete URL to the request from start-to-finish
     *
     * @param bool $with_uri Include URI with location
     * @return string|null
     */
    public static function getRequestLocation($with_uri = true)
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $parse = parse_url(
                (isset($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'off') ? 'https://' : 'http://') .
                (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '')) . (($with_uri) ? $_SERVER['REQUEST_URI'] : null)
            );
            $parse['port'] = $_SERVER["SERVER_PORT"]; // Setup protocol for sure (80 is default)

            return http_build_url('', $parse);
        }

        return null;
    }

    /**
     * Gets the absolute request URI of the incoming request
     *
     * return string
     */
    public static function getRequestUri()
    {
        return $_SERVER['REQUEST_URI'];
    }

    /**
     * Return the current *real* request path (sans namespace)
     *
     * @return null|string
     */
    public static function getRequestPath()
    {
        if (!empty(static::$_validNamespaces)) {
            $_parts = explode('/', static::$_requestPath);
            if (in_array($_parts[0], static::$_validNamespaces)) {
                array_shift($_parts);
                return join('/', $_parts);
            }
        }

        return static::$_requestPath;
    }

    /**
     * Return the real request path, as is came in via the request
     *
     * @return mixed
     */
    public static function getRealRequestPath()
    {
        list($path,) = explode('?', $_SERVER['REQUEST_URI'], 2);
        return $path;
    }

    /**
     * Return the query string, as it was appended to the real request above
     *
     * @param bool $parseArray
     * @return string|array
     */
    public function getQueryString($parseArray = false)
    {
        if (strpos($_SERVER['REQUEST_URI'], '?') !== false) {
            list(, $query_string) = explode('?', $_SERVER['REQUEST_URI'], 2);

            if ($parseArray) {
                $_qs = [];
                parse_str($query_string, $_qs);
                $query_string = $_qs;
            }

            return $query_string;
        }

        return '';
    }

    /**
     * Get the current requested namespace
     *
     * @return null
     */
    public static function getNamespace()
    {
        if (!empty(static::$_validNamespaces)) {
            $_parts = explode('/', static::$_requestPath);
            if (in_array($_parts[0], static::$_validNamespaces)) {
                return $_parts[0];
            }
        }

        return null;
    }

    /**
     * Get all valid namespaces
     *
     * @return array
     */
    public static function getValidNamespaces()
    {
        return static::$_validNamespaces;
    }

    /**
     * Get all deprecated namespaces
     *
     * @return array
     */
    public static function getDeprecatedNamespaces()
    {
        return static::$_oldNamespaces;
    }

    /**
     * Get the currently active namespace (last in list)
     *
     * @return string
     */
    public static function getActiveNamespace()
    {
        return static::$_validNamespaces[count(static::$_validNamespaces) - 1];
    }

    /**
     * See if we have namespaces
     *
     * @return boolean
     */
    public static function hasNamespaces()
    {
        return !!empty(static::$_validNamespaces);
    }

    /**
     * Set the current request content
     *
     * @param  $data
     * @return void
     * @throws HTTPException
     */
    public function setContent($data)
    {
        static::$_data = $data;
    }

    /**
     * Return the current request content
     *
     * @return mixed
     * @throws HTTPException
     */
    public function getContent()
    {
        return JSON::decode(urldecode(static::$_data));
    }

    /**
     * Get raw incoming data (non-parsed & non-decoded)
     *
     * @return mixed
     */
    public function getContentRaw()
    {
        return static::$_data;
    }

    /**
     * @return array|null
     */
    public function getAuthorization()
    {
        /**
         * TODO: Make this compatible with OAuth1 and other schemes
         *
         * We need to split the comma-delimited components, which are ultimately URL-encoded
         * and also separated by =:
         *
         * Authorization: OAuth realm="http://api.example.com/",
         * oauth_consumer_key="0685bd9184jfhq22",
         * oauth_token="ad180jjd733klru7",
         * oauth_signature_method="HMAC-SHA1",
         * oauth_signature="wOJIO9A2W5mFwDgiDvZbTSMK%2FPY%3D",
         * oauth_timestamp="137131200",
         * oauth_nonce="4572616e48616d6d65724c61686176",
         * oauth_version="1.0"
         *
         * Use rawurldecode and rawurlencode
         */
        $auth = static::getHeader('Authorization');

        if (!empty($auth)) {
            list($type, $data) = explode(' ', $auth, 2);
            return ['auth_scheme' => $type, 'auth_data' => $data];
        }

        return null;
    }

    /**
     * @param $auth_data
     *
     * @return array
     */
    public function getBasicAuthCredentials($auth_data)
    {
        $data = base64_decode($auth_data);
        list($user, $pass) = explode(':', $data, 2);
        return ['username' => $user, 'password' => $pass];
    }

    /**
     * Allow API creator to set their own custom JSON content-type
     *    ex: application/vnd.blubber+json (must end in "json")
     *
     * @param  string $contentType
     * @return void
     */
    public static function setContentType($contentType)
    {
        if (strtolower(substr($contentType, -4)) == 'json') {
            $ctype = $contentType;
        } else {
            $ctype = JSON::getContentType();
        }

        JSON::setContentType($ctype);
    }

    /**
     * Normalize a route/request path
     *
     * @param  $path
     * @return string
     */
    public static function normalizePath($path)
    {
        $path = trim($path, '/');
        return empty($path) ? '/' : $path;
    }

    /**
     * Set the current request headers
     *
     * @return void
     */
    protected static function _setRequestHeaders()
    {
        static::$_requestHeaders = apache_request_headers();
    }

    /**
     * Parse a header line into an array based on preference
     *
     * @param  $value
     * @return array
     */
    private static function _parseHeaderValue($value)
    {
        $out = [];
        $data = explode(',', str_replace(' ', '', $value));

        foreach ($data as $val) {

            $_val = $val;
            $_qval = 1.0;

            if (false !== strstr($val, ';q=')) {
                list($_val, $tmp) = explode(';', $val);
                $q = explode('=', $tmp);
                $_qval = (float)$q[1];
            }

            $out[urldecode($_val)] = $_qval;
        }

        arsort($out);
        return $out;
    }

}
