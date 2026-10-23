use App\Models\ImpactGuideline;
echo 'DB Guidelines: ' . ImpactGuideline::count() . PHP_EOL;
foreach (ImpactGuideline::ordered()->get() as \) {
    echo \->display_order . ' | ' . \->action . ' | ' . \->category . ' | ' . (\->is_active ? 'active' : 'inactive') . PHP_EOL;
}
