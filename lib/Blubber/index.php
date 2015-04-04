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

require_once 'autoloader.php';

use Blubber\Exceptions\HTTPException;

class TestApi extends \Blubber\App
{

    private $options = array(
        'use_output_compression'  => true, // set to 'true' to use builtin gzip; otherwise, use apache's gzip
        'require_user_agent'      => false,
        'redirect_old_namespaces' => true,
        'require_https'           => true,
    );


    public function __construct($namespaces = [])
    {
        parent::__construct($namespaces, $this->options);

        // set our own custom content type (must be a json type string, ending in 'json')
        //$this->setContentType('application/vnd.blubber+json');

        // setup a couple of required headers
        //$this->setRequiredHeaders([
        //    'X-Public-Key', 'X-Content-Hash'
        //]);

        $this->setCallbacks();
        $this->setRoutes();
        $this->testParamRoutes();
    }

    public function setCallbacks()
    {
        // internal callback, used to allow the end-user to compare against the User-Agent)
        //  If this event handler is missing, and the require_user_agent option is  true,
        //  then all requests will be processed whether their is a valid User-Agent or not
        $this->on('__USER_AGENT__', function($user_agent) {
            //
            // This callback must explicitly return true or false
            //
            return true;
        });

        // internal callback, used to allow end-clients to capture HTTP and internal errors
        //  Blubber will still output the proper HTTP response... this callback is meant
        //  for users to log and debug
        $this->on('__ERROR__', function($request_id, $resource, $code, $message) {
            // $request_id -> ID of the request (for tracking purposes perhaps?)
            // $resource   -> the absolute URI of the request
            // $code       -> almost always the responding HTTP (error) code
            // $message    -> internally set by Blubber to show a description of the error
        });

        // dummy callback, just shows how to add return headers to a response
        $this->on('rate.limit', function($remoteAddr) {
            // .. do something with the remoteAddr to get the rateLimit

            //
            // Naturally, these figures would all be dynamic, and not static.
            //
            return [
                'X-RateLimit-Limit'     => 5000,
                'X-RateLimit-Remaining' => 4999,
                'X-RateLimit-Reset'     => strtotime('+30 minutes')
            ];
        });

        $this->on('auth.basic.verify', function() {
            $auth = $this->getAuthorization();

            if (!is_null($auth)) {
                if (strtolower($auth['auth_scheme']) == 'basic') {
                    $creds = $this->getBasicAuthCredentials($auth['auth_data']);

                    if ($creds['username'] == 'andrew' && $creds['password'] == 'foo') {
                        return true;
                    }
                }
            }

            // Blubber\t('') is a shortcut function for I18n::get($string)

            throw new HTTPException(Blubber\t('auth.failed'), 401);
        });

        $this->on('auth.hmac.verify', function() {
            //
            // Client must send these headers
            //
            $hash_header = $this->getHeader('X-Content-Hash');  // hashed content using method below
            $public_key  = $this->getHeader('X-Public-Key');    // Assigned to user via API or website

            // Closure for denying user on multiple error codes and messages
            $denyUser = function($message, $http_code) {
                throw new HTTPException($message, $http_code);
            };

            // Make sure the headers were set; if not, deny.  Throw an HTTPException with a 401 Unauthorized
            if (empty($hash_header) || empty($public_key)) {
                $denyUser('X-Content-Hash and X-Public-Key headers must be sent with request', 401);
            }

            // Use your method to lookup the users private key using their public key
            // $private_key = \SomeLibrary::getPrivateKey($public_key);
            $private_key = 'ac3a7cfd3b2d73c74e3b7798df03e2b7d829514ea4cf624c08a9b722decbcddd';

            if ($private_key !== null) {
                // setup our HMAC object
                $hmac = new \Blubber\Core\HMAC($public_key, $private_key);

                //
                // Calculate the content hash; this must be the same method the client used to hash the content.
                // Whole request URI (including query string and starting slash) concat with public key.
                //
                //  api endpoint is -> https://localhost
                //  request uri is  -> /v3/hmac_test
                //
                $hmac->setContent($this->getRequestUri() . $public_key);
                $content_hash = $hmac->getSignature();

                if ($hmac->hashEquals($hash_header)) {
                    //
                    // Normally we'd send 'true' as a response to show that the user is verified, but
                    // we'll send back some data just to verify the data is being sent through properly
                    //
                    return [
                        'hmac' => [
                            'public_key' => $public_key,
                            'content_hash' => $content_hash,
                            'timestamp' => time()
                        ],
                        'request_headers' => $this->getHeaders(),
                        'request_from' => $this->getRealRemoteAddr(),
                        'request_to' => $this->getLocalAddr(),
                        'ssl_status' => $this->isSecure() ? 'on' : 'off'
                    ];
                } else {
                    // 403 code because we did authenticate the user, but not the data
                    $denyUser('Hash-matching failed.  Are you sure you hashed properly?', 403);
                }
            } else {
                // 401 code because we failed to do a proper user lookup
                $denyUser('Private key lookup failed.  Perhaps a wrong ID or automated attack?', 401);
            }

            // this should never be reached; we should either return a valid result, or throw an exception
            return false;

        });

    }

