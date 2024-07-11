# Multitenancy Service


### pre-configurations :

Add central domain in `config/app.php`



0. Create two migration OR copy two table from `/database/migrations` (`create_users_table` and `create_pasword_reset_tokens`) into `/database/migrations/tenant`


1. `TenancyServiceProvider:class` add this  class to 

 app > config > app.php

2. Create `Tenant` Model 

```
<?php

namespace App;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;
}
```



### create function fillable value for Tenant 

```
public static function getCustomColumns(): array
    {
        return [
            "id",
            "name",
            "email",
            "password"
            ...
        ];
    }
```


### Modify Provider > RouteServiceProvider.php for access admin only (central domains)



## Default routes: 
Add your route for app such as login,register, contact us, about us here 
`route > tenant.php`




# how to access urls (super user or admin or owner)

`http://localhost:8000` -> main app home page 

`http://localhost:8000/dashboard` main dashboard

`http://localhost:8000/tenants` view all tenants (users) register on website   


# how to access urls (visitors or users)

`http://test.localhost:8000` user 1

`http://new.localhost:8000` user 2

`http://demo.localhost:8000` user 3