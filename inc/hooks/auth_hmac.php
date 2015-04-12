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

$app->on('auth.hmac', function() use ($app) {
    //
    // Client must send these headers
    //
    $hash_header = $app->getHeader('X-Content-Hash');  // hashed content using method below
    $public_key  = $app->getHeader('X-Public-Key');    // Assigned to user via API or website

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
        //  getRequestLocation() -> https://localhost/v3/hmac_test
        //
        $hmac->setContent($app->getRequestLocation() . $public_key);

        if ($hmac->hashEquals($hash_header)) {
            return true;
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