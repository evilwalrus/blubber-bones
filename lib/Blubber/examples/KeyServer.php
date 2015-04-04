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

class KeyServer extends \Blubber\App
{

    private $options = array(
        'use_output_compression'  => true,
        'require_user_agent'      => false,
        'redirect_old_namespaces' => true,
        'require_https'           => true,
    );


    public function __construct($namespaces = [])
    {
        parent::__construct($namespaces, $this->options);

        $this->setCallbacks();
        $this->setRoutes();
    }

    private function setCallbacks()
    {
        $this->on('generate.key', function($params) {
            if (!empty($params['user_algo']) && in_array($params['user_algo'], hash_algos())) {
                $hash_str = uniqid() . time() . (!empty($params['user_salt']) ? $params['user_salt'] : '');

                return hash($params['user_algo'], $hash_str . $this->getRequestId()); // add a little more entropy
            } else {
                throw new \Blubber\Exceptions\HTTPException('Supplied algorithm (' . $params['user_algo'] . ') is not valid', 400);
            }
        });
    }

    private function setRoutes()
    {

        $this->route('/', function() {
            $this->get(function ($request, $response, $params) {
                $response->write(200, ['usage' => '/v1/generate/{hash_algo}/{{hash_salt}}']);

                return $response;
            });
        });

        // /v1/generate/sha256/somerandomsalt
        $this->route('/generate/:user_algo/?:user_salt', function() {
            $this->get(function($request, $response, $params) {
                $keygen = $this->dispatch('generate.key', [$params]);

                $response->write(200, ['keygen' => $keygen]);

                return $response;
            });
        });

    }

}

$app = new KeyServer(['v1']);
$app->process();

?>