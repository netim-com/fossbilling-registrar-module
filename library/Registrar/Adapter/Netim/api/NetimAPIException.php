<?php

namespace Netim
{
    // Generic class for a client API Exception. Used to standardize API Exceptions so the CMS code does not depend of the underlying architecture (SOAP, REST ...)
	class NetimAPIException extends \Exception
	{
        public function __construct($message, $code = 0, \Exception $previous = null) {
            parent::__construct($message, $code, $previous);
        }

        public function __toString() {
            return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
        }
    }
}