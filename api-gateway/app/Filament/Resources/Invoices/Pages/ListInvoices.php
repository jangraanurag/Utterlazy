<?php

namespace App\Filament\Resources\Invoices\Pages;

use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\Invoices\InvoiceResource;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;
}
