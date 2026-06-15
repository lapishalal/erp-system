<?php

namespace App\Filament\Resources\PayrollResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'details';
    protected static ?string $title = 'Rincian Gaji & Potongan';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Jenis')
                    ->options([
                        'EARNING' => 'Penghasilan',
                        'DEDUCTION' => 'Potongan',
                        'BPJS_COMPANY' => 'BPJS Perusahaan',
                        'BPJS_EMPLOYEE' => 'BPJS Karyawan',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Nama Komponen')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('amount')
                    ->label('Jumlah')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->default(0),
                Forms\Components\Textarea::make('description')
                    ->label('Keterangan'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'EARNING' => 'success',
                        'DEDUCTION' => 'danger',
                        'BPJS_COMPANY' => 'warning',
                        'BPJS_EMPLOYEE' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'EARNING' => 'Penghasilan',
                        'DEDUCTION' => 'Potongan',
                        'BPJS_COMPANY' => 'BPJS Perusahaan',
                        'BPJS_EMPLOYEE' => 'BPJS Karyawan',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('IDR')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(30),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'EARNING' => 'Penghasilan',
                        'DEDUCTION' => 'Potongan',
                        'BPJS_COMPANY' => 'BPJS Perusahaan',
                        'BPJS_EMPLOYEE' => 'BPJS Karyawan',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === 'DRAFT'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === 'DRAFT'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === 'DRAFT'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}