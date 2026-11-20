<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('category_id') && empty($this->category_id)) {
            $this->merge(['category_id' => null]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'category_id' => 'nullable|uuid|exists:store_categories,id',
            'sku' => "sometimes|required|string|max:120|unique:products,sku,{$id}",
            'type' => 'nullable|string|in:PHYSICAL,DIGITAL,COURSE,SUBSCRIPTION,MEMBERSHIP',
            'name' => 'sometimes|required|string|max:300',
            'slug' => "sometimes|required|string|max:300|unique:products,slug,{$id}",
            'image_url' => 'nullable|string',
            'images' => 'nullable|array',
            'variants' => 'nullable|array',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'price_coins' => 'sometimes|required|integer|min:0',
            'compare_coin_price' => 'nullable|integer|min:0',
            'unit_cost_inr' => 'nullable|numeric|min:0',
            'delivery_modes' => 'nullable|array',
            'delivery_modes.*' => 'string|in:DELIVERY,PICKUP,DIGITAL,ONLINE,COURIER',
            'min_quantity_per_order' => 'nullable|integer|min:1',
            'max_quantity_per_order' => 'nullable|integer|min:1|gte:min_quantity_per_order',
            'max_quantity_per_peer_month' => 'nullable|integer|min:1',
            'eligibility' => 'nullable|array',
            'return_allowed' => 'nullable|boolean',
            'customised' => 'nullable|boolean',
            'stock_qty' => 'nullable|integer|min:0',
            'track_inventory' => 'nullable|boolean',
            'allow_backorder' => 'nullable|boolean',
            'weight_grams' => 'nullable|integer|min:0',
            'meta_title' => 'nullable|string|max:300',
            'meta_description' => 'nullable|string',
            'status' => 'nullable|string|in:DRAFT,ACTIVE,INACTIVE,ARCHIVED',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'subscription_duration_months' => 'nullable|integer|min:1',
            'membership_duration' => 'nullable|integer|min:1',
        ];
    }
}
