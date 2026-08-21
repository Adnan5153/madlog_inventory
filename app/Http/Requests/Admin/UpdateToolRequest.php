<?php

namespace App\Http\Requests\Admin;

use App\Enums\ToolCondition;
use App\Enums\ToolStatus;
use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateToolRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('tool')) ?? false;
    }

    public function rules(): array
    {
        $tool = $this->route('tool');
        $workshopId = $this->effectiveWorkshopId();

        return [
            'workshop_id' => $this->workshopRule(),
            'tool_code' => [
                'required', 'string', 'max:64',
                Rule::unique('tools', 'tool_code')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at')
                    ->ignore($tool->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('tool_categories', 'id')->where('workshop_id', $workshopId),
            ],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => [
                'nullable', 'string', 'max:64',
                Rule::unique('tools', 'serial_number')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at')
                    ->ignore($tool->id),
            ],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('tools', 'barcode')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at')
                    ->ignore($tool->id),
            ],
            'qr_code' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'condition' => ['required', 'string', 'in:'.implode(',', ToolCondition::values())],
            'status' => ['required', 'string', 'in:'.implode(',', ToolStatus::values())],
            'current_holder_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('workshop_id', $workshopId),
            ],
            'is_active' => ['required', 'boolean'],
            'bin_id' => [
                'nullable', 'integer',
                Rule::exists('bin_locations', 'id')->where('workshop_id', $workshopId),
            ],
            'supplier_id' => [
                'nullable', 'integer',
                Rule::exists('suppliers', 'id')->where('workshop_id', $workshopId),
            ],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'warranty_expiry' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->lockWorkshopFromRouteModel('tool');
    }
}
