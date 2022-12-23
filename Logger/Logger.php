<?php

namespace Openpay\Cards\Logger;

/**
 * Openpay custom logger allows name changing to differentiate log call origin
 * Class Logger
 *
 * @package Openpay\CardPayments\Logger
 */
class Logger extends \Monolog\Logger
{

    /**
     * Set logger name
     * @param $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }
}
