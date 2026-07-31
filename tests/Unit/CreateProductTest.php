<?php

namespace Tests\Unit;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use Tests\TestCase;

class CreateProductTest extends TestCase
{
    public function test_mutate_form_data_before_create_normalizes_last_buy_price(): void
    {
        $page = new class extends CreateProduct {
            public function exposeMutateFormDataBeforeCreate(array $data): array
            {
                return $this->mutateFormDataBeforeCreate($data);
            }
        };

        $result = $page->exposeMutateFormDataBeforeCreate([
            'last_buy_price' => 'Rp 15.500',
        ]);

        $this->assertSame(15500.0, $result['last_buy_price']);
    }
}