    public function setRoutes()
    {

        //
        // This must be nothing more than a backslash.  If you add variables, you overwrite all the below routes
        //
        $this->route('/', function() {
            $this->get(function ($request, $response, $params) {

                $response->write(200, ['message' => 'Welcome Home']);

                return $response;
            });
        });

        // send the request headers back to the user
        $this->route('/headers/*withNamespace', function() {
            $this->get(function ($request, $response, $params) {
                $rateHeaders = $this->dispatch('rate.limit', [$request->getRemoteAddr()]);

                if (isset($params['withNamespace'])) {
                    $rateHeaders['X-Namespace'] = $request->getNamespace();
                }

                $response->write(200, $rateHeaders);  // send the request headers back as a filler
                $response->headers($rateHeaders);

                return $response;
            });
        });

        $this->route('/server_vars', function () {
            $this->get(function ($request, $response, $params) {
                $response->write(200, $_SERVER);

                return $response;
            });
        });

        $this->route('/langs', function() {
            $this->get(function($request, $response, $params) {
                $response->write(200, $request->getAcceptLanguage());

                return $response;
            });
        });

        $this->route('/hmac_test', function() {

            $this->get(function($request, $response, $params) {
                $hmac = $this->dispatch('auth.hmac.verify');

                $response->write(200, $hmac);
                return $response;
            });

            $this->post(function($request, $response, $params) {

                //
                // This dispatchter throws an HTTPException if certain conditions aren't met.  Otherwise,
                // it will always return valid data and doesn't really need to be checked against.
                //
                $hmac = $this->dispatch('auth.hmac.verify');

                $response->write(201, $hmac);
                return $response;
            });

        });

        $this->route('/basic_auth', function() {
            $this->post(function($request, $response, $params) {
                //
                // auth.basic.verify throws an HTTPException on bad credentials, so
                // there's no need to verify the information here.  If the credentials
                // succeed, then we can move on with our code.
                //
                $this->dispatch('auth.basic.verify');

                $response->write(200, $request->getHeaders());

                return $response;
            });
        })->name('basic_auth_using_callback');  // route is now internally named

        $this->route('/post_test', function() {
            $this->post(function($request, $response, $params) {
                $response->write(200, $request->getHeaders());

                return $response;
            });
        });

        $this->route('/paths', function() {
            $this->get(function($request, $response, $params) {

                $data = [
                    'request_uri' => $request->getRequestUri(),
                    'request_path' => $request->getRequestPath(),
                    'real_request_path' => $request->getRealRequestPath(),
                    'query_string' => $request->getQueryString(true)
                ];

                $response->write(200, $data);

                return $response;
            });
        });

    }

    // testing hierarchy
    public function testParamRoutes()
    {
        // first param is required, second is optional
        //  - second will only be set if it exists
        $this->route('/users/!user_name/*user_action', function() {
            $this->get(function($request, $response, $params) {
                $response->write(200, $params);
                return $response;
            });
        })->namespaces(['v1']);  // only available on the /v1 namespace (if it's not deprecated)

    }
}

$api = new TestApi(['v1', 'v2', 'v3']);
$api->deprecateNamespaces(['v1', 'v2']);
$api->process();

?>