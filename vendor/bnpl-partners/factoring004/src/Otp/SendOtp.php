<?php

declare(strict_types=1);

namespace BnplPartners\Factoring004\Otp;

use BnplPartners\Factoring004\ArrayInterface;

/**
 * @psalm-immutable
 */
class SendOtp implements ArrayInterface
{
    /**
     * @var string
     */
    private $merchantId;
    /**
     * @var string
     */
    private $merchantOrderId;

    public function __construct(string $merchantId, string $merchantOrderId)
    {
        $this->merchantId = $merchantId;
        $this->merchantOrderId = $merchantOrderId;
    }

    /**
     * @param array<string, mixed> $sendOtp
     *
     * @psalm-param array{merchantId: string, merchantOrderId: string} $sendOtp
     */
    public static function createFromArray($sendOtp): SendOtp
    {
        return new self($sendOtp['merchantId'], $sendOtp['merchantOrderId']);
    }

    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    public function getMerchantOrderId(): string
    {
        return $this->merchantOrderId;
    }

    /**
     * @psalm-return array{merchantId: string, merchantOrderId: string}
     */
    public function toArray(): array
    {
        return [
            'merchantId' => $this->getMerchantId(),
            'merchantOrderId' => $this->getMerchantOrderId(),
        ];
    }
}
