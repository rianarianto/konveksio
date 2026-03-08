<?php

namespace App\Filament\Resources\OrderReturns\Pages;

use App\Filament\Resources\OrderReturns\OrderReturnResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrderReturn extends CreateRecord
{
    protected static string $resource = OrderReturnResource::class;
}
