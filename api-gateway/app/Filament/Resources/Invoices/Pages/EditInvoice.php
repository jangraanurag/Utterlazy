<?php

namespace App\Filament\Resources\Invoices\Pages;

use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Invoices\InvoiceResource;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;
}
