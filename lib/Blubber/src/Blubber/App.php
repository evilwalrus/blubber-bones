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

namespace Blubber;

const DATE_RFC1123 = 'D, d M Y H:i:s \G\M\T';

const PROJECT_NAME = 'Blubber';
const PROJECT_URL = 'https://github.com/evilwalrus/Blubber';
const PROJECT_VERSION = '1.0.0-rc.1';

use Blubber\Core\I18n;
use Blubber\Core\Request;
use Blubber\Core\Response;
use Blubber\Core\Route;
use Blubber\Core\Tools;
use Blubber\Exceptions\HTTPException;

//
// Shortcut function for translations
//
function t($key) {
    return I18n::get($key);
}


/**
 * App class
 *
 * Main Blubber\App class.  Used for creating and running of API frameworks.
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2015, Andrew Heebner
 * @license     MIT
 * @package     Blubber
 */
class App extends Request
{

    protected $_routes           = [];
    protected $_currRoute        = null;
    protected $_currMethod       = null;
    private   $_methods          = ['get', 'head', 'options', 'post', 'patch', 'put', 'delete'];
    protected static $_options   = [];
    protected $_content          = null;
    private   $_requiredHeaders  = [];

    private $_authenticated = false;

    protected static $_eventHandlers    = [];
    protected static $_methodCallbacks  = [];

    /**
     * App constructor
     *
     * @param array $namespaces
     * @param array $options
     */
    public function __construct(array $namespaces = [], array $options = [])
    {
        date_default_timezone_set('GMT');

        // parse out the user's options
        self::_setOptions($options);

        // set the request headers
        self::_setRequestHeaders();

        static::$_validNamespaces = $namespaces;
        static::$_requestPath     = self::normalizePath($_SERVER['REQUEST_URI']);
        static::$_requestId       = Tools::generateUUID();

        //
        // Get I18n ready for strings
        //
        I18n::init();

        self::_handleEvents();

        // set the content after everything is all setup
        try {
            self::setContent(@file_get_contents('php://input'));
        } catch (HTTPException $e) {
            self::dispatch('error', [$e]);
        }
    }

    /**
     * Magic method.
     *
     * @param  string $method
     * @param  array  $args
     * @return App|false
     */
    public function __call($method, $args)
    {
        if (!empty($this->_currRoute)) {
            if (in_array($method, $this->_methods)) {
                $this->_currMethod = $method;
                self::getCurrentRoute()->setMethodCallback($method, $args[0]->bindTo($this, $this));

                return $this;
            }
        }

        self::dispatch('error', [new HTTPException(sprintf(t('invalid.method'), $method, __CLASS__), 500)]);
        return false; // unreachable; put here just to clear IDE errors
    }

    /**
     * Namespace list which to deprecate from future use
     *
     * @param array $namespaces
     */
    public function deprecateNamespaces(array $namespaces = []) {
        static::$_oldNamespaces = $namespaces;
    }

    /**
     * Set default options for APP class
     *
     * @param  array $options
     * @return void
     */
    private function _setOptions(array $options = [])
    {
        //
        // Define default class options here
        //
        self::$_options = [
            'use_output_compression'  => false,
            'require_user_agent'      => false,
            'redirect_old_namespaces' => true,
            'require_https'           => true,
            'force_user_language'     => 'en',
        ];

        foreach ($options as $option => $value) {
            if (array_key_exists($option, self::$_options)) {
                self::$_options[$option] = $value;
            }
        }
    }

    /**
     * Get the content from the current request (parent override)
     *
     * @return mixed|null
     */
    public function getContent()
    {
        $data = null;

        try {
            $data = parent::getContent();
        } catch (HTTPException $e) {
            self::dispatch('error', [$e]);
        }

        return $data;
    }

    /**
     * Set a single option (or an array of options) in the App
     *
     * @param  mixed $option
     * @param  mixed $value
     * @return void
     */
    public static function setOption($option, $value = null)
    {
        if (is_array($option)) {
            foreach ($option as $k => $v) {
                self::$_options[$k] = $v;
            }
        } else {
            self::$_options[$option] = $value;
        }

    }

    /**
     * Get a user or default option value
     *
     * @param  string $option
     * @return null
     */
    public static function getOption($option)
    {
        if (array_key_exists($option, self::$_options)) {
            return self::$_options[$option];
        }

        return null;
    }

    /**
     * Set required headers for Request
     *
     * @param  array $headers
     * @return void
     */

    public function setRequiredHeaders(array $headers = [])
    {
        $this->_requiredHeaders = $headers;
    }

    /**
     * Get all required headers for Request
     *
     * @return array
     */
    public function getRequiredHeaders()
    {
        return $this->_requiredHeaders;
    }

    /**
     * Create a new routing pattern
     *
     * @param  string   $route
     * @param  \Closure $callback
     * @return App
     */
    public function route($route, \Closure $callback)
    {
        $_route = new Route(self::normalizePath($route));

        $this->_currRoute = $_route;

        // random hash until we rename it
        $this->_routes[$_route->getHash()] = $_route;

        // activate the callback
        call_user_func_array($callback->bindTo($this, $this), []);

        return $this;
    }

