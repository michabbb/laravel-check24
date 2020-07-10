# Import Orders from check24.de

## Installation

You can install the package via composer:

```bash
composer require macropage/laravel-check24
```

Publish config using `php artisan vendor:publish --provider="macropage\laravel_check24\Check24ServiveProvider"`  

Update your config `config/check24.php`
```php
<?php

return [
    'accounts' => [
        'CUSTOMER1' => [
            'orders' => [
                'ftp' => [
                    'driver'   => 'ftp',
                    'host'     => 'ftp.xxxxx.de',
                    'username' => 'xxxxxxx',
                    'password' => 'xxxxxxx',
                ]
            ]
        ]
    ]

];
```
`CUSTOMER1` is just a placeholder, choose any name and as many you want.  
Create a folder named "done" in your ftp-home.

## Requirements
[A Cache-Provider](https://laravel.com/docs/7.x/cache#cache-tags) that supports "tagging".

## Facade
With the Facade `Check24` you can call these methods:

- Check24::setCustomerConfig('CUSTOMER1')
- Check24::getXMLOrders (fetch orders via ftp or from cache)
- Check24::getSingleXMLOrder($orderId) `$OrderFileName = 'ORDER_' . $orderId . '.xml';`
- Check24::getXMLOrdersCached (same like getXMLOrders, but fetch data from cache)
- Check24::getDistinctShippingDescr (for debugging: unique list of shipping-description within all orders)
- Check24::getDistinctPaymentDescr (for debugging: unique list of payment-description within all orders)
- Check24::setDone($xmlFileName) (moves file to 'done')
- Check24::uploadShippingData($order_number, $carrier, $shipping_number) (uploading shipping infos to ftp)

**NOTICE:** using "getXMLOrders" without cache, flushes the whole cache for your CUSTOMER1  
in case you want to flush the cache manually: `Cache::tags('check24.CUSTOMER1')->flush();` 

## Usage: Artisan Commands
- check24:list-orders {account_name} {orderid?} {--cache}
- check24:set-done {account_name} {orderid}

"list-orders" prints all orders as php-array  
"set-done" moves the xml-order-file into the folder named "done".

## Usage: in your code
```php
<?php
Check24::setCustomerConfig($this->argument('customer'));
if ($this->argument('orderid')) {
    $singleOrder = Check24::getSingleXMLOrder($this->argument('orderid'), $this->option('cache'));
} else {
    $OrderArrays = Check24::getXMLOrders($this->option('cache'));
}
```

## Contributing

Help is appreciated :-)

## You need help?
_yes, you can hire me!_  
    
[![xing](https://i.imgur.com/V3RuEM7.png)](https://www.xing.com/profile/Michael_Bladowski/cv)
[![linkedin](https://i.imgur.com/UNH7YtM.png)](https://www.linkedin.com/in/macropage/)
[![twitter](https://i.imgur.com/iSv2xRb.png)](https://twitter.com/michabbb)

## Credits
- [Michael Bladowski](https://github.com/michabbb)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
