# Модуль оплаты WooCommerce Рассрочка 0-0-4

## Установка

* Создайте резервную копию вашего магазина и базы данных
* Загрузите [woocommerce-factoring004.zip](https://github.com/bnpl-partners/factoring004-wp-woocommerce.git?raw=true)
* Зайдите в панель администратора Wordpress (www.yoursite.com/wp-admin/)
* Выберите _Плагины → Добавить новый_
* Загрузите модуль через **Добавить новый**
* Выберите _Плагины → Установленные_ и найдите _Рассрочка 0-0-4_ модуль и активируйте его.

![Activate](https://github.com/bnpl-partners/factoring004-wp-woocommerce/raw/main/doc/activate.png)

## Настройка

Зайдите в _WooCommerce → Настройки → Платежи_

![Setup-1](https://github.com/bnpl-partners/factoring004-wp-woocommerce/raw/main/doc/wc_settings.png)

Из списка ниже нажмите на `Рассрочка 0-0-4` и откроется
страница настройки модуля.


![Setup-2](https://github.com/bnpl-partners/factoring004-wp-woocommerce/raw/main/doc/payment_fields.png)

* Получите данные от АПИ платежной системы
* Заполните данные
* нажмите _Сохранить изменения_

Модуль настроен и готов к работе.

## Примечания

Разработанно и протестированно с:

* Wordress 5.9.3
* WooCommerce 6.3.1
* PHP 5.x/7.x

## Тестирование

Вы можете использовать тестовые данные выданные АПИ платежной системы, чтобы настроить способ оплаты в тестовом режиме