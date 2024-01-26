<?php

/**
 * Plugin Name: Рассрочка 0-0-4
 * Plugin URI:
 * Description: Купи сейчас, плати потом! Быстрое и удобное оформление рассрочки на 4 месяца без первоначальной оплаты для жителей Казахстана. Моментальное подтверждение, без комиссий и процентов. Для заказов суммой от 6000 до 200000 тг.
 * Author: Team BNPL
 * Author URI:
 * Version: 1.0.0
 * Text Domain: factoring004
 * Domain Path: /languages/
 */

defined('ABSPATH') || exit;

session_start();

/**
 * Хук действия регистрирует класс PHP как платежный шлюз WooCommerce.
 */
add_filter('woocommerce_payment_gateways', 'factoring004_add_gateway_class');

function factoring004_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Factoring004_Gateway';
    return $gateways;
}

/**
 * Этот хук действия регистрирует функцию для работы с условиями отображения платежа
 */

add_filter('woocommerce_available_payment_gateways', 'disable_factoring004_above_6000_or_below_200000');

function disable_factoring004_above_6000_or_below_200000($available_gateways)
{
    if (is_admin()) {
        return $available_gateways;
    }

    $minSum = 6000;
    $maxSum = 200000;
    if (WC()->cart->total < $minSum || WC()->cart->total > $maxSum) {
        unset($available_gateways['factoring004']);
    }
    return $available_gateways;
}

add_action('plugins_loaded', 'factoring004_init_gateway_class');

