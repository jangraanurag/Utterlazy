<?php

namespace App\Filament\Pages;

use App\Models\Restaurant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class GstReports extends Page
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected string $view = 'filament.pages.gst-reports';

    protected static \UnitEnum|string|null $navigationGroup = 'GST & Finance';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('restaurant_id')
                    ->label('Restaurant')
                    ->options(Restaurant::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('month')
                    ->label('Month')
                    ->options([
                        '01' => 'January',
                        '02' => 'February',
                        '03' => 'March',
                        '04' => 'April',
                        '05' => 'May',
                        '06' => 'June',
                        '07' => 'July',
                        '08' => 'August',
                        '09' => 'September',
                        '10' => 'October',
                        '11' => 'November',
                        '12' => 'December',
                    ])
                    ->required(),
                Select::make('year')
                    ->label('Year')
                    ->options(function () {
                        $years = [];
                        for ($y = 2025; $y <= date('Y'); $y++) {
                            $years[$y] = (string) $y;
                        }
                        return $years;
                    })
                    ->default(date('Y'))
                    ->required(),
            ])
            ->statePath('data');
    }

    public function downloadReport()
    {
        $data = $this->form->getState();
        $restaurant = Restaurant::find($data['restaurant_id']);
        if (!$restaurant) {
            Notification::make()->title('Restaurant not found')->danger()->send();
            return;
        }

        $url = config('app.catalog_service_url') . "/api/admin/gst/report/{$data['restaurant_id']}/{$data['year']}/{$data['month']}";
        
        try {
            $response = Http::timeout(60)->get($url);
            if ($response->successful()) {
                $filename = "GST_Report_{$restaurant->name}_{$data['year']}-{$data['month']}.xlsx";
                $path = tempnam(sys_get_temp_dir(), 'gst_');
                file_put_contents($path, $response->body());
                
                return response()->download($path, $filename, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])->deleteFileAfterSend(true);
            }
            
            Notification::make()->title('Failed to generate report: ' . $response->body())->danger()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }
}
