<?php

declare(strict_types=1);

namespace App\Services\Ask;

use App\Models\Ask\AskFlow;
use App\Models\Ask\AskOption;
use App\Models\Ask\AskOptionGroup;
use App\Models\Ask\AskType;
use Illuminate\Support\Str;

class AskOptionManagementService
{
    /**
     * Create a new Ask Option Group (Section).
     *
     * @param  array{name: string, code: string, description?: ?string, input_type: string, flows?: array<int, string>, initial_options?: ?string}  $data
     */
    public function createOptionGroup(array $data): AskOptionGroup
    {
        $code = Str::slug($data['code'], '_');
        $isMultiSelect = ($data['input_type'] === 'multi_select');
        $flows = $data['flows'] ?? ['collaboration'];

        $maxSortOrder = (int) AskOptionGroup::query()->max('sort_order');

        $group = AskOptionGroup::create([
            'name' => trim($data['name']),
            'code' => $code,
            'description' => $data['description'] ?? null,
            'input_type' => $data['input_type'],
            'is_multi_select' => $isMultiSelect,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => $maxSortOrder + 1,
            'metadata' => ['flows' => $flows],
        ]);

        // If initial options were provided as comma-separated or newline-separated string
        if (! empty($data['initial_options'])) {
            $lines = preg_split('/[\r\n,]+/', (string) $data['initial_options']);
            $sortIdx = 1;
            foreach ($lines as $line) {
                $label = trim((string) $line);
                if ($label !== '') {
                    $optCode = Str::slug($label, '_');
                    AskOption::create([
                        'option_group_id' => $group->id,
                        'code' => $optCode,
                        'label' => $label,
                        'sort_order' => $sortIdx++,
                        'is_active' => true,
                    ]);
                }
            }
        }

        return $group->load('options');
    }

    /**
     * Update an Ask Option Group (Section).
     *
     * @param  array{name: string, code?: ?string, description?: ?string, input_type?: ?string, flows?: array<int, string>}  $data
     */
    public function updateOptionGroup(AskOptionGroup $group, array $data): AskOptionGroup
    {
        $payload = [
            'name' => trim($data['name']),
        ];

        if (! empty($data['code'])) {
            $payload['code'] = Str::slug($data['code'], '_');
        }

        if (isset($data['input_type'])) {
            $payload['input_type'] = $data['input_type'];
            $payload['is_multi_select'] = ($data['input_type'] === 'multi_select');
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }

        if (isset($data['flows'])) {
            $metadata = $group->metadata ?? [];
            $metadata['flows'] = $data['flows'];
            $payload['metadata'] = $metadata;
        }

        $group->update($payload);

        return $group->fresh()->load('options');
    }

    /**
     * Delete an Ask Option Group.
     */
    public function deleteOptionGroup(AskOptionGroup $group): bool
    {
        $group->options()->delete();

        return (bool) $group->delete();
    }

    /**
     * Create a new Option inside a Group.
     *
     * @param  array{option_group_id: string, label: string, code: string, description?: ?string, sort_order?: ?int, ask_type_id?: ?string, flow_id?: ?string}  $data
     */
    public function createOption(array $data): AskOption
    {
        $code = Str::slug($data['code'], '_');
        $sortOrder = $data['sort_order'] ?? null;

        if ($sortOrder === null) {
            $maxSortOrder = (int) AskOption::query()
                ->where('option_group_id', $data['option_group_id'])
                ->when(! empty($data['ask_type_id']), fn ($q) => $q->where('ask_type_id', $data['ask_type_id']))
                ->max('sort_order');
            $sortOrder = $maxSortOrder + 1;
        }

        return AskOption::create([
            'option_group_id' => $data['option_group_id'],
            'ask_type_id' => $data['ask_type_id'] ?? null,
            'flow_id' => $data['flow_id'] ?? null,
            'code' => $code,
            'label' => trim($data['label']),
            'description' => $data['description'] ?? null,
            'sort_order' => (int) $sortOrder,
            'is_active' => true,
        ]);
    }

    /**
     * Update an Option.
     *
     * @param  array{label: string, code?: ?string, ask_type_id?: ?string, description?: ?string, sort_order?: ?int}  $data
     */
    public function updateOption(AskOption $option, array $data): AskOption
    {
        $payload = [
            'label' => trim($data['label']),
        ];

        if (! empty($data['code'])) {
            $payload['code'] = Str::slug($data['code'], '_');
        }

        if (array_key_exists('ask_type_id', $data)) {
            $payload['ask_type_id'] = $data['ask_type_id'];
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }

        if (isset($data['sort_order'])) {
            $payload['sort_order'] = (int) $data['sort_order'];
        }

        $option->update($payload);

        return $option->fresh()->load('askType');
    }

    /**
     * Delete an Option.
     */
    public function deleteOption(AskOption $option): bool
    {
        return (bool) $option->delete();
    }

    /**
     * Create a new Category / Sub-Type for a Flow.
     *
     * @param  array{flow_id: string, name: string, code: string, description?: ?string, sort_order?: ?int}  $data
     */
    public function createType(array $data): AskType
    {
        $code = Str::slug($data['code'], '_');
        $maxSortOrder = (int) AskType::query()
            ->where('flow_id', $data['flow_id'])
            ->max('sort_order');

        return AskType::create([
            'flow_id' => $data['flow_id'],
            'code' => $code,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'level' => 1,
            'sort_order' => $data['sort_order'] ?? ($maxSortOrder + 1),
            'is_active' => true,
        ]);
    }

    /**
     * Update a Category / Sub-Type.
     *
     * @param  array{name: string, code?: ?string, description?: ?string, sort_order?: ?int}  $data
     */
    public function updateType(AskType $type, array $data): AskType
    {
        $payload = [
            'name' => trim($data['name']),
        ];

        if (! empty($data['code'])) {
            $payload['code'] = Str::slug($data['code'], '_');
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }

        if (isset($data['sort_order'])) {
            $payload['sort_order'] = (int) $data['sort_order'];
        }

        $type->update($payload);

        return $type->fresh();
    }

    /**
     * Delete a Category / Sub-Type.
     */
    public function deleteType(AskType $type): bool
    {
        return (bool) $type->delete();
    }

    /**
     * Update a Flow.
     *
     * @param  array{name: string, description?: ?string}  $data
     */
    public function updateFlow(AskFlow $flow, array $data): AskFlow
    {
        $flow->update([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? $flow->description,
        ]);

        return $flow->fresh();
    }
}
