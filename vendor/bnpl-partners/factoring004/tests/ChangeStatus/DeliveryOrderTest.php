<?php

declare(strict_types=1);

namespace BnplPartners\Factoring004\ChangeStatus;

use PHPUnit\Framework\TestCase;

class DeliveryOrderTest extends TestCase
{
    public function testCreateFromArray(): void
    {
        $expected = new DeliveryOrder('1', DeliveryStatus::DELIVERY());
        $actual = DeliveryOrder::createFromArray([
            'orderId' => '1',
            'status' => DeliveryStatus::DELIVERY()->getValue(),
        ]);

        $this->assertEquals($expected, $actual);
    }

    public function testGetStatus(): void
    {
        $order = new DeliveryOrder('1', DeliveryStatus::DELIVERY());
        $this->assertEquals(DeliveryStatus::DELIVERY(), $order->getStatus());
    }

    public function testGetOrderId(): void
    {
        $order = new DeliveryOrder('1', DeliveryStatus::DELIVERY());
        $this->assertEquals('1', $order->getOrderId());

        $order = new DeliveryOrder('100', DeliveryStatus::DELIVERY());
        $this->assertEquals('100', $order->getOrderId());
    }

    public function testToArray(): void
    {
        $order = new DeliveryOrder('1', DeliveryStatus::DELIVERY());
        $expected = [
            'orderId' => '1',
            'status' => DeliveryStatus::DELIVERY()->getValue(),
        ];

        $this->assertEquals($expected, $order->toArray());
    }
}

