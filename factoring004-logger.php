<?php

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class DebugLogger extends AbstractLogger {

    private $logger;

    public function __construct(?WC_Logger_Interface $logger = null)
    {
        $this->logger = $logger ?? wc_get_logger();
    }

    public function log($level, $message, array $context = array())
    {
        if ($level === LogLevel::DEBUG) {
            $this->logger->debug($message, ['source' => 'factoring004']);
        }
    }
}