    /**
     * Name the current route (internally)
     *
     * @param string $route_name
     */
    public function name($route_name)
    {
        $this->getCurrentRoute()->setHash($route_name);
    }

    /**
     * Set valid namespaces for current route
     *
     * @param  array $namespaces
     * @return App
     */
    public function namespaces(array $namespaces = [])
    {
        self::getCurrentRoute()->setNamespaces($namespaces);

        return $this;
    }

    /**
     * Set rate-limiting options for RateLimiter (per method)
     *
     * @param string $hookName
     * @param int $cost
     * @return App
     */
    public function rateLimit($hookName = '__RATE_LIMIT__', $cost = 1)
    {
        self::getCurrentRoute()->setMethodRateLimit($this->_currMethod, $hookName, $cost);

        return $this;
    }

    /**
     * Set auth hook per method
     */
    public function auth($hook)
    {
        self::getCurrentRoute()->setMethodAuth($this->_currMethod, $hook);

        return $this;
    }

    /**
     * Finish off all routes
     *
     * @return void
     */
    private function _finishRoutes()
    {
        foreach ($this->_routes as $_route) {
            if (!empty(static::$_validNamespaces)) {
                if (empty($_namespaces)) {
                    $_route->setNamespaces(static::$_validNamespaces);
                }
            }

            $_route->setFinished();
        }
    }

    /**
     * Set a handler for a specified named event
     *
     * @param  string   $event
     * @param  \Closure $callback
     * @return void
     */
    public function on($event, \Closure $callback)
    {
        self::$_eventHandlers[$event] = $callback;
    }

    /**
     * See if we have a named event handler in stock
     *
     * @param  string $event
     * @return bool
     */
    public function hasEventHandler($event)
    {
        return !!array_key_exists($event, self::$_eventHandlers);
    }

    /**
     * Dispatch a named event with specified args
     *
     * @param  string $event
     * @param  array  $args
     * @return mixed
     */
    public function dispatch($event, array $args = [])
    {
        $response = null;

        if (self::hasEventHandler($event)) {
            $callback = self::$_eventHandlers[$event];

            try {
                $response = call_user_func_array($callback->bindTo($this, $this), $args);
            } catch (HTTPException $ex) {
                // re-dispatch our Exception as an error
                self::dispatch('error', [$ex]);
            }

            if ($response instanceof Response) {
                $headers = ($args[0] instanceof HTTPException) ? $args[0]->getHeaders() : [];

                try {
                    $response->send($headers);
                } catch (HTTPException $e) {
                    self::dispatch('error', [$e]);
                }
            }
        }
        return $response;
    }

    /**
     * Return if the user passed authentication or not
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        return !!$this->_authenticated;
    }

    /**
     * Returns the hook that we authenticated with
     *
     * @return string
     */
    public function authenticatedWith()
    {
        return $this->_authenticated;
    }

    /**
     * Check if we're running under SSL (if option enabled)
     *
     * @return void
     */
    protected function _checkRequireSSL()
    {
        if (self::getOption('require_https')) {
            if (!self::isSecure()) {
                self::dispatch('error', [new HTTPException(t('require.https'), 400)]);
            }
        }
    }

    /**
     * Check for required headers (if option enabled)
     *
     * @param  array $search
     * @param  array $in
     * @return void
     */
    protected function _checkRequiredHeaders($search, $in)
    {
        if (!empty($search)) {
            $errArr = [];

            foreach ($search as $key) {
                if (!array_key_exists($key, $in)) {
                    $errArr[] = $key;
                }
            }

            if (!empty($errArr)) {
                $errMsg = t('missing.required.headers') . ': ' . join(', ', $errArr);
                self::dispatch('error', [new HTTPException($errMsg, 400)]);
            }
        }
    }

    /**
     * Check for a valid User-Agent (if option enabled)
     *
     * @return void
     */
    protected function _checkUserAgent()
    {
        //
        // Check for a valid user-agent (if required) before we process any callbacks
        //
        if (self::getOption('require_user_agent')) {
            if (self::hasEventHandler('__USER_AGENT__')) {
                if (null !== ($_ua = self::getUserAgent())) {
                    $_uaCheck = self::dispatch('__USER_AGENT__', [$_ua]);

                    if ($_uaCheck === false) {
                        self::dispatch('error', [new HTTPException(t('invalid.user.agent'), 400)]);
                    }
                } else {
                    self::dispatch('error', [new HTTPException(t('invalid.user.agent'), 400)]);
                }
            }
            // allow all requests if they didn't setup the event handler, regardless of the option setting
        }
    }

