<?php

namespace App\Services\Portal;

/**
 * A refusal the contract defines by name, so gateways and adapters can react
 * to a specific failure instead of parsing prose (Spec 9.5).
 */
class PortalContractException extends \RuntimeException
{
    /**
     * A contract-defined refusal code, distinct from Exception::$code (which is
     * an integer and is not ours to set).
     */
    public string $reason;

    public function __construct(
        string $reason,
        string $message,
        public readonly int $status = 400,
    ) {
        parent::__construct($message);
        $this->reason = $reason;
    }
}