function factoring004_init_gateway_class() {

    class WC_Factoring004_Gateway extends WC_Payment_Gateway
    {

        const REQUIRED_FIELDS = ['billNumber', 'status', 'preappId', 'signature'];

        const ZONE_NAME = 'Казахстан';

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {
            $this->id = 'factoring004'; // id плагина платежного шлюза
//            $this->icon = apply_filters('woocommerce_gateway_icon', plugin_dir_url('factoring004-gateway/assets/images/factoring004.svg').'factoring004.svg'); // URL значка, который будет отображаться на странице оформления заказа рядом с именем вашего шлюза
            $this->has_fields = false; // если вам нужна индивидуальная форма кредитной карты
            $this->method_title = 'Рассрочка 0-0-4'; // заголовок
            $this->method_description = 'Купи сейчас, плати потом! Быстрое и удобное оформление рассрочки на 4 месяца без первоначальной оплаты. Моментальное подтверждение, без комиссий и процентов. Для заказов суммой от 6000 до 200000 тг.'; // описание

            $this->supports = array(
                'products',
            );

            // Метод со всеми полями параметров
            $this->init_form_fields();

            // Загрузите настройки
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');

            require_once 'factoring004.php';

            // Хук действия сохраняет настройки
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Регистрация вебхука
            add_action('woocommerce_api_factoring004-post-link', array($this, 'webhook'));

            // Регистрация js на странице checkout
            if ($this->get_option('client_route') === 'modal') {
                add_action('wp_footer', array($this, 'factoring004_add_jscript_checkout'), 9999);
            }
        }

        public function factoring004_add_jscript_checkout()
        {
            if ($this->get_option('client_route') === 'modal' && $this->enabled === 'yes') {
                $domain = stripos($this->get_option('api_host'), 'dev') ? 'dev.bnpl.kz' : 'bnpl.kz';
                ?>
                <script defer src="https://<?php echo $domain?>/widget/index_bundle.js"></script><div id="modal-factoring004"></div>
                <script>
                    jQuery(function($) {
                        $(document).ajaxComplete(function (event, XMLHttpRequest, ajaxOptions) {
                            if (XMLHttpRequest.responseJSON.result == "success" && XMLHttpRequest.responseJSON.redirectLink != null) {

                                const bnplKzApi = new BnplKzApi.CPO({
                                    rootId: "modal-factoring004",
                                    callbacks: {
                                        onError: () => window.location.replace(XMLHttpRequest.responseJSON.redirectLink),
                                        onDeclined: () => window.location.replace("/"),
                                        onEnd: () => window.location.replace("/"),
                                    }
                                });
                                bnplKzApi.render({
                                    redirectLink: XMLHttpRequest.responseJSON.redirectLink
                                });
                            }
                        })
                    })
                </script>
                <?php
            }
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => __('Enable/Disable', 'woocommerce'),
                    'label'       => 'Включить Рассрочка 0-0-4',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => __('Title', 'woocommerce'),
                    'type'        => 'text',
                    'description' => 'Это управляет заголовком, который пользователь видит во время оформления заказа.',
                    'default'     => 'Рассрочка 0-0-4',
                    'desc_tip'    => true
                ),
                'description' => array(
                    'title'       => __('Description', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => 'Это управляет описанием, которое пользователь видит во время оформления заказа.',
                    'default'     => 'Купи сейчас, плати потом! Быстрое и удобное оформление рассрочки на 4 месяца без первоначальной оплаты. Моментальное подтверждение, без комиссий и процентов. Для заказов суммой от 6000 до 200000 тг.',
                    'desc_tip'    => true
                ),
                'api_host' => array(
                    'title'       => 'API Host',
                    'type'        => 'text'
                ),
                'login' => array(
                    'title'       => 'Login',
                    'type'        => 'text',
                ),
                'password' => array(
                    'title'       => 'Password',
                    'type'        => 'text'
                ),
                'partner_name' => array(
                    'title'       => 'Partner Name',
                    'type'        => 'text'
                ),
                'partner_code' => array(
                    'title'       => 'Partner Code',
                    'type'        => 'text'
                ),
                'point_code' => array(
                    'title'       => 'Point Code',
                    'type'        => 'text'
                ),
                'client_route' => array(
                    'type'        => 'factoring004_client_route',
                ),
                'debug_mode' => array(
                    'title'       => 'Включить/Выключить режим отладки',
                    'label'       => ' ',
                    'type'        => 'checkbox',
                )
            );
        }

        /**
         * Создаем кастомное поле для выбора интерфейсного пути
         */
        public function generate_factoring004_client_route_html()
        {
            ob_start();
            ?>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="woocommerce_factoring004_client_route">Вид интерфейса клиентского пути</label>
                    </th>
                    <td class="forminp">
                        <fieldset>
                            <label for="woocommerce_factoring004_client_route">
                                <select name="woocommerce_factoring004_client_route">
                                    <option <?php if ($this->get_option('client_route') === 'redirect') echo 'selected'; ?> value="redirect">Редирект</option>
                                    <option <?php if ($this->get_option('client_route') === 'modal') echo 'selected'; ?> value="modal">Модальное окно</option>
                                </select>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            <?php
            return ob_get_clean();
        }

        /**
         * Обработка платежа
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            try {
                $factoring004 = new WC_Factoring004(
                    $this->get_option('api_host'),
                    $this->get_option('login'),
                    $this->get_option('password'),
                    $this->get_option('debug_mode') === 'yes'
                );

                $redirectLink = $factoring004->preApp(
                    [
                        'partnerName' => $this->get_option('partner_name'),
                        'partnerCode' => $this->get_option('partner_code'),
                        'pointCode' => $this->get_option('point_code'),
                        'partnerEmail' => $this->get_option('partner_email'),
                        'partnerWebsite' => $this->get_option('partner_website'),
                    ],
                    $order
                );

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_option('client_route') === 'modal' ? false : $redirectLink,
                    'redirectLink' => $redirectLink
                );

            } catch (Exception $e) {
                file_put_contents(
                        __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                        date('H-i-s') . ':' . PHP_EOL . $e . PHP_EOL, FILE_APPEND
                );
                wc_add_notice(
                        'Технические доработки. Улучшаем сервис для Вас. Попробуйте оформить покупку позднее.',
                        'error'
                );
                return;
            }
        }

        /**
         * Обработка webhook (если он нужен)
         */
        public function webhook()
        {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                wp_send_json(['success'=>false, 'error' => 'Method not allowed'],405);
            }

            $request = json_decode(file_get_contents('php://input'), true);

            foreach (static::REQUIRED_FIELDS as $field) {
                if (empty($request[$field]) || !is_string($field)) {
                    wp_send_json(['success' => false, 'error' => $field . ' is invalid'], 400);
                }
            }

            $validator = new \BnplPartners\Factoring004\Signature\PostLinkSignatureValidator(
                    $this->get_option('partner_code')
            );

            try {
                $validator->validateData($request);
            } catch (\BnplPartners\Factoring004\Exception\InvalidSignatureException $e) {
                file_put_contents(
                        __DIR__ . '/logs/' . date('Y-m-d') . '.log',
                        date('H-i-s') . ':' . PHP_EOL . $e . PHP_EOL, FILE_APPEND
                );
                wp_send_json(['success'=>false, 'error' => 'Invalid signature'],400);
            }

            $order = wc_get_order($request['billNumber']);

            if (!$order) {
                wp_send_json(['success'=>false, 'error' => 'Order not found'],400);
            }

            if ($request['status'] === 'preapproved') {
                wp_send_json(['response' => 'preapproved'],200);
            } elseif ($request['status'] === 'declined') {
                $order->update_status('failed');
                wp_send_json(['response' => 'declined'],200);
            } elseif ($request['status'] === 'completed') {
                $order->update_status('processing');
                wp_send_json(['response' => 'ok'],200);
            } else {
                wp_send_json(['success' => false, 'error' => 'Unexpected status'], 400);
            }
        }
    }
}