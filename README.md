Api
===

A simple Laravel based API package using [Fractal](http://fractal.thephpleague.com/). At the moment, Laravel 5 is not yet officially released, so this package was based on Laravel 4.2.

This package simplify the API request building and transforming using The PHP Leagues's `Fractal` as the underlying engine.

To use it, you just need to follow these 3 steps

- Create API endpoint by extending the `Ratiw\Api\BaseApiController` class.
- Create Transformer by extending `Ratiw\Api\BaseTransformer` class.
- Create a route for it.

Installation
----

Installation can be done via `composer`

```
"require": {
    "ratiw/api": "dev-master"
}
```
This package requires `Laravel Framework` v4.2 and `Fractal` v0.9.*, so it will be pull in automatically

Usage
----
####Let's make the assumptions
- The API code will be in `Api` directory.
- The following PSR-4 namespaces were defined in `composer.json` like so
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

#### Createing Transformer class (optional)
This is optional though. You don't need to create a Transformer if you don't want to transform any field.
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

You should be able to test it via your browser. Just go to `http://localhost` or `http://localhost:8000` or whatever appropriate in your development environment. However, if you've setup your environment different than that, you will have to create a configuration file for that.

__Note:__ If you use Google Chrome browser, you can install [JSONView](https://chrome.google.com/webstore/detail/jsonview/chklaanhfefbnpoihckbnefhakgolnmc) from Chrome Web Store to help prettify the JSON result.

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
####Sort Operations
Sort operation can be done by passing `sort` parameter in the query string when calling the Api controller.
```
http://localhost:8000/clients?sort=shortname
```
To sort in descending order, just prepend the sort column with `-`
```
http://localhost:8000/clients?sort=-shortname
```

####Filtering Results
The results can be filtered by specifying the the `q` parameter in the query string. 
```
http://localhost:8000/clients?q=John
```
By default, it will perform `where` clause on the field named `code`, which will cause error if your model does not have `code` field.
You can fix this by overriding the `search()` method, like so.
```php
	...
	protected function search($query, $searchStr)
	{
		return $query->where('shortname', 'like', "%$searchStr%")
			->where('name', 'like', '%$searchStr%');
	}
	...
```

####Paginated Results
The returned or transformed result is paginated by default to 10 records. To change this, just pass `per_page` parameter on the query string.
```
http://localhost:8000/clients?per_page=20
```

####Eager Load the results
To eager load the result of the query, just specifying it in the `$eagerLoads` array property of the Api class.
```php
...
class ClientsController extends BaseApiController
{
	$protected $eagerLoads = ['saleRep'];
	...
}
```
####Specifying Transformer class
API package will automatically looks for a corresponding Transformer class using the following criteria:

- Use the Transformer class specified in `$transformer` property. If the Transformer does not exist, exception will be thrown.
- Use the base name of the specified `$model` property to guess the Transformer class. If the Transformer class does not exist, the `Ratiw\Api\BaseTransformer` class will be used.

This provides enough ease and flexibility. If your project is *small and not so complicated*, you can just put the Api classes and Transformer classes in the same directory. Or, you won't even have to define any Transformer for the Api class if you do not need to transform anything.

But if your project is quite complex or you prefer putting things in directory where you can organized things neatly, you have the flexibility to do so by specifying the `$transformer` class to use in the Api class.
```php
...
class ClientsController extends BaseApiController
{
	protected $model = 'Entities\Clients\Client';

	protected $transformer = 'Api\Transformers\ClientTransformer';
	...
}
...
```

####Overriding Transformer Path
By default, the Api class will look for its associated Transformer class in the same directory, but you can override this by putting the `transformer_path` in the `app/config/api.php` file.
```php
<?php
return [
	'allowable_path' => [...];
	...
	'transformer_path' => 'Api\\Transfomers\\';
];
```
__Note__ the double backslash `\\` in the path. Double backslash at the end is not required though.

####Embedded Resources in Transformer
Embbed resources (nested resources) can be included using the machanism defined by `Fractal`.
```php
...
class ClientTransformer extends BaseTransformer
{
	protected $defaultIncludes = ['saleRep'];

	public function transform($client)
	{
		...
	}

    public function includeSaleRep($client)
    {
        $saleRep = $client->saleRep;

        return $this->item($saleRep, new SaleTransformer);
    }

}
```
