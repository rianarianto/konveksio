<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Customer;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Facades\Filament;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Pesanan Baru')
                ->icon('heroicon-o-plus')
                ->modalHeading('Inisialisasi Pesanan Baru')
                ->modalDescription('Isi data pelanggan terlebih dahulu, lalu Anda akan diarahkan untuk mengisi detail produk.')
                ->modalWidth('2xl')
                ->modalSubmitActionLabel('Buat & Lanjut Input Produk')
                ->form([
                    Grid::make(2)->schema([
                        Select::make('customer_id')
                            ->label('Nama Pelanggan')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nama Pelanggan')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->label('Kontak')
                                    ->required()
                                    ->tel()
                                    ->maxLength(255),
                                Textarea::make('address')
                                    ->label('Alamat')
                                    ->required()
                                    ->rows(3),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $customer = Customer::create([
                                    'shop_id' => Filament::getTenant()->id,
                                    'name' => $data['name'],
                                    'phone' => $data['phone'],
                                    'address' => $data['address'],
                                ]);
                                return $customer->id;
                            }),

                        DatePicker::make('order_date')
                            ->label('Tanggal Pesanan')
                            ->required()
                            ->default(now())
                            ->native(false),

                        DatePicker::make('deadline')
                            ->label('Deadline')
                            ->required()
                            ->native(false)
                            ->minDate(now()),

                        Toggle::make('is_express')
                            ->label('Pesanan Express')
                            ->onIcon('heroicon-m-bolt')
                            ->default(false)
                            ->live()
                            ->columnSpan(2),
                    ]),
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $data['shop_id'] = Filament::getTenant()->id;
                    $data['status'] = 'draft'; 
                    return $data;
                })
                ->successRedirectUrl(fn (Order $record): string => OrderResource::getUrl('edit', ['record' => $record])),
        ];
    }

    public function mount(): void
    {
        parent::mount();

        if (request()->query('filter') === 'piutang_macet') {
            $this->tableFilters['piutang_macet'] = ['isActive' => true];
        }
    }
}
