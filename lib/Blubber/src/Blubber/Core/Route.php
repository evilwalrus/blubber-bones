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

/**
 * Route class
 *
 * Holds specific information and methods pertaining to routing
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2015, Andrew Heebner
 * @license     MIT
 * @package     Blubber
 */
class Route
{

    protected $_finished        = false;
    protected $_path            = '';
    protected $_hash;
    protected $_validNS         = [];
    protected $_methodCallbacks = [];

    /**
     * Route constructor
     *
     * @param string $route
     */
    public function __construct($route)
    {
        $this->_path   = $route;
        $this->_hash   = Tools::shortHash($route);
    }

    /**
     * Retrieve the current route hash
     *
     * @return string
     */
    public function getHash()
    {
        return $this->_hash;
    }

    /**
     * Set the current route hash
     *
     * @param string $hash
     */
    public function setHash($hash = '')
    {
        $this->_hash = $hash;
    }

    /**
     * Retrieve the current route path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Generate a regular expression based on the route path
     *
     * @return string
     */
    public function getPathRegexp()
    {
        $regexp = [];
        $required = '!';
        $optional = '*';

        if ($this->_path != '/') {
            $parts = explode('/', $this->_path);

            foreach ($parts as $section) {
                if ($section[0] !== $required && $section[0] !== $optional) {
                    $regexp[] = $section;
                } else {
                    $regexp[] = '(/[^/]+)';

                    if ($section[0] === $optional) {
                        $regexp[] = '?';
                    }
                }
            }

            if (count($regexp) == 1) {
                $regexp[0] = '^' . $regexp[0] . '([\/\?].*)?$';
            }

            return '#' . join('', $regexp) . '#';
        }

        return '#^\/?$#';
    }

    /**
     * Retrieve the callback options for requested method
     *
     * @param  string $method
     * @return mixed Return an array if method was found, null if not
     */
    public function getMethodCallback($method)
    {
        return isset($this->_methodCallbacks[$method]) ? $this->_methodCallbacks[$method] : null;
    }

    /**
     * Get available methods for current route
     *
     * @return array
     */
    public function getAvailableMethods()
    {
        $keys = [];

        if (!empty($this->_methodCallbacks)) {
            $keys = array_keys($this->_methodCallbacks);
        }

        return $keys;
    }

    /**
     * Check the current namespace against the allowed namespaces for a route
     *
     * @param  \Blubber\Core\Request $request
     * @return bool
     */
    public function isValidNamespace($request)
    {
        if (count($this->_validNS) > 0) {
            return in_array($request->getNamespace(), $this->_validNS) ? true : false;
        } else {
            // No namespace specified, or an illegal one.  Set the route available to all
            // valid namespaces
            if (!in_array($request->getNamespace(), App::getValidNamespaces())) {
                return true;
            }

            // No namespace path set, deny action.
            return false;
        }
    }

    /**
     * Capture parameters from the request
     *
     * @param  App $request
     * @return array
     */
    public function getParams($request)
    {
        $obj = [];

        // remove the query string
        $requestPath = explode('?', $request->getRequestPath(), 2);

        $params  = explode('/', $this->_path);
        $request = explode('/', $requestPath[0]);

        foreach ($params as $k => $v) {
            if (substr($v, 0, 1) == '!') {
                $val = substr($v, 1);
                $obj[$val] = isset($request[$k]) ? $request[$k] : null;
            } elseif (substr($v, 0, 1) == '*' && isset($request[$k])) {
                $val = substr($v, 1);
                $obj[$val] = $request[$k];
            }
        }

        return $obj;
    }

    /**
     * Set method callback options
     *
     * @param  string   $method
     * @param  \Closure $callback
     * @return void
     */
    public function setMethodCallback($method, \Closure $callback)
    {
        $this->_methodCallbacks[$method] = [
            'method'   => $method,
            'callback' => $callback
        ];
    }

    public function setMethodRateLimit($method, $hook, $cost)
    {
        $this->_methodCallbacks[$method]['rateLimit']['hook'] = $hook;
        $this->_methodCallbacks[$method]['rateLimit']['cost'] = $cost;
    }

    public function getMethodRateLimit($method)
    {
        if (array_key_exists('rateLimit', $this->_methodCallbacks[$method])) {
            return $this->_methodCallbacks[$method]['rateLimit'];
        }

        return null;
    }

    public function setMethodAuth($method, $hook)
    {
        $this->_methodCallbacks[$method]['auth']['hook'] = $hook;
    }

    public function getMethodAuth($method)
    {
        if (array_key_exists('auth', $this->_methodCallbacks[$method])) {
            return $this->_methodCallbacks[$method]['auth'];
        }

        return null;
    }

    /**
     * Set valid namespaces for this route
     *
     * @param  array $namespaces
     * @return void
     */
    public function setNamespaces(array $namespaces = [])
    {
        $this->_validNS = $namespaces;
    }

    /**
     * Get namespaces for current route
     *
     * @return array
     */
    public function getNamespaces()
    {
        return $this->_validNS;
    }

    /**
     * Check to see if the requested path matches the current path
     *
     * @param  string $requestPath
     * @return bool
     */
    public function isValidPath($requestPath)
    {
        return preg_match($this->getPathRegexp(), $requestPath);
    }

    /**
     * Allows the route to be "finished"
     *
     * @param  bool $status
     * @return void
     */
    public function setFinished($status = true)
    {
        $this->_finished = $status;
    }

    /**
     * See if the route has ben finished by the client
     *
     * @return bool
     */
    public function isFinished()
    {
        return !!$this->_finished;
    }

}
