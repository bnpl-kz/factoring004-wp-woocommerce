document.addEventListener('DOMContentLoaded', function () {
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { useSelect } = window.wp.data;

    if (!registerPaymentMethod) {
        console.error('wcBlocksRegistry не найден или не доступен.');
        return;
    }

    // график платежей
    const PaymentSchedule = function () {
        const selectedPaymentMethod = useSelect((select) =>
            select('wc/store/payment')?.getActivePaymentMethod()
        );

        React.useEffect(() => {
            if (selectedPaymentMethod === 'factoring004') {
                const cartTotals = window.wp.data.select('wc/store/cart').getCartTotals();

                if (cartTotals) {
                    const totalPrice = cartTotals.total_price;

                    const schedule = new Factoring004.PaymentSchedule({
                        elemId: "factoring004-schedule",
                        totalAmount: totalPrice / 100,
                    });

                    schedule.render();
                } else {
                    console.error('Не удалось получить данные о корзине.');
                }
            }
        }, [selectedPaymentMethod]);

        if (selectedPaymentMethod !== 'factoring004') {
            return null; // Не отображаем график, если выбран другой метод
        }

        return React.createElement('div', { id: 'factoring004-schedule', style: { padding: '24px' } });
    };

    const Content = function () {
        return React.createElement(
            'div',
            { className: 'factoring004-payment-method' },
            React.createElement('p', null, 'Купи сейчас, плати потом! Быстрое и удобное оформление рассрочки на 4 месяца без первоначальной оплаты.'),
            React.createElement(PaymentSchedule, null)
        );
    };

    const Edit = function () {
        return React.createElement(
            'div',
            { className: 'factoring004-payment-method-edit' },
            'Это редактор для метода оплаты Рассрочка 0-0-4.'
        );
    };

    let interceptFetch = true; // Флаг для управления перехватом

    const originalFetch = window.fetch;
    window.fetch = async function (...args) {
        if (!interceptFetch) {
            // Если перехват отключен, просто возвращаем оригинальный fetch
            return originalFetch(...args);
        }

        console.log('Запрос перехвачен:', args);
        const response = await originalFetch(...args);

        // Проверяем, что это JSON
        if (response.headers.get('content-type')?.includes('application/json')) {
            const clonedResponse = response.clone();

            clonedResponse.json()
                .then((data) => {
                    console.log('Данные ответа:', data);

                    // Проверяем, что это ответ на чекаут
                    if (
                        args[0].includes('/wp-json/wc/store/v1/checkout') &&
                        args[1]?.method === 'POST'
                    ) {
                        console.log('POST-запрос на чекаут завершен.');

                        const redirectLink = data?.payment_result?.payment_details?.find(
                            (detail) => detail.key === 'redirectLink'
                        )?.value;

                        if (redirectLink) {
                            console.log('RedirectLink найден:', redirectLink);

                            // Обрабатываем модальное окно
                            const bnplKzApi = new BnplKzApi.CPO({
                                rootId: 'modal-factoring004',
                                callbacks: {
                                    onError: () => window.location.replace(redirectLink),
                                    onDeclined: () => window.location.replace("/"),
                                    onClosed: () => console.log('onClosed'), //optional
                                    onEnd: () => {
                                        const checkoutData = window.wcSettings?.checkoutData;
                                        if (checkoutData) {
                                            const orderId = checkoutData.order_id;
                                            const orderKey = checkoutData.order_key;
                                            const redirectUrl = `/checkout/order-received/${orderId}/?key=${orderKey}&front=Y`;
                                            console.log('Сформированный URL:', redirectUrl);

                                            if (redirectUrl) {
                                                window.location.replace(redirectUrl);
                                            } else {
                                                console.error('Redirect URL отсутствует. Перенаправление на главную.');
                                                window.location.replace("/");
                                            }
                                        }
                                    },
                                },
                            });


                            bnplKzApi.render({ redirectLink });
                            console.log('Модальное окно BNPL API отображено.');

                            // Отключаем перехватчик
                            interceptFetch = false;
                            console.log('Перехват fetch отключен.');
                        } else {
                            console.warn('RedirectLink отсутствует.');
                        }
                    }
                })
                .catch((error) => {
                    console.error('Ошибка при обработке JSON:', error);
                });
        } else {
            console.warn('Ответ не является JSON:', response);
        }

        return response;
    };

    const settings = {
        name: 'factoring004',
        label: 'Рассрочка 0-0-4',
        ariaLabel: 'Метод оплаты Рассрочка 0-0-4',
        title: 'Рассрочка 0-0-4',
        description:
            'Купи сейчас, плати потом! Быстрое и удобное оформление рассрочки на 4 месяца без первоначальной оплаты. Моментальное подтверждение, без комиссий и процентов. Для заказов суммой от 6000 до 200000 тг.',
        content: React.createElement(Content),
        edit: React.createElement(Edit),

        canMakePayment: function () {
            return true;
        },
    };

    registerPaymentMethod(settings);
    console.log('Factoring004: метод оплаты зарегистрирован.');
});