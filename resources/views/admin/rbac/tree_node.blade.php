@php
    $hasRenderedSubtree = in_array($role->id, $GLOBALS['rendered_subtrees'] ?? [], true);
    if (!$hasRenderedSubtree && !empty($parentToChildren[$role->id])) {
        $GLOBALS['rendered_subtrees'][] = $role->id;
    }

    // Pick icon based on role key or type
    $iconMap = [
        'global_founder'    => 'bi-star-fill',
        'global_admin'      => 'bi-shield-fill',
        'ded'               => 'bi-building',
        'id'                => 'bi-briefcase-fill',
        'industry_director' => 'bi-briefcase-fill',
        'Circle Director'   => 'bi-circle-fill',
        'Circle Founder'    => 'bi-award-fill',
        'Circle_Chair'      => 'bi-person-badge-fill',
        'cd'                => 'bi-circle-fill',
        'cf'                => 'bi-circle',
        'chair'             => 'bi-person-badge-fill',
        'vice_chair'        => 'bi-person-badge',
        'secretary'         => 'bi-journal-text',
        'member'            => 'bi-person-fill',
        'user'              => 'bi-person',
    ];
    $roleIcon = $iconMap[$role->key] ?? match($role->role_type) {
        'system' => 'bi-shield-lock-fill',
        'admin'  => 'bi-person-gear',
        default  => 'bi-person',
    };

    $currentParentIds = collect($childToParents[$role->id] ?? [])->toArray();
@endphp
<li>
    @php $isRoot = empty($childToParents[$role->id] ?? []); @endphp
    <div class="node-box node-box-interactive {{ $isRoot ? 'node-root' : '' }}" data-role-id="{{ $role->id }}"
         onclick="if(!event.target.closest('.node-action-btn')) openAssignmentsModal('{{ $role->id }}', {{ json_encode($role->name) }}, {{ json_encode($role->key) }}, {{ json_encode($role->scope_rule) }})">
        {{-- Role icon --}}
        <div class="node-icon">
            <i class="bi {{ $roleIcon }}"></i>
        </div>
        <div class="node-title">{{ $role->name }}</div>
        <div class="node-meta">{{ $role->key }}</div>
        <span class="node-badge badge-{{ $role->role_type ?: 'user' }}">
            {{ ucfirst($role->role_type ?: 'user') }}
        </span>

        {{-- Action buttons (appear on hover) --}}
        <div class="node-actions">
            <button type="button" class="node-action-btn node-edit-btn"
                title="Edit Role"
                onclick="openEditModal(
                    '{{ $role->id }}',
                    {{ json_encode($role->name) }},
                    {{ json_encode($role->key) }},
                    {{ json_encode($role->role_type) }},
                    {{ json_encode($role->scope_rule) }},
                    {{ json_encode($role->description) }},
                    {{ json_encode($currentParentIds) }}
                )">
                <i class="bi bi-pencil-fill"></i>
            </button>
            <button type="button" class="node-action-btn node-delete-btn"
                title="Delete Role"
                onclick="openDeleteModal('{{ $role->id }}', {{ json_encode($role->name) }})">
                <i class="bi bi-trash3-fill"></i>
            </button>
        </div>
    </div>

    @if(!empty($parentToChildren[$role->id]))
        @if(!$hasRenderedSubtree)
            <ul>
                @foreach($parentToChildren[$role->id] as $childId)
                    @php $childRole = $roles->firstWhere('id', $childId); @endphp
                    @if($childRole)
                        @include('admin.rbac.tree_node', ['role' => $childRole])
                    @endif
                @endforeach
            </ul>
        @else
            <div style="margin-top:12px;">
                <span class="subtree-link-badge">
                    <i class="bi bi-link-45deg"></i> Subtree linked above
                </span>
            </div>
        @endif
    @endif
</li>

