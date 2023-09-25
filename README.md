# CodeIgniter 4 Database Relationships

A simple solution to add relationships to your Codeigniter 4 application.

## Installation

You can install this library from composer by running `composer install immachakata/ci4-relationships`

## Defining Relationships

You can define as many relationships as you want using the `hasMany` or `hasOne` functions as needed. Here's an example of defining a single relationship for a users avatar and multiple relationship for a users links.

```php
namespace App\Models;

use CodeIgniter\Model;
use CI4Extensions\Database\RelationshipsTrait;

class UserModel extends Model
{
    use RelationshipsTrait;
    
    ... 
    // declare relationships
    public function initialize(){
        $this->hasOne('avatar', AvatarModel::class, 'user_id');
        $this->hasMany('links', LinkModel::class, 'user_id');
    }
}
```

## Using Relationships

When you perfom your query, explicitly mention the field name you want back as you defined in the initialize function. To do so, use the `with($fieldName)` function. Here's an example of retrieving the user avatar

```php
class UserController extends BaseController
{
    public function index(){
        ...
        $this->respond([
            'user' => model(UserModel::class)->with('avatar')->findAll()
        ]);
    }
}
```

## Credits

Forked from [michalsn/codeigniter-nested-model](https://github.com/michalsn/codeigniter-nested-model)