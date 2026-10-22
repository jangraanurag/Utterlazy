<?php

namespace App\Filament\Resources\Invoices\Pages;

use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Invoices\InvoiceResource;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;
}
