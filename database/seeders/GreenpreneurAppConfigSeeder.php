<?php

namespace Database\Seeders;

use App\Models\AppBrandingSetting;
use App\Models\AppDashboardWidget;
use App\Models\AppFeatureToggle;
use App\Models\AppLabel;
use App\Models\AppMembershipLabel;
use App\Models\AppNavigationItem;
use App\Models\AppSocialLink;
use Illuminate\Database\Seeder;

class GreenpreneurAppConfigSeeder extends Seeder
{
    public function run(): void
    {
        AppBrandingSetting::updateOrCreate(['app_key' => 'greenpreneur'], [
            'app_name' => 'Greenpreneur', 'website_url' => 'https://greenpreneur.in',
            'primary_color' => '#2E7D32', 'secondary_color' => '#81C784', 'accent_color' => '#FFC107',
            'splash_bg_color' => '#FFFFFF', 'button_color' => '#2E7D32', 'text_color' => '#212121', 'is_active' => true,
        ]);

        foreach ([
            'app_name'=>'Greenpreneur','peer'=>'Green Member','peers'=>'Green Network','my_peers'=>'My Network','circle'=>'Eco-Circle','circles'=>'Eco-Circles','event'=>'Eco Event','events'=>'Eco Events','coin'=>'Green Coin','coins'=>'Green Coins','impact'=>'Green Impact','lives_impacted'=>'Impact Score','referral'=>'Green Referral','business_deal'=>'Green Deal','p2p_meeting'=>'Peer Meeting','requirement'=>'Need','post_an_ask'=>'Post a Need','visitor'=>'Guest','register_visitor'=>'Guest Pass','circular'=>'Announcement','circulars'=>'Announcements','chat'=>'Messages','leaderboard'=>'Green Leaderboard','badge'=>'Green Badge','welcome_title'=>'Welcome to the Greenpreneur Ecosystem','welcome_subtitle'=>"Join India's growing green entrepreneurship network",'register_button'=>'Join Now','login_button'=>'Login','activities_section_title'=>'GREEN ACTIONS','impact_section_title'=>'GREEN IMPACT DASHBOARD','share_message'=>"I am part of Greenpreneur, India's green entrepreneurship network. Join now and become part of the green movement.",
        ] as $key => $value) {
            AppLabel::updateOrCreate(['label_key' => $key], ['label_value' => $value, 'group_name' => $this->labelGroup($key), 'is_active' => true]);
        }

        $features = ['events'=>true,'referrals'=>true,'business_deals'=>true,'p2p_meetings'=>true,'testimonials'=>true,'requirements'=>true,'collaborations'=>true,'collaboration_ask'=>true,'visitor_registration'=>true,'add_impact'=>true,'claim_coins'=>true,'coins_wallet'=>true,'leaderboard'=>true,'impact_score'=>true,'badges'=>true,'gratitude_score'=>false,'circles'=>true,'chat_messaging'=>true,'geo_nearby'=>false,'circulars'=>true,'gallery'=>true,'videos'=>true,'meeting_schedule'=>true,'invoices'=>true,'blocked_users'=>true,'welcome_creative'=>true,'feedback'=>true,'qr_scan'=>true,'community_feed'=>true,'leadership_form'=>true,'recommend_peer'=>true,'peers'=>true];
        $i = 1; foreach ($features as $key => $enabled) AppFeatureToggle::updateOrCreate(['feature_key'=>$key], ['feature_name'=>str($key)->replace('_',' ')->title()->toString(), 'is_enabled'=>$enabled, 'sort_order'=>$i++]);

        $this->seedNavigation('bottom_nav', [['home','Feed','home',null,'community_feed',1],['my_peers','My Network','people',null,'peers',2],['impact','Green Impact','impact',null,'impact_score',3],['circle','Eco-Circle','circle',null,'circles',4],['highlights','Highlights','star',null,null,5]]);
        $this->seedNavigation('plus_menu', [['events','Eco Events',null,'events','events',1],['referrals','Green Referral',null,'referral','referrals',2],['business_deals','Green Deal',null,'business_deal','business_deals',3],['p2p_meetings','Peer Meeting',null,'p2p_meeting','p2p_meetings',4],['testimonials','Testimonial',null,null,'testimonials',5],['requirements','Post a Need',null,'post_an_ask','requirements',6],['collaborations','Find Collaboration',null,null,'collaborations',7],['collaboration_ask','Collaboration Ask',null,null,'collaboration_ask',8],['visitor_registration','Guest Pass',null,'register_visitor','visitor_registration',9],['add_impact','Log Impact',null,'impact','add_impact',10],['claim_coins','Claim Green Coins',null,'coins','claim_coins',11]]);
        $this->seedNavigation('impact_menu', [['impact_score','My Green Impact',null,'impact','impact_score',1],['badges','Green Badges',null,'badge','badges',2],['coins_wallet','My Green Coins',null,'coins','coins_wallet',3],['collaboration_history','Collaboration History',null,null,'collaborations',4],['referrals','Green Referrals',null,'referral','referrals',5],['gratitude_score','Gratitude Score',null,null,'gratitude_score',6]]);
        $this->seedNavigation('drawer', [['circulars','Announcements',null,'circulars','circulars',1],['gallery','Photo Gallery',null,null,'gallery',2],['videos','Video Library',null,null,'videos',3],['meeting_schedule','Meeting Schedule',null,null,'meeting_schedule',4],['invoices','Invoices',null,null,'invoices',5],['blocked_users','Blocked Users',null,null,'blocked_users',6],['welcome_creative','Welcome Card',null,null,'welcome_creative',7],['rate_app','Rate App',null,null,null,8],['share_app','Share App',null,null,null,9],['settings','Settings',null,null,null,10],['feedback','Feedback',null,null,'feedback',11],['logout','Logout',null,null,null,12]]);

        foreach (['banner_carousel','leaderboard_preview','impact_tracker','upcoming_events','hot_deals','membership_banner','feed_composer','circle_preview','community_feed'] as $idx => $key) AppDashboardWidget::updateOrCreate(['widget_key'=>$key], ['widget_name'=>str($key)->replace('_',' ')->title()->toString(), 'is_enabled'=>true, 'sort_order'=>$idx+1]);
        foreach ([['linkedin','LinkedIn','https://linkedin.com/company/greenpreneur','linkedin',1],['instagram','Instagram','https://instagram.com/greenpreneur','instagram',2],['facebook','Facebook','https://facebook.com/greenpreneur','facebook',3],['youtube','YouTube',null,'youtube',4],['website','Website','https://greenpreneur.in','website',5]] as $s) AppSocialLink::updateOrCreate(['platform'=>$s[0]], ['display_name'=>$s[1], 'url'=>$s[2], 'icon'=>$s[3], 'is_enabled'=>true, 'sort_order'=>$s[4]]);
        foreach (['free_peer'=>'Free Member','unity_peer'=>'Green Member','only_unity_peer'=>'Eco Member','chartered_peer'=>'Premium Green Member','charter_investor'=>'Green Investor'] as $key=>$label) AppMembershipLabel::updateOrCreate(['membership_key'=>$key], ['display_label'=>$label, 'is_enabled'=>true]);
    }

    private function seedNavigation(string $type, array $items): void { foreach ($items as $item) AppNavigationItem::updateOrCreate(['menu_type'=>$type,'item_key'=>$item[0]], ['display_label'=>$item[1], 'icon'=>$item[2], 'label_key'=>$item[3], 'feature_key'=>$item[4], 'sort_order'=>$item[5], 'is_enabled'=>true]); }
    private function labelGroup(string $key): string { return str_contains($key, 'welcome') ? 'welcome' : (str_contains($key, 'button') ? 'buttons' : 'general'); }
}
