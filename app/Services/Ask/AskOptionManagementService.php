<?php

declare(strict_types=1);

namespace App\Services\Ask;

use App\Models\Ask\AskOption;
use App\Models\Ask\AskOptionGroup;
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
     * Create a new Option inside a Group.
     *
     * @param  array{option_group_id: string, label: string, code: string, description?: ?string, sort_order?: ?int}  $data
     */
    public function createOption(array $data): AskOption
    {
        $code = Str::slug($data['code'], '_');
        $sortOrder = $data['sort_order'] ?? null;

        if ($sortOrder === null) {
            $maxSortOrder = (int) AskOption::query()
                ->where('option_group_id', $data['option_group_id'])
                ->max('sort_order');
            $sortOrder = $maxSortOrder + 1;
        }

        return AskOption::create([
            'option_group_id' => $data['option_group_id'],
            'code' => $code,
            'label' => trim($data['label']),
            'description' => $data['description'] ?? null,
            'sort_order' => (int) $sortOrder,
            'is_active' => true,
        ]);
    }
}
