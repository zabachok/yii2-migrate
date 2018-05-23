# yii2-migrate
Cataloging migrations by date

## Using

Add in your console config file^

```php
    'controllerMap' => [
        'migrate' => [
            'class' => \zabachok\migrate\MigrateController::class,
        ],
    ],
```
