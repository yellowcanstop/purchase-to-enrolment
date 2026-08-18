<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OrderCompletedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // middleware already did auth
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required'],
            'billing.email' => ['required', 'email'],
            // {order 1001, line_items: [SKU-42, SKU-99]}
            // one order can mean multiple course enrolments
            // so enrolment_requests is a separate table keyed on order_id:product_id rather than one row per webhook
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.product_id' => ['required']
        ];
    }
}
