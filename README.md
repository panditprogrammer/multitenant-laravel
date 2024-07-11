# Multitenancy Service

Default routes: 
`route > tenant.php`



# pre-configurations :


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
