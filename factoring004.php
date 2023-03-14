<?php

defined('ABSPATH') || exit;

require_once 'vendor/autoload.php';

use BnplPartners\Factoring004\Api;
use BnplPartners\Factoring004\Auth\BearerTokenAuth;
use BnplPartners\Factoring004\ChangeStatus\CancelOrder;
use BnplPartners\Factoring004\ChangeStatus\CancelStatus;
use BnplPartners\Factoring004\ChangeStatus\DeliveryOrder;
use BnplPartners\Factoring004\ChangeStatus\DeliveryStatus;
use BnplPartners\Factoring004\ChangeStatus\MerchantsOrders;
use BnplPartners\Factoring004\ChangeStatus\ReturnOrder;
use BnplPartners\Factoring004\ChangeStatus\ReturnStatus;
use BnplPartners\Factoring004\Otp\CheckOtp;
use BnplPartners\Factoring004\Otp\CheckOtpReturn;
use BnplPartners\Factoring004\Otp\SendOtp;
use BnplPartners\Factoring004\Otp\SendOtpReturn;
use BnplPartners\Factoring004\PreApp\PreAppMessage;
use BnplPartners\Factoring004\Transport\GuzzleTransport;
use BnplPartners\Factoring004\Transport\TransportInterface;
use Psr\Log\NullLogger;
use BnplPartners\Factoring004\OAuth\OAuthTokenManager;

require_once 'factoring004-logger.php';
require_once 'factoring004-cache.php';

final class WC_Factoring004
{

    private $webhook_url = 'factoring004-post-link';

    private $base_url;

    private $api;

    private $debug_mode;

    private const AUTH_PATH = '/users/api/v1';

    private const CACHE_KEY = 'factoring004-cache';

    public function __construct($host, $login, $password, $debug)
    {
        if (is_null(CustomCache::get(self::CACHE_KEY)['token'])) {
            CustomCache::set(self::CACHE_KEY, [
                'token'=>(new OAuthTokenManager($host.self::AUTH_PATH, $login, $password))
                    ->getAccessToken()->getAccess()
            ]);
        }
        $this->debug_mode = $debug;
        $this->api = Api::create($host, new BearerTokenAuth(CustomCache::get(self::CACHE_KEY)['token']), $this->createTransport());
        $this->base_url = get_site_url();
    }

    public function preApp($partnerData, $order)
    {
        $message = PreAppMessage::createFromArray([
            'partnerData' => [
                'partnerName' => (string) $partnerData['partnerName'],
                'partnerCode' => (string) $partnerData['partnerCode'],
                'pointCode' => (string) $partnerData['pointCode'],
                'partnerEmail' => (string) $partnerData['partnerEmail'],
                'partnerWebsite' => (string) $partnerData['partnerWebsite'],
            ],
            'billNumber' => (string) $order->get_id(),
            'billAmount' => (int) ceil($order->get_total()),
            'itemsQuantity' => (int) array_sum(array_map(function ($item) {
                return $item->get_quantity();
            }, $order->get_items())),
            'items' => array_values(array_map(function ($item) {
                return [
                    'itemId' => (string) $item->get_product_id(),
                    'itemName' => (string) $item->get_name(),
                    'itemCategory' => implode(',', array_map(function ($cat) use ($item) {
                        return $cat->name;
                    }, get_the_terms($item->get_product_id(), 'product_cat'))),
                    'itemQuantity' => (int) $item->get_quantity(),
                    'itemPrice' => $item->get_quantity() > 1
                        ? (int) ceil($item->get_subtotal() / $item->get_quantity())
                        : (int) ceil($item->get_subtotal()),
                    'itemSum' => (int) ceil($item->get_total()),
                ];
            }, $order->get_items())),
            'successRedirect' => $this->base_url,
            'failRedirect' => $this->base_url,
            'postLink' => $this->base_url . '/wc-api/' . $this->webhook_url,
            'phoneNumber' => preg_replace('/^8|\+7/', '7', $order->get_billing_phone()),
            'deliveryPoint' => [
                'region' => (string) $order->get_shipping_country(),
                'district' => (string) $order->get_shipping_state(),
                'city' => (string) $order->get_shipping_city(),
                'street' => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2()
            ]
        ]);

        return $this->api->preApps->preApp($message)->getRedirectLink();
    }

    public function delivery($order_id, $partner_code, $otp_code, $orderAmount)
    {
        if (!empty($otp_code)) {
            return $this->checkOtpDelivery($order_id, $partner_code, $otp_code, $orderAmount);
        }

        try {
            $response = $this->api->changeStatus->changeStatusJson([
                new MerchantsOrders(
                    (string) $partner_code,
                    [new DeliveryOrder((string) $order_id, DeliveryStatus::DELIVERY(), $orderAmount)]
                )
            ]);

            if ($response->getErrorResponses()) {
                file_put_contents(
                    __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                    date('H-i-s') . ':' . PHP_EOL . json_encode($response) . PHP_EOL, FILE_APPEND
                );
                return false;
            }

            return true;
        } catch (Exception $e) {
            file_put_contents(
                __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                date('H-i-s') . ':' . PHP_EOL . $e . PHP_EOL, FILE_APPEND
            );
            throw $e;
        }
    }

