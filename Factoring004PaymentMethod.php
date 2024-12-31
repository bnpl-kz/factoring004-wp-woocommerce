<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Factoring004PaymentMethod extends AbstractPaymentMethodType {
    public function get_name() {
        return 'factoring004';
    }

    public function is_active() {
        return true;
    }

    public function get_payment_method_script_handles() {
        return ['factoring004-payment-script'];
    }

    public function get_payment_method_data() {
        return [
            'title'       => 'Рассрочка 0-0-4',
            'description' => 'Купи сейчас, плати потом! Быстрое и удобное оформление рассрочки на 4 месяца без первоначальной оплаты. Моментальное подтверждение, без комиссий и процентов. Для заказов суммой от 6000 до 200000 тг.',
        ];
    }

    public function initialize() {
        add_action('woocommerce_blocks_payment_method_type_registration', [$this, 'register_payment_method_block']);
    }

    public function register_payment_method_block($payment_method_registry) {
        error_log('Factoring004PaymentMethod: регистрация вызвана');
        if (!is_a($payment_method_registry, \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry::class)) {
            error_log('Factoring004PaymentMethod: Неверный тип $payment_method_registry.');
            return;
        }

        $payment_method_registry->register(
            $this->get_name(),
            [
                'title'       => $this->get_payment_method_data()['title'],
                'description' => $this->get_payment_method_data()['description'],
                'scripts'     => $this->get_payment_method_script_handles(),
            ]
        );
        error_log('Factoring004PaymentMethod: метод успешно зарегистрирован.');
    }

}
