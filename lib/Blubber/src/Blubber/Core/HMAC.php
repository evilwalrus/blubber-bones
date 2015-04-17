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


/**
 * HMAC Class
 *
 * Used for generating and comparing HMAC hashes
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2015, Andrew Heebner
 * @license     MIT
 * @package     Blubber
 */
class HMAC
{

    private $public_key  = '';
    private $private_key = '';
    private $content     = '';


    /**
     * @param $public_key
     * @param $private_key
     */
    public function __construct($public_key, $private_key)
    {
        self::setPublicKey($public_key);
        self::setPrivateKey($private_key);
    }

    /**
     * @param $public_key
     */
    public function setPublicKey($public_key)
    {
        $this->public_key = $public_key;
    }

    /**
     * @param $private_key
     */
    public function setPrivateKey($private_key)
    {
        $this->private_key = $private_key;
    }

    /**
     * @param $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        return hash_hmac('sha256', $this->content, $this->private_key);
    }

    /**
     * @param $compared_hash
     *
     * @return bool
     */
    public function hashEquals($compared_hash)
    {
        if (!function_exists('hash_equals')) { // hash_equals is only available in 5.6.0+
            return (self::getSignature() === $compared_hash);
        } else {
            return hash_equals(self::getSignature(), $compared_hash);
        }
    }

}