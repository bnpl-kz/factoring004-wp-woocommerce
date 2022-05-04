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
 * Хук для создания таблиц в бд, срабатывает в момент активации плагина
 */
register_activation_hook(__FILE__, 'create_table_factoring004_payment_gateway');

function create_table_factoring004_payment_gateway()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'factoring004_order_preapps';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                     `id` INT NOT NULL AUTO_INCREMENT, 
                     `order_id` INT NOT NULL,
                     `preapp_uid` VARCHAR(255) NOT NULL, 
                     `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                     PRIMARY KEY (`id`),
                     CONSTRAINT unique_$table_name UNIQUE (order_id, preapp_uid)
                     ) ENGINE = InnoDB;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Хук для удаления таблиц в бд, срабатывает в момент деактивации плагина
 */
register_deactivation_hook(__FILE__, 'drop_table_factoring004_payment_gateway');

function drop_table_factoring004_payment_gateway()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'factoring004_order_preapps';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

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
 * хук вывода кнопки в деталях заказа для обработки доставки
 */
add_action('woocommerce_order_item_add_action_buttons', 'action_woocommerce_order_item_add_action_buttons', 10, 1);

function action_woocommerce_order_item_add_action_buttons($order)
{
    $screen    = get_current_screen();
    $screen_id = $screen ? $screen->id : '';

    if ($screen_id !== 'shop_order') {
        return;
    }

    $payment_method = $order->get_payment_method();
    $order_current_status = $order->get_status();

    if ($payment_method === 'factoring004' && $order_current_status === 'processing') {
        echo '<button class="button generate-items do-api-delivery" type="button">Доставка (Рассрочка 0-0-4)</button>';
        echo '<button class="button generate-items do-api-cancel" type="button">Отмена (Рассрочка 0-0-4)</button>';
    }

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


/**
 * Хук регистрации обработчика отмены
 */

add_action('wp_ajax_factoring004_cancel', 'factoring004_cancel_callback');

function factoring004_cancel_callback()
{
    if (!wp_verify_nonce($_POST['_nonce'])) {
        wp_die(0,400);
    }

    $data = $_POST['data'];

    call_user_func(array(new WC_Factoring004_Gateway,'process_cancel'),$data);
}

/**
 * Хук регистрации обработчика вовзрата отправки смс
 */

add_action('wp_ajax_factoring004_send_otp_return', 'factoring004_send_otp_return_callback');

function factoring004_send_otp_return_callback()
{
    if (!wp_verify_nonce($_POST['_nonce'])) {
        wp_die(0,400);
    }

    $data = $_POST['data'];

    call_user_func(array(new WC_Factoring004_Gateway,'send_otp_return'),$data);
}

/**
 * Хук регистрации обработчика вовзрата проверки смс кода
 */

add_action('wp_ajax_factoring004_check_otp_return', 'factoring004_check_otp_return_callback');

function factoring004_check_otp_return_callback()
{
    if (!wp_verify_nonce($_POST['_nonce'])) {
        wp_die(0,400);
    }

    $data = $_POST['data'];

    call_user_func(array(new WC_Factoring004_Gateway,'check_otp_return'),$data);
}

/**
 * Хук регистрации обработчика доставки отправки смс
 */

add_action('wp_ajax_factoring004_send_otp_delivery', 'factoring004_send_otp_delivery_callback');

function factoring004_send_otp_delivery_callback()
{
    if (!wp_verify_nonce($_POST['_nonce'])) {
        wp_die(0,400);
    }

    $data = $_POST['data'];

    call_user_func(array(new WC_Factoring004_Gateway,'send_otp_delivery'),$data);
}

/**
 * Хук регистрации обработчика доставки
 */

add_action('wp_ajax_factoring004_delivery', 'factoring004_with_or_without_otp_delivery_callback');

function factoring004_with_or_without_otp_delivery_callback()
{
    if (!wp_verify_nonce($_POST['_nonce'])) {
        wp_die(0,400);
    }

    $data = $_POST['data'];

    call_user_func(array(new WC_Factoring004_Gateway,'process_delivery'),$data);
}

/**
 * Хук регистрации обработчика удаления файла
 */

add_action('wp_ajax_factoring004_agreement_destroy', 'factoring004_agreement_destroy_callback');

function factoring004_agreement_destroy_callback()
{
    if (!wp_verify_nonce($_POST['_nonce'])) {
        wp_die(0,400);
    }

    $filename = $_POST['filename'];

    call_user_func(array(new WC_Factoring004_Gateway,'destroyAgreementFile'),$filename);
}

/**
 * Хук для добавления кастомных ассетов
 */
add_action('admin_head', 'add_custom_assets');

function add_custom_assets()
{
    $screen    = get_current_screen();
    $screen_id = $screen ? $screen->id : '';

    if ($screen_id !== 'shop_order') {
        return;
    }

    wp_enqueue_style(
        'woocommerce_factoring004_admin',
        plugin_dir_url('factoring004-gateway/assets/css/factoring004-admin-orders.css').'factoring004-admin-orders.css',
        array(),false,'all'
    );

    wp_enqueue_script(
        'woocommerce_factoring004_admin',
        plugin_dir_url('factoring004-gateway/assets/js/factoring004-admin-orders.js').'factoring004-admin-orders.js',
        array(), false, true
    );

    wp_nonce_field(-1,'factoring004_nonce');

    require_once 'templates/modal.html';

    $scriptData = array(
        'deliveries' => (new WC_Factoring004_Gateway)->get_option('delivery_items')
    );

    wp_localize_script('woocommerce_factoring004_admin', 'factoring004_options', $scriptData);
}

function factoring004_init_gateway_class() {

    class WC_Factoring004_Gateway extends WC_Payment_Gateway
    {

        const REQUIRED_FIELDS = ['billNumber', 'status', 'preappId'];

        const ZONE_NAME = 'Казахстан';

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {
            $this->id = 'factoring004'; // id плагина платежного шлюза
            $this->icon = apply_filters('woocommerce_gateway_icon', plugin_dir_url('factoring004-gateway/assets/images/factoring004.svg').'factoring004.svg'); // URL значка, который будет отображаться на странице оформления заказа рядом с именем вашего шлюза
            $this->has_fields = false; // если вам нужна индивидуальная форма кредитной карты
            $this->method_title = 'Рассрочка 0-0-4'; // заголовок
            $this->method_description = 'Купи сейчас, плати потом! Быстрое и удобное оформление рассрочки на 4 месяца без первоначальной оплаты. Моментальное подтверждение, без комиссий и процентов. Для заказов суммой от 6000 до 200000 тг.'; // описание

            $this->supports = array(
                'products',
                'refunds'
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

            // Хук регистрации js страницы пользователя
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            // Хук регистрации js страницы пользователя в админке
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

            // Регистрация вебхука
            add_action('woocommerce_api_factoring004-post-link', array($this, 'webhook'));

            // Регистрация вывода чекбокс оферты
            if ($this->get_option('agreement_file')) {
                add_action('woocommerce_review_order_before_submit', array($this,'bt_add_checkout_checkbox'));
            }

        }

        public function bt_add_checkout_checkbox()
        {
            $agreementLink = wp_upload_dir()['baseurl'] . '/' . $this->get_option('agreement_file');
            woocommerce_form_field('checkout_factoring004_agreement', array(
                'id'          => 'factoring004_checkbox_agreement',
                'type'        => 'checkbox',
                'class'       => array('factoring004-checkbox-agreement'),
                'required'    => true,
                'label'       => "Я ознакомлен и согласен с условиями <a href='$agreementLink' target='_blank' rel='noopener'>Рассрочка 0-0-4</a>",
            ));
        }

        public function process_admin_options()
        {
            $data = $this->get_post_data();
            $this->set_post_data(array_merge($data,
                [
                    'woocommerce_factoring004_agreement_file'
                        => isset($_FILES['woocommerce_factoring004_agreement_file'])
                        ?
                            $this->uploadAgreementFile($_FILES['woocommerce_factoring004_agreement_file'])
                        :
                            $data['woocommerce_factoring004_agreement_file'],
                    'woocommerce_factoring004_delivery_items'
                        => isset($data['woocommerce_factoring004_delivery_items'])
                        ?
                            implode(',',$data['woocommerce_factoring004_delivery_items'])
                        :
                        ''
                ]));
            return parent::process_admin_options();
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
                'preapp_token' => array(
                    'title'       => 'OAuth Token bnpl-partners',
                    'type'        => 'text',
                ),
                'delivery_token' => array(
                    'title'       => 'OAuth Token AccountingService',
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
                'partner_email' => array(
                    'title'       => 'Partner Email',
                    'type'        => 'text'
                ),
                'partner_website' => array(
                    'title'       => 'Partner Website',
                    'type'        => 'text'
                ),
                'delivery_items' => array(
                    'type'        => 'factoring004_delivery_items',
                ),
                'agreement_file' => array(
                    'type'        => 'factoring004_agreement_file',
                )
            );
        }

        /**
         * Создаем кастомное поле для загрузки файла
         */
        public function generate_factoring004_agreement_file_html()
        {
            ob_start();
            ?>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="woocommerce_factoring004_agreement_file">Файл оферты</label>
                    </th>
                    <td class="forminp">
                        <fieldset>
                            <?php if ($this->get_option('agreement_file')):  ?>
                                <label for="woocommerce_factoring004_agreement_file">
                                    <a target="_blank" href="<?php echo wp_upload_dir()['baseurl'].'/'.$this->get_option('agreement_file');  ?>" class="button-primary">Просмотреть</a>
                                    <button id="factoring004-button-delete" data-filename="<?php echo $this->get_option('agreement_file');  ?>" class="button-primary" type="button">Удалить</button>
                                    <input type="hidden" name="woocommerce_factoring004_agreement_file" value="<?php echo $this->get_option('agreement_file');  ?>">
                                    <?php wp_nonce_field() ?>
                                </label>
                            <?php else: ?>
                                <label for="woocommerce_factoring004_agreement_file">
                                    <button class="button-primary" onclick="document.getElementById('woocommerce_factoring004_agreement_file').click()" type="button" id="factoring004-agreement-file-button">Выбрать файл</button>
                                    <input style="display: none;" type="file" name="woocommerce_factoring004_agreement_file" id="woocommerce_factoring004_agreement_file">
                                </label>
                                <p>Загрузите файл оферты, если вам необходимо его отобразить клиенту</p>
                            <?php endif; ?>
                            <br>
                        </fieldset>
                    </td>
                </tr>
            <?php
            return ob_get_clean();
        }

        /**
         * Создаем кастомное поле для выбора способа доставки
         */
        public function generate_factoring004_delivery_items_html()
        {
            ob_start();
            ?>
            <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="woocommerce_factoring004_delivery_items">Способы доставки</label>
                    </th>
                    <td class="forminp">
                        <fieldset>
                            <label for="woocommerce_factoring004_delivery_items">
                                <?php foreach ($this->getDeliveryItems() as $delivery): ?>
                                    <label style="display: block">
                                        <input
                                            <?php foreach (explode(',', $this->get_option('delivery_items')) as $item): ?>
                                                <?php if ($item === $delivery['id']): ?>
                                                    checked
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            type="checkbox" name="woocommerce_factoring004_delivery_items[]" value="<?php echo $delivery['id'] ?>">
                                        <?php echo $delivery['name'] ?>
                                    </label>
                                <?php endforeach; ?>
                            </label>
                            <br>
                        </fieldset>
                    </td>
                </tr>
            <?php
            return ob_get_clean();
        }

        /**
         * Метод позволяет создать собствунную форму, если оно того требует (например: форму карты)
         */
        public function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }
        }

        /**
         * Пользовательские CSS и JS, в случае если испаользуете в пользовательской части
         */
        public function payment_scripts()
        {
            wp_enqueue_script(
                'woocommerce_factoring004_admin',
                plugin_dir_url('factoring004-gateway/assets/js/factoring004.js').'factoring004.js',
                array(), false, true
            );
            wp_enqueue_style(
                'woocommerce_factoring004',
                plugin_dir_url('factoring004-gateway/assets/css/factoring004.css').'factoring004.css',
                array(),false,'all'
            );
        }

        /**
         * Пользовательские CSS и JS, в случае если испаользуете в админ части
         */
        public function admin_scripts()
        {
            $screen    = get_current_screen();
            $screen_id = $screen ? $screen->id : '';

            if ('woocommerce_page_wc-settings' !== $screen_id) {
                return;
            }

            wp_enqueue_script(
                    'woocommerce_factoring004_admin',
                plugin_dir_url('factoring004-gateway/assets/js/factoring004-admin.js').'factoring004-admin.js',
                array(), false, true
            );

        }

        /**
          * Валидация полей чекаута
         */
        public function validate_fields()
        {
            if (empty($_POST['checkout_factoring004_agreement'])) {
                wc_add_notice('Вам необходимо согласиться с условиями!', 'error');
                return false;
            }
            return true;
        }

        /**
         * Обработка платежа
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $factoring004 = new WC_Factoring004($this->get_option('api_host'),$this->get_option('preapp_token'));

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
                'redirect' => $redirectLink
            );
        }


        public function send_otp_delivery($data)
        {
            $order = wc_get_order($data['order_id']);

            if (!$order) {
                wp_send_json(false);
            }

            $factoring004 = new WC_Factoring004($this->get_option('api_host'), $this->get_option('delivery_token'));

            if (!$factoring004->sendOtpDelivery($this->get_option('partner_code'), $order)) {
                wp_send_json(false);
            }

            wp_send_json(true);

        }

        /**
         * @param $data
         * отправка смс для возврата
         */
        public function send_otp_return($data)
        {
            $order = wc_get_order($data['order_id']);

            if (!$order) {
                wp_send_json(false);
            }

            $amount = empty($data['amount']) ? 0 : (int) $data['amount'];

            $factoring004 = new WC_Factoring004($this->get_option('api_host'), $this->get_option('delivery_token'));

            if (!$factoring004->sendOtpReturn($amount, $this->get_option('partner_code'), $order)) {
                wp_send_json(false);
            }

            wp_send_json(true);

        }

        /**
         * @param $data
         * проверка смс кода для возврата
         */

        public function check_otp_return($data)
        {
            $order = wc_get_order($data['order_id']);

            if (!$order) {
                wp_send_json(false);
            }

            $amount = empty($data['amount']) ? 0 : (int) $data['amount'];

            $factoring004 = new WC_Factoring004($this->get_option('api_host'), $this->get_option('delivery_token'));

            if (!$factoring004->checkOtpReturn($amount, $this->get_option('partner_code'), $order, $data['otp_code'])) {
                wp_send_json(false);
            }

            $order->update_status('refunded');

            wp_send_json(true);
        }

        /**
         * обработка отмены
         */
        public function process_cancel($data)
        {
            $order = wc_get_order($data['order_id']);

            if (!$order) {
                wp_send_json(false);
            }

            $factoring004 = new WC_Factoring004($this->get_option('api_host'),$this->get_option('delivery_token'));

            if (!$factoring004->cancel($order, $this->get_option('partner_code'))) {
                wp_send_json(false);
            }

            $order->update_status('cancelled');

            wp_send_json(true);

        }

        /**
         * обработка доставки
         */
        public function process_delivery($data)
        {
            $order = wc_get_order($data['order_id']);

            if (!$order) {
                wp_send_json(false);
            }

            $factoring004 = new WC_Factoring004($this->get_option('api_host'),$this->get_option('delivery_token'));

            if (!$factoring004->delivery($data['order_id'],$this->get_option('partner_code'), $data['otp_code'])) {
                wp_send_json(false);
            }

            $order->update_status('completed');

            wp_send_json(true);
        }

        /**
         * Обработка возврата
         */

        public function process_refund($order_id, $amount = null, $reason = '')
        {
            $order = wc_get_order($order_id);

            $factoring004 = new WC_Factoring004($this->get_option('api_host'),$this->get_option('delivery_token'));

            if (!$factoring004->return($order, ceil($amount), $this->get_option('partner_code'))) {
                return false;
            }

            $order->update_status('refunded');

            return true;
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
            } else {
                wp_send_json(['success' => false, 'error' => 'Unexpected status'], 400);
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'factoring004_order_preapps';
            $result = $wpdb->insert("$table_name", array('order_id'=>$request['billNumber'], 'preapp_uid'=>$request['preappId']));

            if (!$result) {
                wp_send_json(['success' => false, 'error' => 'An error occurred'], 500);
            }

            wp_send_json(['response' => 'ok'],200);

        }

        /**
         * получение всех видов доставки по зоне КЗ
         */
        private function getDeliveryItems()
        {
            $zones = WC_Shipping_Zones::get_zones();
            $methods = [];
            foreach ($zones as $zone) {
                if ($zone['zone_name'] === static::ZONE_NAME) {
                    foreach ($zone['shipping_methods'] as $method) {
                        if ($method->enabled === 'yes') {
                            $methods[] = [
                                'id'=>$method->id,
                                'name'=>$method->method_title
                            ];
                        }
                    }
                }
            }
            return $methods;
        }

        /**
         * загрузка файла
         */
        private function uploadAgreementFile($agreement_file)
        {
            $filename = '';
            if ($agreement_file['tmp_name']) {
                $ext = pathinfo($agreement_file['name'], PATHINFO_EXTENSION);
                $filename = basename($agreement_file['name'],'.'.$ext) . '_' . uniqid(rand(), true) . '.' . $ext;
                move_uploaded_file($agreement_file['tmp_name'], wp_upload_dir()['basedir'].'/' . $filename);
            }
            return $filename;
        }

        /**
         * удаление файла
         */
        public function destroyAgreementFile($agreement_file)
        {
            if (file_exists(wp_upload_dir()['basedir'].'/' . $agreement_file)) {
                if (!unlink(wp_upload_dir()['basedir'].'/' . $agreement_file)) {
                    wp_send_json(['success'=>false,'message'=>'Неуспех!']);
                }
            }

            $this->update_option('agreement_file');
            wp_send_json(['success'=>true,'message'=>'Успех!']);
        }
    }
}