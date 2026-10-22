<?php

namespace App\Filament\Resources\VendorPayments\Pages;

use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\VendorPayments\VendorPaymentResource;

class ListVendorPayments extends ListRecords
{
    protected static string $resource = VendorPaymentResource::class;
}
