<?php

namespace App\Filament\Resources\VendorPayments\Pages;

use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\VendorPayments\VendorPaymentResource;

class CreateVendorPayment extends CreateRecord
{
    protected static string $resource = VendorPaymentResource::class;
}
