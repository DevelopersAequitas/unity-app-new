<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminCreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->slug) && !empty($this->name)) {
            $this->merge([
                'slug' => Str::slug($this->name) . '-' . substr(uniqid(), -5),
            ]);
        } elseif (!empty($this->slug)) {
            $baseSlug = Str::slug($this->slug);
            $exists = DB::table('products')->where('slug', $baseSlug)->exists();
            if ($exists) {
                $this->merge([
                    'slug' => $baseSlug . '-' . substr(uniqid(), -5),
                ]);
            } else {
                $this->merge(['slug' => $baseSlug]);
            }
        }
        if (empty($this->sku) && !empty($this->name)) {
            $this->merge([
                'sku' => 'SKU-' . strtoupper(substr(uniqid(), -6)),
            ]);
        }
        if ($this->has('category_id') && empty($this->category_id)) {
            $this->merge(['category_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => 'nullable|uuid|exists:store_categories,id',
            'sku' => 'required|string|max:120|unique:products,sku',
            'type' => 'nullable|string|in:PHYSICAL,DIGITAL,COURSE,SUBSCRIPTION,MEMBERSHIP',
            'name' => 'required|string|max:300',
            'slug' => 'required|string|max:300|unique:products,slug',
            'image_url' => 'nullable|string',
            'images' => 'nullable|array',
            'variants' => 'nullable|array',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'price_coins' => 'required|integer|min:0',
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
