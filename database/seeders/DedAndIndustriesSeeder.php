<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\City;
use App\Models\District;
use App\Models\Industry;
use App\Models\LeaderReport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DedAndIndustriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedOfficialIndustries();
        $this->fixCircleNamesAndAssociations();
        $this->fixAhmedabadPeersCircleMembership();
        $this->seedSampleLeaderReports();
    }

    /**
     * Seed/Sync the 18 Official Master Industries.
     */
    private function seedOfficialIndustries(): void
    {
        $officialIndustries = [
            [
                'name' => 'Technology',
                'slug' => 'technology',
                'icon_url' => 'https://api.peersunity.com/icons/technology.png',
                'sort_order' => 1,
            ],
            [
                'name' => 'Manufacturing',
                'slug' => 'manufacturing',
                'icon_url' => 'https://api.peersunity.com/icons/manufacturing.png',
                'sort_order' => 2,
            ],
            [
                'name' => 'Real Estate',
                'slug' => 'real-estate',
                'icon_url' => 'https://api.peersunity.com/icons/real-estate.png',
                'sort_order' => 3,
            ],
            [
                'name' => 'Healthcare',
                'slug' => 'healthcare',
                'icon_url' => 'https://api.peersunity.com/icons/healthcare.png',
                'sort_order' => 4,
            ],
            [
                'name' => 'Financial Services',
                'slug' => 'financial-services',
                'icon_url' => 'https://api.peersunity.com/icons/financial-services.png',
                'sort_order' => 5,
            ],
            [
                'name' => 'Education & Skill',
                'slug' => 'education-skill',
                'icon_url' => 'https://api.peersunity.com/icons/education.png',
                'sort_order' => 6,
            ],
            [
                'name' => 'Agriculture & Food',
                'slug' => 'agriculture-food',
                'icon_url' => 'https://api.peersunity.com/icons/agriculture.png',
                'sort_order' => 7,
            ],
            [
                'name' => 'Green & Sustainability',
                'slug' => 'green-sustainability',
                'icon_url' => 'https://api.peersunity.com/icons/green.png',
                'sort_order' => 8,
            ],
            [
                'name' => 'Media & Entertainment',
                'slug' => 'media-entertainment',
                'icon_url' => 'https://api.peersunity.com/icons/media.png',
                'sort_order' => 9,
            ],
            [
                'name' => 'Tourism & Hospitality',
                'slug' => 'tourism-hospitality',
                'icon_url' => 'https://api.peersunity.com/icons/tourism.png',
                'sort_order' => 10,
            ],
            [
                'name' => 'Retail & FMCG',
                'slug' => 'retail-fmcg',
                'icon_url' => 'https://api.peersunity.com/icons/retail.png',
                'sort_order' => 11,
            ],
            [
                'name' => 'Logistics & Supply Chain',
                'slug' => 'logistics-supply-chain',
                'icon_url' => 'https://api.peersunity.com/icons/logistics.png',
                'sort_order' => 12,
            ],
            [
                'name' => 'Construction & Infra',
                'slug' => 'construction-infra',
                'icon_url' => 'https://api.peersunity.com/icons/construction.png',
                'sort_order' => 13,
            ],
            [
                'name' => 'Legal & Professional',
                'slug' => 'legal-professional',
                'icon_url' => 'https://api.peersunity.com/icons/legal.png',
                'sort_order' => 14,
            ],
            [
                'name' => 'Fashion & Lifestyle',
                'slug' => 'fashion-lifestyle',
                'icon_url' => 'https://api.peersunity.com/icons/fashion.png',
                'sort_order' => 15,
            ],
            [
                'name' => 'Automotive',
                'slug' => 'automotive',
                'icon_url' => 'https://api.peersunity.com/icons/automotive.png',
                'sort_order' => 16,
            ],
            [
                'name' => 'Energy & Power',
                'slug' => 'energy-power',
                'icon_url' => 'https://api.peersunity.com/icons/energy.png',
                'sort_order' => 17,
            ],
            [
                'name' => 'Chemicals & Materials',
                'slug' => 'chemicals-materials',
                'icon_url' => 'https://api.peersunity.com/icons/chemicals.png',
                'sort_order' => 18,
            ],
        ];

        // Mapping from existing DB names to standard names
        $aliasMap = [
            'technology & digital' => 'Technology',
            'manufacturing & engineering' => 'Manufacturing',
            'real estate & infrastructure' => 'Real Estate',
            'healthcare & life sciences' => 'Healthcare',
            'finance & investment' => 'Financial Services',
            'education & skill development' => 'Education & Skill',
            'agriculture & rural enterprises' => 'Agriculture & Food',
            'sustainability & esg' => 'Green & Sustainability',
            'marketing, media & communication' => 'Media & Entertainment',
            'food, hospitality & travel' => 'Tourism & Hospitality',
            'retail & e-commerce' => 'Retail & FMCG',
            'import, export & global trade' => 'Logistics & Supply Chain',
            'creative & lifestyle' => 'Fashion & Lifestyle',
            'msme services & business support' => 'Legal & Professional',
            'professional services' => 'Legal & Professional',
            'startup ecosystem' => 'Construction & Infra',
            'ipo, corporate & large enterprise services' => 'Energy & Power',
            'women & social enterprises' => 'Chemicals & Materials',
        ];

        foreach ($officialIndustries as $ind) {
            // Find existing by exact slug or exact name or alias
            $existing = Industry::query()
                ->where('slug', $ind['slug'])
                ->orWhereRaw('LOWER(name) = ?', [strtolower($ind['name'])])
                ->first();

            if (! $existing) {
                // Check if an alias matches an unmapped existing record
                foreach ($aliasMap as $oldName => $newName) {
                    if (strtolower($newName) === strtolower($ind['name'])) {
                        $existing = Industry::query()
                            ->whereRaw('LOWER(name) = ?', [strtolower($oldName)])
                            ->first();
                        if ($existing) {
                            break;
                        }
                    }
                }
            }

            if ($existing) {
                $existing->update([
                    'name' => $ind['name'],
                    'slug' => $ind['slug'],
                    'icon_url' => $ind['icon_url'],
                    'sort_order' => $ind['sort_order'],
                    'is_active' => true,
                ]);
            } else {
                Industry::query()->create([
                    'id' => (string) Str::uuid(),
                    'name' => $ind['name'],
                    'slug' => $ind['slug'],
                    'icon_url' => $ind['icon_url'],
                    'sort_order' => $ind['sort_order'],
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * Fix Circle Names and assign Ahmedabad District & City.
     */
    private function fixCircleNamesAndAssociations(): void
    {
        $ahmedabadCity = City::query()->whereRaw("LOWER(name) = 'ahmedabad'")->first();
        $cityId = $ahmedabadCity ? (string) $ahmedabadCity->id : 'dd96919d-3666-4806-9509-bc7e196e9f6f';

        $ahmedabadDistrict = District::query()->whereRaw("LOWER(name) = 'ahmedabad'")->first();
        $districtId = $ahmedabadDistrict ? (string) $ahmedabadDistrict->id : 'bca9c723-875d-46ef-9952-1af4239bac04';

        $dedUserId = '8ef4c5ad-13c5-4b08-8e6f-cbde39df23a5'; // Dhruvil User

        // Set DED on District
        if ($ahmedabadDistrict) {
            $ahmedabadDistrict->update([
                'ded_user_id' => $dedUserId,
                'city_id' => $cityId,
                'is_active' => true,
            ]);
        }

        $circleConfigs = [
            'd06173c0-368c-4bfd-b682-e07e67fdb320' => [
                'name' => 'Ahmedabad Tech Pioneers',
                'slug' => 'ahmedabad-tech-pioneers',
                'description' => 'Premier Technology circle for SaaS founders, tech innovators, and IT leaders in Ahmedabad.',
                'purpose' => 'Fostering tech innovations, B2B deals, and cross-circle collaborations.',
                'announcement' => 'Monthly Ahmedabad Tech Pioneers Summit every 1st Saturday.',
                'industry_tags' => ['Technology', 'ind_01'],
            ],
            '799b0a88-48fa-490a-ae45-2a540aed72cd' => [
                'name' => 'Ahmedabad MSME Growth Circle',
                'slug' => 'ahmedabad-msme-growth-circle',
                'description' => 'Dedicated circle for MSME growth, manufacturing excellence, and supply chain enterprises in Ahmedabad.',
                'purpose' => 'Accelerating MSME scaling and manufacturing partnerships.',
                'announcement' => 'MSME Vendor Meet scheduled for next week.',
                'industry_tags' => ['Manufacturing', 'ind_02'],
            ],
            '3021d5b4-4b63-478e-ae26-ef1bf897ccf4' => [
                'name' => 'Ahmedabad Business Circle',
                'slug' => 'ahmedabad-business-circle',
                'description' => 'Flagship business leadership circle connecting finance, investments, and enterprise leaders.',
                'purpose' => 'Promoting peer-to-peer business networking and high-value business deals.',
                'announcement' => 'Annual Business Leaders Assembly registration open.',
                'industry_tags' => ['Financial Services', 'Real Estate', 'ind_05', 'ind_03'],
            ],
            'd9cf253e-8b72-478a-a6be-8ccaeb362bbd' => [
                'name' => 'Satellite Business Circle',
                'slug' => 'satellite-business-circle',
                'description' => 'High-growth professional business circle located in Satellite, Ahmedabad.',
                'purpose' => 'Local business synergy and healthcare / professional collaboration.',
                'announcement' => 'Weekly breakfast networking session at Satellite Club.',
                'industry_tags' => ['Healthcare', 'Technology', 'ind_04', 'ind_01'],
            ],
        ];

        foreach ($circleConfigs as $circleId => $config) {
            $circle = Circle::query()->where('id', $circleId)->first();
            if ($circle) {
                $circle->update([
                    'name' => $config['name'],
                    'slug' => $config['slug'],
                    'description' => $config['description'],
                    'purpose' => $config['purpose'],
                    'announcement' => $config['announcement'],
                    'city_id' => $cityId,
                    'district_id' => $districtId,
                    'ded_user_id' => $dedUserId,
                    'industry_tags' => $config['industry_tags'],
                    'status' => 'active',
                ]);
            }
        }
    }

    /**
     * Fix Peer circle memberships so all Ahmedabad peers link to active Ahmedabad circles.
     */
    private function fixAhmedabadPeersCircleMembership(): void
    {
        $ahmedabadCircleIds = [
            'd06173c0-368c-4bfd-b682-e07e67fdb320', // Ahmedabad Tech Pioneers
            '799b0a88-48fa-490a-ae45-2a540aed72cd', // Ahmedabad MSME Growth Circle
            '3021d5b4-4b63-478e-ae26-ef1bf897ccf4', // Ahmedabad Business Circle
            'd9cf253e-8b72-478a-a6be-8ccaeb362bbd', // Satellite Business Circle
        ];

        $ahmedabadUsers = User::query()
            ->whereRaw("LOWER(city) LIKE '%ahmedabad%'")
            ->orWhereRaw("LOWER(city_of_residence) LIKE '%ahmedabad%'")
            ->get();

        foreach ($ahmedabadUsers as $idx => $user) {
            $assignedCircleId = $ahmedabadCircleIds[$idx % count($ahmedabadCircleIds)];

            // Ensure circle member record exists
            $existing = CircleMember::query()
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $existing->update([
                    'circle_id' => $assignedCircleId,
                    'status' => 'approved',
                ]);
            } else {
                CircleMember::query()->create([
                    'id' => (string) Str::uuid(),
                    'circle_id' => $assignedCircleId,
                    'user_id' => $user->id,
                    'status' => 'approved',
                    'role' => 'peer',
                    'joined_at' => now()->subDays(random_int(10, 100)),
                ]);
            }

            // Update user active_circle_id
            $user->update([
                'active_circle_id' => $assignedCircleId,
            ]);
        }
    }

    /**
     * Seed sample LeaderReports linked to Ahmedabad circles.
     */
    private function seedSampleLeaderReports(): void
    {
        $reportsCount = LeaderReport::query()->count();
        if ($reportsCount > 0) {
            return;
        }

        $sampleReports = [
            [
                'id' => (string) Str::uuid(),
                'circle_id' => 'd06173c0-368c-4bfd-b682-e07e67fdb320', // Ahmedabad Tech Pioneers
                'submitted_by_user_id' => '8ef4c5ad-13c5-4b08-8e6f-cbde39df23a5',
                'report_type' => 'Monthly',
                'period' => 'July 2026',
                'attendance_percentage' => 94.5,
                'deals_closed_value' => '₹28.4L',
                'summary_text' => 'Outstanding monthly performance with 14 active tech collaborations and high peer attendance.',
                'content' => 'The Ahmedabad Tech Pioneers circle demonstrated strong member engagement in July 2026. Key achievements include 8 closed cross-circle deals and 100% active member participation.',
                'status' => 'Approved',
            ],
            [
                'id' => (string) Str::uuid(),
                'circle_id' => '799b0a88-48fa-490a-ae45-2a540aed72cd', // Ahmedabad MSME Growth Circle
                'submitted_by_user_id' => '8ef4c5ad-13c5-4b08-8e6f-cbde39df23a5',
                'report_type' => 'Monthly',
                'period' => 'July 2026',
                'attendance_percentage' => 91.0,
                'deals_closed_value' => '₹19.2L',
                'summary_text' => 'Solid manufacturing sector deal flow and 6 new vendor linkages established.',
                'content' => 'The MSME Growth Circle completed its quarterly supply chain review with strong positive feedback.',
                'status' => 'Approved',
            ],
            [
                'id' => (string) Str::uuid(),
                'circle_id' => '3021d5b4-4b63-478e-ae26-ef1bf897ccf4', // Ahmedabad Business Circle
                'submitted_by_user_id' => '8ef4c5ad-13c5-4b08-8e6f-cbde39df23a5',
                'report_type' => 'Quarterly',
                'period' => 'Q2 2026',
                'attendance_percentage' => 88.5,
                'deals_closed_value' => '₹45.0L',
                'summary_text' => 'Q2 performance report reflecting enterprise partnerships and high referral activity.',
                'content' => 'Ahmedabad Business Circle completed a record quarter with substantial revenue generation.',
                'status' => 'Approved',
            ],
        ];

        foreach ($sampleReports as $reportData) {
            LeaderReport::query()->create($reportData);
        }
    }
}
