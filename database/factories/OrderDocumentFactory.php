<?php

namespace Database\Factories;

use App\Models\OrderDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDocument>
 */
class OrderDocumentFactory extends Factory
{
    protected $model = OrderDocument::class;

    public function definition(): array
    {
        return [
            'order_id' => 'ORD-1001',
            'type' => 'invoice',
            'type_label' => 'Invoice',
            'filename' => 'invoice.pdf',
            'url' => 'https://storage.biji.local/orders/invoice.pdf',
            'uploaded_at' => now(),
        ];
    }
}
