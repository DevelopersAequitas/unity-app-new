@php
    $currentRoute = request()->route()?->getName() ?? '';
    $rbacNavItems = [
        ['label' => 'Permission Matrix', 'route' => 'admin.rbac.permission-matrix.index', 'icon' => 'bi-grid-3x3-gap', 'match' => 'admin.rbac.permission-matrix.*'],
        ['label' => 'Module Access', 'route' => 'admin.rbac.module-access.index', 'icon' => 'bi-eye', 'match' => 'admin.rbac.module-access.*'],
        ['label' => 'Modules', 'route' => 'admin.rbac.modules.index', 'icon' => 'bi-box', 'match' => 'admin.rbac.modules.*'],
        ['label' => 'Pages', 'route' => 'admin.rbac.pages.index', 'icon' => 'bi-file-earmark', 'match' => 'admin.rbac.pages.*'],
        ['label' => 'Page Groups', 'route' => 'admin.rbac.page-groups.index', 'icon' => 'bi-collection', 'match' => 'admin.rbac.page-groups.*'],
        ['label' => 'Data Scope', 'route' => 'admin.rbac.data-scope.index', 'icon' => 'bi-funnel', 'match' => 'admin.rbac.data-scope.*'],
        ['label' => 'Workflows', 'route' => 'admin.rbac.workflow-rules.index', 'icon' => 'bi-diagram-3', 'match' => 'admin.rbac.workflow-rules.*'],
        ['label' => 'Role Hierarchy', 'route' => 'admin.rbac.hierarchy', 'icon' => 'bi-tree', 'match' => 'admin.rbac.hierarchy*'],
        ['label' => 'Role History', 'route' => 'admin.rbac.lifespan.index', 'icon' => 'bi-clock-history', 'match' => 'admin.rbac.lifespan.*'],
    ];
@endphp

<div class="d-flex flex-wrap gap-2">
    @foreach($rbacNavItems as $item)
        @php
            $isActive = request()->routeIs($item['match']);
        @endphp
        <a href="{{ route($item['route']) }}"
           class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }}">
            <i class="{{ $item['icon'] }} me-1"></i>{{ $item['label'] }}
        </a>
    @endforeach
</div>
