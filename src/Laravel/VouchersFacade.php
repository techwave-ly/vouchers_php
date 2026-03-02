<?php

namespace Commerce\Vouchers\Laravel;

use Illuminate\Support\Facades\Facade;

class VouchersFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'vouchers';
    }
}