    /**
     * Runs a callback for a specified HTTP method
     *
     * @param  Route  $route
     * @param  string $method
     * @param  array $params
     * @return void
     */
    protected function runMethodCallback(Route $route, $method, $params)
    {
        $allow_header = ['Allow' => strtoupper(join(', ', $route->getAvailableMethods()))];

        $callback = $route->getMethodCallback($method);

        if (!is_null($callback) && is_array($callback)) {

            $closure = $callback['callback'];

            $auth_hook = $route->getMethodAuth($method);
            $rate_hook = $route->getMethodRateLimit($method);

            try {
                // dispatch our auth event (if any)
                if (!is_null($auth_hook)) {
                    if (is_array($auth_hook['hook'])) {
                        foreach ($auth_hook['hook'] as $hook) {
                            if (self::hasEventHandler($hook) && (self::dispatch($hook) !== false)) {
                                $this->_authenticated = $hook;
                                break; // leave the loop since we already authenticated
                            }
                        }
                    } else {
                        if (self::dispatch($auth_hook['hook']) !== false) {
                            $this->_authenticated = $hook;
                        }
                    }
                }

                // dispatch the rate-limiting (if any)
                if (!is_null($rate_hook) && self::hasEventHandler($rate_hook['hook'])) {
                    $rateHeaders = self::dispatch($rate_hook['hook'], [$rate_hook['cost']]);
                }

                // dispatch the main closure (will only execute if the above are successful)
                $response = call_user_func_array($closure->bindTo($this, $this), [$this, new Response(), $params]);
            } catch (HTTPException $ex) {
                self::dispatch('error', [$ex]);
            }

            if ($response instanceof Response) {

                // add the rate-limiting headers
                $response->headers($rateHeaders);

                //
                // As per HTTP spec, Options needs a list of methods set to the Allow header
                //
                if ($callback['method'] == 'options') {
                    $headers = array_merge($allow_header, $response->getHeaders());
                } else {
                    $headers = $response->getHeaders();
                }

                // Add a deprecation warning header if we are using a deprecated namespace
                $oldNamespaces = self::getDeprecatedNamespaces();

                if (!empty($oldNamespaces) && in_array(self::getNamespace(), $oldNamespaces)) {

                    // these headers will be added to all requests with a deprecated namespace
                    $headers['X-Blubber-Warn']    = t('deprecated.namespace');
                    $headers['X-Blubber-Upgrade'] = self::getActiveNamespace();

                    if (self::getOption('redirect_old_namespaces')) {
                        $headers['Location'] = '/' . self::getActiveNamespace() . '/' . self::getRequestPath();

                        // change the response to a 301 (w/ no data) and forward the user
                        $response->write(301, [])->send($headers);
                    }
                }

                try {
                    $response->send($headers);
                } catch (HTTPException $e) {
                    self::dispatch('error', [$e]);
                }
            } else {
                self::dispatch('error', [new HTTPException(t('missing.response.data'), 500)]);
            }
        } else {
            self::dispatch('error', [new HTTPException(t('method.not.allowed'), 405, $allow_header)]);
        }
    }

    /**
     * Process all callbacks, handlers, etc.
     *
     * @return void
     */
    public function process()
    {
        self::_checkRequireSSL();
        self::_checkRequiredHeaders(self::getRequiredHeaders(), self::getHeaders());
        self::_checkUserAgent();

        self::_finishRoutes();

        if (!empty($this->_routes)) {
            // get a matching route
            $route = self::getValidRoute();

            if ($route instanceof Route) {
                // Does the route have a valid namespace?
                if (!$route->isValidNamespace($this)) {
                    self::dispatch('error', [new HTTPException(t('invalid.namespace'), 403)]);
                }

                $method   = strtolower(self::getRequestMethod());
                $params   = $route->getParams($this);

                self::runMethodCallback($route, $method, $params);
            } else {
                self::dispatch('error', [new HTTPException(t('route.not.found'), 404)]);
            }
        } else {
            // APi creator didn't define any routes.  Don't expose, just 404.
            self::dispatch('error', [new HTTPException(t('route.not.found'), 404)]);
        }
    }

    /**
     * Get a valid route matching the incoming Request route
     *
     * @return Route|bool Returns a Route object on success, false on error
     */
    protected function getValidRoute()
    {
        foreach ($this->_routes as $route) {
            if ($route->isValidPath(self::getRequestPath())) {
                return $route;
            }
        }

        return false;
    }

    /**
     * Returns Route object that is currently in use
     *
     * @return object
     */
    protected function getCurrentRoute()
    {
        return $this->_routes[$this->_currRoute->getHash()];
    }

    /**
     * Setup internal event handlers
     *
     * @return void
     */
    protected function _handleEvents()
    {
        //
        // Handle internal error events
        //
        $this->on('error', function(HTTPException $exception) {

            $err_response = [
                'request_id' => self::getRequestId(),
                'resource'   => self::getRequestUri(),
                'code'       => $exception->getCode(),
                'message'    => $exception->getMessage()
            ];

            if (self::hasEventHandler('__ERROR__')) {
                self::dispatch('__ERROR__', $err_response);
            }

            $response = new Response();
            $response->write($exception->getCode(), $err_response);

            return $response;
        });

    }

}
