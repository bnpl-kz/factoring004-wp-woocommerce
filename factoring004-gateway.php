<?php

/*
 * Plugin Name: Рассрочка 0-0-4
 * Plugin URI:
 * Description: Купи сейчас, плати потом! Быстрое и удобное оформление рассрочки на 4 месяца без первоначальной оплаты для жителей Казахстана. Моментальное подтверждение, без комиссий и процентов. Для заказов суммой от 6000 до 200000 тг.
 * Author: Team BNPL
 * Author URI:
 * Version: 1.0.1
 */

/**
 * Этот хук действия регистрирует наш класс PHP как платежный шлюз WooCommerce.
 */

add_filter('woocommerce_payment_gateways', 'factoring004_add_gateway_class');
function factoring004_add_gateway_class($gateways) {
    $gateways[] = 'WC_Factoring004_Gateway';
    return $gateways;
}

/**
 * Этот хук действия регистрирует функцию для работы с условиями отображения платежа
 */

add_filter('woocommerce_available_payment_gateways', 'disable_factoring004_above_6000_or_below_200000');

function disable_factoring004_above_6000_or_below_200000($available_gateways)
{
    $minSum = 6000;
    $maxSum = 200000;
    if (WC()->cart->total < $minSum || WC()->cart->total > $maxSum) {
        unset($available_gateways['factoring004']);
    }
    return $available_gateways;
}

add_action('plugins_loaded', 'factoring004_init_gateway_class');

/**
 * Хук регистрации обработчика удаления файла
 */

add_action('wp_ajax_factoring004_agreement_destroy', 'callback');

function callback()
{
    if (!wp_verify_nonce($_POST['_nonce'])) {
        wp_die(0,400);
    }

    $filename = $_POST['filename'];

    call_user_func(array(new WC_Factoring004_Gateway,'destroyAgreementFile'),$filename);
}

function factoring004_init_gateway_class() {


    class WC_Factoring004_Gateway extends WC_Payment_Gateway
    {

        private $zone_name = 'Казахстан';

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {
            $this->id = 'factoring004'; // id плагина платежного шлюза
            $this->icon = apply_filters( 'woocommerce_gateway_icon', plugin_dir_url('factoring004-gateway/assets/images/factoring004_logo.png').'factoring004_logo.png'); // URL значка, который будет отображаться на странице оформления заказа рядом с именем вашего шлюза
            $this->has_fields = false; // если вам нужна индивидуальная форма кредитной карты
            $this->method_title = 'Рассрочка 0-0-4'; // заголовок
            $this->method_description = 'Описание для Рассрочка 0-0-4'; // описание

            $this->supports = array(
                'products'
            );

            // Метод со всеми полями параметров
            $this->init_form_fields();

            // Загрузите настройки
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');

            // Хук действия сохраняет настройки
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Хук регистрации js страницы пользователя
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            // Хук регистрации js страницы пользователя в админке
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

            // Регистрация вебхука
            add_action('woocommerce_api_{webhook name}', array($this, 'webhook'));

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
                        => implode(',',$data['woocommerce_factoring004_delivery_items'])
                ]));
            return parent::process_admin_options();
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
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
                                <select multiple name="woocommerce_factoring004_delivery_items[]" id="woocommerce_factoring004_delivery_items">
                                    <?php foreach ($this->getDeliveryItems() as $delivery): ?>
                                        <option
                                            <?php foreach (explode(',', $this->get_option('delivery_items')) as $item): ?>
                                                <?php if ($item === $delivery['id']): ?>
                                                    selected
                                                <?php endif; ?>
                                            <?php endforeach; ?> value="<?php echo $delivery['id'] ?>"><?php echo $delivery['name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
            if (!(int)isset($_POST['checkout_factoring004_agreement'])) {
                wc_add_notice('Вам необходимо согласиться с условиями!', 'error');
            }
        }

        /**
         * Обработка платежа
         */
        public function process_payment($order_id)
        {
            print_r($order_id);die;
        }

        /**
         * Обработка webhook (если он нужен)
         */
        public function webhook()
        {
            //
        }

        /**
         * получение всех видов доставки по зоне КЗ
         */
        private function getDeliveryItems()
        {
            $zones = WC_Shipping_Zones::get_zones();
            $methods = [];
            foreach ($zones as $zone) {
                if ($zone['zone_name'] === $this->zone_name) {
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
                    wp_die();
                }
            }

            $this->update_option('agreement_file');
            wp_send_json(['success'=>true,'message'=>'Успех!']);
            wp_die();
        }
    }
}