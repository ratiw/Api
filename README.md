Api
===

A simple Laravel based API package using [Fractal](http://fractal.thephpleague.com/). At the moment, Laravel 5 is not yet officially released, so this package was based on Laravel 4.2.

This package simplify the API request building and transforming using The PHP Leagues's `Fractal` as the underlying engine.

To use it, you just need to follow these 3 steps
	1. Create API endpoint by extending the `Ratiw\Api\BaseApiController` class.
	2. Create Transformer by extending `Ratiw\Api\BaseTransformer` class.
	3. Create a route for it.

Installation
----

Installation can be done via `composer`

```
"require": {
    "ratiw/api": "dev-master"
}
```
This package requires `Laravel Framework` and `Fractal`, so it will be pull in automatically

Usage
----
####Let's make the assumptions
- The API code will be in `Api` directory.
- The following PSR-4 namespaces were defined in `composer.json' like so
```json
	...
	"autoload": {
		"psr-4": {
			"Api\\": "app/Api",
			"Entities\\": "app/Entities"
		}
	}
	...
```
- The API classes will be put in `Api\Controllers` directory.
- The Transformer classes will be put in `Api\Transformers` directory.
- A simple `Client` eloquent based class exists in the `Entities\Clients' directory.
```php
<?php namespace Entities\Clients;

class Client extends \Eloquent
{
	protected $table = 'clients';

	public function saleRep()
	{
		return $this->belongsTo('Entities\Staffs\Sale', 'sale_id');
	}
}
```

#### Creating API class
```php
<?php namespace Api\Controllers;

use Ratiw\Api\BaseApiController;

class ClientsController extends BaseApiController
{
	protected $model = 'Entities\Clients\Client';
}
```

#### Createing Transformer class
```php
<?php namespace Api\Transformers;

use Ratiw\Api\BaseTransformer;

class ClientTransformer extends BaseTransformer
{
	public function transform($client)
	{
		return [
			'id' => (int) $client->id,
			'short_name' => $client->shortname,
			'full_name' => $client->name,
			'sale_id' => $client->sale_id,
			'contact' => $client->contact,
			'email' => $client->email,
			'phone' => $client->phone
		];
	}
}
```

####Creating Route for the endpoint
```php
Route::group(['prefix' => 'api'], function()
{
    Route::get('clients/{id}', 'Api\Controllers\ClientsController@show');
    Route::get('clients', 'Api\Controllers\ClientsController@index');
    ...
}
```

Additional Info
---
####Existing Routes
By default, the API package comes ready with two methods
- __index()__ for querying the collection
- __show()__ for querying specific item

You should be able to test it via your browser. Just go to http://localhost or http://localhost:8000 or whatever appropriate in your development environment. However, if you've setup your environment different than that, you will have to create a configuration file for that.

####Allowable Domain Configuration
The API class, by default, will first check to see if the calling party is from the allowable host or domain. If it is originate from the domain other than the ones configured, it will return an error.

By default, the allowable hosts are:
- localhost
- localhost:8000

In order to change this or add more hosts, you will need to create a configuration file in the `app/config` directory like so,
```php
// app/config/api.php

<?php

return [
    'allow_hosts' => [
        'localhost:8000',
        'localhost'
        'myawesome.app:8000',  //<-- your addition allowable domain
    ],

    'allow_paths' => [
        'api/*'
    ]	
];

```

####Search Operations

####Eager Load the results
To eager load the result of the query, just specifying it in the `$
####Specifying Transformer class

####Overriding Transformer Path

####Embedded Resource
