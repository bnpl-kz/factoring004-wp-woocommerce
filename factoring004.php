<?php

defined( 'ABSPATH' ) || exit;

require_once 'vendor/autoload.php';

use BnplPartners\Factoring004\Api;
use BnplPartners\Factoring004\Auth\BearerTokenAuth;
use BnplPartners\Factoring004\ChangeStatus\MerchantsOrders;
use BnplPartners\Factoring004\ChangeStatus\ReturnOrder;
use BnplPartners\Factoring004\ChangeStatus\ReturnStatus;
use BnplPartners\Factoring004\Exception\PackageException;
use BnplPartners\Factoring004\Otp\CheckOtpReturn;
use BnplPartners\Factoring004\Otp\SendOtpReturn;
use BnplPartners\Factoring004\PreApp\PreAppMessage;

final class WC_Factoring004
{

    private $webhook_url = 'factoring004-payment-gateway';

    private $base_url;

    private Api $api;

    public function __construct($host, $token)
    {
        $this->api = Api::create($host, new BearerTokenAuth($token));
        $this->base_url = get_site_url();
    }

    public function preApp($partnerData, $order)
    {
        try {
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
                        'itemCategory' => implode(',',array_map(function ($cat) use ($item) {
                            return $cat->name;
                        }, get_the_terms($item->get_product_id(),'product_cat'))),
                        'itemQuantity' => (int) $item->get_quantity(),
                        'itemPrice' => $item->get_quantity() > 1 ? (int) ceil($item->get_subtotal() / $item->get_quantity()) : (int) ceil($item->get_subtotal()),
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

        } catch (PackageException $e) {
            file_put_contents(__DIR__ .'/logs/'. date('Y-m-d').'.log',date('H-i-s').':'.PHP_EOL . $e . PHP_EOL, FILE_APPEND);
            wc_add_notice('An error occurred!', 'error');
        }

    }

    public function delivery($order)
    {
        print_r($order);die;
    }

    public function return($order, $amount, $partner_code)
    {

        try {
            $response = $this->api->changeStatus->changeStatusJson([
                new MerchantsOrders(
                    $partner_code,
                    [
                        new ReturnOrder
                        (
                            $order->get_id(),
                            $amount > 0 ? ReturnStatus::PARTRETURN() : ReturnStatus::RETURN(),
                            $this->getAmountRemaining($amount, $order->get_total())
                        ),
                    ],
                ),
            ]);

            if ($response->getErrorResponses()) {
                file_put_contents(__DIR__ .'/logs/'. date('Y-m-d').'.log',date('H-i-s').':'.PHP_EOL . json_encode($response) . PHP_EOL, FILE_APPEND);
                return false;
            }

            return true;

        } catch (Exception $e) {
            file_put_contents(__DIR__ .'/logs/'. date('Y-m-d').'.log',date('H-i-s').':'.PHP_EOL . $e . PHP_EOL, FILE_APPEND);
            throw $e;
        }

    }

    public function sendOtpReturn($amount, $partner_code, $order)
    {

        try {

            $sendOtpReturn = new SendOtpReturn(
                    $this->getAmountRemaining($amount, ceil($order->get_total())),
                    (string) $partner_code,
                    (string) $order->get_id()
                );

            $response = $this->api->otp->sendOtpReturn($sendOtpReturn);

            if ($response->isError()) {
                file_put_contents(__DIR__ .'/logs/'. date('Y-m-d').'.log',date('H-i-s').':'.PHP_EOL . json_encode($response) . PHP_EOL, FILE_APPEND);
                return false;
            }

            return true;

        } catch (Exception $e) {
            file_put_contents(__DIR__ .'/logs/'. date('Y-m-d').'.log',date('H-i-s').':'.PHP_EOL . $e . PHP_EOL, FILE_APPEND);
            throw $e;
        }
    }

    public function checkOtpReturn($amount, $partner_code, $order, $otp_code)
    {
        try {
            $checkOtpReturn = new CheckOtpReturn(
                $this->getAmountRemaining($amount, ceil($order->get_total())),
                (string) $partner_code,
                (string) $order->get_id(),
                $otp_code
            );

            $response = $this->api->otp->checkOtpReturn($checkOtpReturn);

            if ($response->isError()) {
                file_put_contents(__DIR__ .'/logs/'. date('Y-m-d').'.log',date('H-i-s').':'.PHP_EOL . json_encode($response) . PHP_EOL, FILE_APPEND);
                return false;
            }

            return true;

        } catch (Exception $e) {
            file_put_contents(__DIR__ .'/logs/'. date('Y-m-d').'.log',date('H-i-s').':'.PHP_EOL . $e . PHP_EOL, FILE_APPEND);
            throw $e;
        }

    }

    private function getAmountRemaining($amount, $total)
    {
        return ($amount > 0 && $total > $amount)
            ? (int) $total - $amount
            : 0;
    }
}