    public function return($order, $amount, $partner_code)
    {
        try {
            $response = $this->api->changeStatus->changeStatusJson([
                new MerchantsOrders(
                    $partner_code,
                    [
                        new ReturnOrder(
                                $order->get_id(),
                                $amount > 0 ? ReturnStatus::PARTRETURN() : ReturnStatus::RETURN(),
                                $this->getAmountRemaining($amount, $order->get_total() - $order->get_total_refunded())
                            ),
                    ],
                ),
            ]);

            if ($response->getErrorResponses()) {
                file_put_contents(
                    __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                    date('H-i-s') . ':' . PHP_EOL . json_encode($response) . PHP_EOL, FILE_APPEND
                );
                return false;
            }

            return true;
        } catch (Exception $e) {
            file_put_contents(
                __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                date('H-i-s') . ':' . PHP_EOL . $e . PHP_EOL, FILE_APPEND
            );
            throw $e;
        }
    }

    public function cancel($order, $partner_code)
    {
        try {
            $response = $this->api->changeStatus->changeStatusJson([
                new MerchantsOrders(
                    (string) $partner_code,
                    [new CancelOrder((string) $order->get_id(), CancelStatus::CANCEL())]
                )
            ]);

            if ($response->getErrorResponses()) {
                file_put_contents(
                    __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                    date('H-i-s') . ':' . PHP_EOL . json_encode($response) . PHP_EOL, FILE_APPEND
                );
                return false;
            }

            return true;
        } catch (Exception $e) {
            file_put_contents(
                __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                date('H-i-s') . ':' . PHP_EOL . $e . PHP_EOL, FILE_APPEND
            );
            throw $e;
        }
    }

    public function sendOtpDelivery($partner_code, $order)
    {
        try {
            $sendOtp = new SendOtp((string) $partner_code, (string) $order->get_id(), (int) $order->get_total());

            $response = $this->api->otp->sendOtp($sendOtp);

            if ($response->isError()) {
                file_put_contents(
                    __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                    date('H-i-s') . ':' . PHP_EOL . json_encode($response) . PHP_EOL, FILE_APPEND
                );
                return false;
            }

            return true;
        } catch (Exception $e) {
            file_put_contents(
                __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                date('H-i-s') . ':' . PHP_EOL . $e . PHP_EOL, FILE_APPEND
            );
            throw $e;
        }
    }

    public function sendOtpReturn($amount, $partner_code, $order)
    {
        try {

            $sendOtpReturn = new SendOtpReturn(
                $this->getAmountRemaining($amount, ceil($order->get_total() - $order->get_total_refunded())),
                (string) $partner_code,
                (string) $order->get_id()
            );

            $response = $this->api->otp->sendOtpReturn($sendOtpReturn);

            if ($response->isError()) {
                file_put_contents(
                    __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                    date('H-i-s') . ':' . PHP_EOL . json_encode($response) . PHP_EOL, FILE_APPEND
                );
                return false;
            }

            return true;
        } catch (Exception $e) {
            file_put_contents(
                __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                date('H-i-s') . ':' . PHP_EOL . $e . PHP_EOL, FILE_APPEND
            );
            throw $e;
        }
    }

    public function checkOtpReturn($amount, $partner_code, $order, $otp_code)
    {
        try {
            $checkOtpReturn = new CheckOtpReturn(
                $this->getAmountRemaining($amount, ceil($order->get_total() - $order->get_total_refunded())),
                (string) $partner_code,
                (string) $order->get_id(),
                $otp_code
            );

            $response = $this->api->otp->checkOtpReturn($checkOtpReturn);

            if ($response->isError()) {
                file_put_contents(
                    __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                    date('H-i-s') . ':' . PHP_EOL . json_encode($response) . PHP_EOL, FILE_APPEND
                );
                return false;
            }

            return true;
        } catch (Exception $e) {
            file_put_contents(
                __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                date('H-i-s') . ':' . PHP_EOL . $e . PHP_EOL, FILE_APPEND
            );
            throw $e;
        }
    }

    private function checkOtpDelivery($order_id, $partner_code, $otp_code, $orderAmount)
    {
        try {
            $checkOtp = new CheckOtp((string)$partner_code, (string)$order_id, (string)$otp_code, (int) $orderAmount);

            $response = $this->api->otp->checkOtp($checkOtp);

            if ($response->isError()) {
                file_put_contents(
                    __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                    date('H-i-s') . ':' . PHP_EOL . json_encode($response) . PHP_EOL, FILE_APPEND
                );
                return false;
            }

            return true;
        } catch (Exception $e) {
            file_put_contents(
                __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                date('H-i-s') . ':' . PHP_EOL . $e . PHP_EOL, FILE_APPEND
            );
            throw $e;
        }
    }

    private function getAmountRemaining($amount, $total)
    {
        return ($amount > 0 && $total > $amount)
            ? (int) $total - $amount
            : 0;
    }

    private function createTransport(): TransportInterface
    {
        $transport = new GuzzleTransport();
        $transport->setLogger($this->debug_mode ? new DebugLogger() : new NullLogger());

        return $transport;
    }
}
