@php
    $currentRoute = request()->route()?->getName() ?? '';
    $rbacNavItems = [
        ['label' => 'Permission Matrix', 'route' => 'admin.rbac.permission-matrix.index', 'icon' => 'bi-grid-3x3-gap-fill', 'match' => 'admin.rbac.permission-matrix.*'],
        ['label' => 'Module Access', 'route' => 'admin.rbac.module-access.index', 'icon' => 'bi-eye', 'match' => 'admin.rbac.module-access.*'],
        ['label' => 'Modules', 'route' => 'admin.rbac.modules.index', 'icon' => 'bi-box-seam', 'match' => 'admin.rbac.modules.*'],
        ['label' => 'Pages', 'route' => 'admin.rbac.pages.index', 'icon' => 'bi-file-earmark', 'match' => 'admin.rbac.pages.*'],
        ['label' => 'Page Groups', 'route' => 'admin.rbac.page-groups.index', 'icon' => 'bi-collection', 'match' => 'admin.rbac.page-groups.*'],
        ['label' => 'Data Scope', 'route' => 'admin.rbac.data-scope.index', 'icon' => 'bi-funnel', 'match' => 'admin.rbac.data-scope.*'],
        ['label' => 'Workflows', 'route' => 'admin.rbac.workflow-rules.index', 'icon' => 'bi-diagram-3', 'match' => 'admin.rbac.workflow-rules.*'],
        ['label' => 'Role Hierarchy', 'route' => 'admin.rbac.hierarchy', 'icon' => 'bi-tree', 'match' => 'admin.rbac.hierarchy*'],
        ['label' => 'Role History', 'route' => 'admin.rbac.lifespan.index', 'icon' => 'bi-clock-history', 'match' => 'admin.rbac.lifespan.*'],
    ];
@endphp

<div class="d-flex flex-wrap align-items-center gap-2 rbac-pill-nav">
    @foreach($rbacNavItems as $item)
        @php
            $isActive = request()->routeIs($item['match']);
        @endphp
        <a href="{{ route($item['route']) }}"
           class="rbac-nav-pill text-decoration-none px-3 py-2 d-inline-flex align-items-center gap-2 {{ $isActive ? 'active' : '' }}">
            <i class="{{ $item['icon'] }} {{ $isActive ? 'text-white' : 'text-slate-500' }}"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</div>

<style>
.rbac-pill-nav .rbac-nav-pill {
    border-radius: 12px;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #334155;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}
.rbac-pill-nav .rbac-nav-pill:hover {
    background-color: #f1f5f9;
    border-color: #cbd5e1;
    color: #0f172a;
    transform: translateY(-1px);
}
.rbac-pill-nav .rbac-nav-pill.active {
    background: linear-gradient(135deg, #5850ec 0%, #6366f1 100%);
    border-color: transparent;
    color: #ffffff !important;
    box-shadow: 0 4px 14px rgba(88, 80, 236, 0.35);
}
.rbac-pill-nav .rbac-nav-pill.active:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(88, 80, 236, 0.45);
}
.text-slate-500 {
    color: #64748b;
}
</style>
