<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Controller;
use App\Models\AppBrandingSetting;
use App\Models\AppDashboardWidget;
use App\Models\AppFeatureToggle;
use App\Models\AppLabel;
use App\Models\AppMembershipLabel;
use App\Models\AppNavigationItem;
use App\Models\AppSocialLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppConfigAdminController extends Controller
{
    public function index(): JsonResponse { return $this->ok(['branding'=>AppBrandingSetting::query()->first(),'labels'=>AppLabel::query()->orderBy('label_key')->get(),'features'=>AppFeatureToggle::query()->orderBy('sort_order')->get(),'navigation'=>AppNavigationItem::query()->orderBy('menu_type')->orderBy('sort_order')->get(),'dashboard_widgets'=>AppDashboardWidget::query()->orderBy('sort_order')->get(),'social_links'=>AppSocialLink::query()->orderBy('sort_order')->get(),'membership_labels'=>AppMembershipLabel::query()->orderBy('membership_key')->get()], 'App configuration fetched successfully.'); }
    public function updateBranding(Request $r): JsonResponse { $data=$r->validate($this->brandingRules()); $m=AppBrandingSetting::query()->firstOrCreate(['app_key'=>'greenpreneur']); $m->update($data); return $this->changed($m,'Branding updated successfully.'); }
    public function updateLabel(Request $r,string $label_key): JsonResponse { $data=$r->validate(['label_value'=>'required|string','group_name'=>'nullable|string|max:100','description'=>'nullable|string']); $m=AppLabel::updateOrCreate(['label_key'=>$label_key],$data+['is_active'=>true]); return $this->changed($m,'Label updated successfully.'); }
    public function bulkUpdateLabels(Request $r): JsonResponse { $data=$r->validate(['labels'=>'required|array','labels.*'=>'required|string']); foreach($data['labels'] as $k=>$v) AppLabel::updateOrCreate(['label_key'=>$k],['label_value'=>$v,'is_active'=>true]); return $this->changed(null,'Labels updated successfully.'); }
    public function updateFeature(Request $r,string $feature_key): JsonResponse { $data=$r->validate(['is_enabled'=>'required|boolean']); $m=AppFeatureToggle::where('feature_key',$feature_key)->firstOrFail(); $m->update($data); return $this->changed($m,'Feature toggle updated successfully.'); }
    public function bulkUpdateFeatures(Request $r): JsonResponse { $data=$r->validate(['features'=>'required|array','features.*'=>'required|boolean']); foreach($data['features'] as $k=>$v) AppFeatureToggle::where('feature_key',$k)->update(['is_enabled'=>$v]); return $this->changed(null,'Feature toggles updated successfully.'); }
    public function updateNavigation(Request $r,string $id): JsonResponse { $data=$r->validate(['display_label'=>'sometimes|required|string|max:255','icon'=>'nullable|string|max:100','route_name'=>'nullable|string|max:255','feature_key'=>'nullable|string|max:255','is_enabled'=>'sometimes|required|boolean','sort_order'=>'sometimes|required|integer']); $m=AppNavigationItem::findOrFail($id); $m->update($data); return $this->changed($m,'Navigation item updated successfully.'); }
    public function updateDashboardWidget(Request $r,string $widget_key): JsonResponse { $data=$r->validate(['is_enabled'=>'required|boolean','sort_order'=>'sometimes|required|integer']); $m=AppDashboardWidget::where('widget_key',$widget_key)->firstOrFail(); $m->update($data); return $this->changed($m,'Dashboard widget updated successfully.'); }
    public function updateSocialLink(Request $r,string $platform): JsonResponse { $data=$r->validate(['display_name'=>'sometimes|required|string|max:255','url'=>'nullable|url','icon'=>'nullable|string|max:100','is_enabled'=>'sometimes|required|boolean','sort_order'=>'sometimes|required|integer']); $m=AppSocialLink::where('platform',$platform)->firstOrFail(); $m->update($data); return $this->changed($m,'Social link updated successfully.'); }
    public function updateMembershipLabel(Request $r,string $membership_key): JsonResponse { $data=$r->validate(['display_label'=>'required|string|max:255','description'=>'nullable|string']); $m=AppMembershipLabel::where('membership_key',$membership_key)->firstOrFail(); $m->update($data); return $this->changed($m,'Membership label updated successfully.'); }
    public function clearCache(): JsonResponse { AppConfigController::clearCache(); return $this->ok(null,'App configuration cache cleared successfully.'); }

    private function brandingRules(): array { $hex=['nullable','regex:/^#[0-9A-Fa-f]{6}$/']; return ['app_name'=>'sometimes|required|string|max:255','app_logo_url'=>'nullable|url','splash_logo_url'=>'nullable|url','primary_color'=>$hex,'secondary_color'=>$hex,'accent_color'=>$hex,'splash_bg_color'=>$hex,'button_color'=>$hex,'text_color'=>$hex,'playstore_url'=>'nullable|url','appstore_url'=>'nullable|url','website_url'=>'nullable|url','support_email'=>'nullable|email|max:255','support_phone'=>'nullable|string|max:50']; }
    private function changed($data,string $message): JsonResponse { AppConfigController::clearCache(); return $this->ok($data,$message); }
    private function ok($data,string $message): JsonResponse { return response()->json(['success'=>true,'message'=>$message,'data'=>$data]); }
}
