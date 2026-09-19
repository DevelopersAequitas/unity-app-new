<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Web\WebBlog;
use App\Models\Web\WebCompany;
use App\Models\Web\WebOpportunity;
use App\Models\Web\WebPageMedia;
use App\Models\Web\WebPartnership;
use App\Models\Web\WebSetting;
use Illuminate\Database\Seeder;

class PeersGlobalWebSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Partnerships
        if (WebPartnership::count() === 0) {
            $partnerships = [
                [
                    'code' => 'PTS-101',
                    'title' => 'Cross-Border Supply Chain AI & Freight Integration',
                    'company_a' => 'Apex Logistics',
                    'company_b' => 'Zen Cloud Solutions',
                    'sector' => 'Supply & Logistics Tech',
                    'route' => 'Mumbai ↔ Bengaluru',
                    'value' => '₹ 4.2 Cr',
                    'numeric_value' => 4.20,
                    'status' => 'Active',
                    'stage' => 'Active Execution',
                    'progress_percent' => 90,
                    'avatar_a' => 'AL',
                    'avatar_b' => 'ZC',
                    'signed_date' => '2026-08-14',
                    'description' => 'Unified automated warehouse manifest dispatch with live customs clearance synchronization across West and South India trade hubs.',
                    'synergies' => ['99.4% SLA Automated Dispatch', 'Zero-latency customs broker API', '140+ Fleet integrations'],
                    'lead_manager' => 'Vikram Singhania',
                ],
                [
                    'code' => 'PTS-102',
                    'title' => 'Solar & Renewable Micro-Grid Infrastructure JV',
                    'company_a' => 'EcoPower Tech Hub',
                    'company_b' => 'Horizon Polymers Ltd',
                    'sector' => 'Clean Energy & Materials',
                    'route' => 'Ahmedabad ↔ Pune',
                    'value' => '₹ 12.8 Cr',
                    'numeric_value' => 12.80,
                    'status' => 'Active',
                    'stage' => 'Active Execution',
                    'progress_percent' => 85,
                    'avatar_a' => 'EP',
                    'avatar_b' => 'HP',
                    'signed_date' => '2026-07-28',
                    'description' => 'Joint development of captive rooftop solar installations and recycled composite battery enclosures for Tier-2 industrial estates.',
                    'synergies' => ['28 MW Clean energy generation', 'State PLI capital subsidy qualified', 'Direct EPC syndication'],
                    'lead_manager' => 'Ananya Sharma',
                ],
                [
                    'code' => 'PTS-103',
                    'title' => 'Healthcare Cold-Chain IoT Diagnostic Corridor',
                    'company_a' => 'Nexus Health Biotech',
                    'company_b' => 'AeroGlobal Logistics',
                    'sector' => 'Healthcare & Life Sciences',
                    'route' => 'Hyderabad ↔ Delhi NCR',
                    'value' => '₹ 6.5 Cr',
                    'numeric_value' => 6.50,
                    'status' => 'Under Review',
                    'stage' => 'Due Diligence',
                    'progress_percent' => 45,
                    'avatar_a' => 'NH',
                    'avatar_b' => 'AG',
                    'signed_date' => '2026-09-02',
                    'description' => 'Sub-zero real-time temperature tracking for biologics and blood plasma shipments across Tier-1 airports.',
                    'synergies' => ['Continuous sensor telemetry', 'FDA compliant cold room custody', 'Same-day turnaround'],
                    'lead_manager' => 'Rajesh K. Mehta',
                ],
                [
                    'code' => 'PTS-104',
                    'title' => 'Agri-Commodities Direct Trade & Processing Corridor',
                    'company_a' => 'KisanSetu Organics',
                    'company_b' => 'TerraFirma Foods Group',
                    'sector' => 'AgriTech & Food Processing',
                    'route' => 'Indore ↔ Surat',
                    'value' => '₹ 8.1 Cr',
                    'numeric_value' => 8.10,
                    'status' => 'Negotiation',
                    'stage' => 'MOU Drafting',
                    'progress_percent' => 60,
                    'avatar_a' => 'KS',
                    'avatar_b' => 'TF',
                    'signed_date' => '2026-08-30',
                    'description' => 'Direct farm-gate aggregation and export-grade vacuum packing for organic pulses and non-GMO oilseeds.',
                    'synergies' => ['12,000+ Cultivator network', 'Zero middleman leakage', 'APEDA accredited quality control'],
                    'lead_manager' => 'Pradeep Joshi',
                ],
            ];

            foreach ($partnerships as $p) {
                WebPartnership::create($p);
            }
        }

        // 2. Seed Opportunities
        if (WebOpportunity::count() === 0) {
            $opportunities = [
                [
                    'code' => 'OPP-501',
                    'title' => '₹3.5 Cr Cross-Border SaaS Supply Contract',
                    'sector' => 'Enterprise Software & Cloud',
                    'value' => '₹ 3.5 Cr',
                    'location' => 'Bengaluru, India',
                    'status' => 'Active',
                    'description' => 'Seeking an established enterprise cloud provider for building a multi-tenant logistics ERP.',
                    'requirements' => ['SOC-2 compliance', 'Microservices architecture', 'Kafka event streaming'],
                    'tags' => ['SaaS', 'Cloud', 'Enterprise'],
                    'proposer_name' => 'Rahul Singhal',
                    'proposer_company' => 'Apex Cloud Works',
                    'deadline' => '2026-10-30',
                ],
                [
                    'code' => 'OPP-502',
                    'title' => 'Industrial Rooftop Solar 5MW Turnkey EPC',
                    'sector' => 'Renewable Energy',
                    'value' => '₹ 7.8 Cr',
                    'location' => 'Sanand, Gujarat',
                    'status' => 'Active',
                    'description' => 'Turnkey installation requirement for 5MW rooftop solar plant with 10-year O&M contract.',
                    'requirements' => ['Tier-1 Solar Panels', 'CEA guidelines compliance', 'Zero export protection'],
                    'tags' => ['Solar', 'CleanTech', 'EPC'],
                    'proposer_name' => 'Bhavin Patel',
                    'proposer_company' => 'Gujarat Clean Energy Corp',
                    'deadline' => '2026-11-15',
                ],
                [
                    'code' => 'OPP-503',
                    'title' => 'Automated Cold Storage Facility JV Partner',
                    'sector' => 'Warehousing & Cold Chain',
                    'value' => '₹ 15.0 Cr',
                    'location' => 'Bhiwandi, Maharashtra',
                    'status' => 'Under Review',
                    'description' => 'Co-investment opportunity for building a 10,000 pallet automated cold storage warehouse.',
                    'requirements' => ['Land parcel ready', 'Joint Venture structure', 'Pharma grade specifications'],
                    'tags' => ['ColdChain', 'Infrastructure', 'JV'],
                    'proposer_name' => 'Karan Malhotra',
                    'proposer_company' => 'Vanguard Cold Logistics',
                    'deadline' => '2026-12-01',
                ],
            ];

            foreach ($opportunities as $opp) {
                WebOpportunity::create($opp);
            }
        }

        // 3. Seed Companies
        if (WebCompany::count() === 0) {
            $companies = [
                [
                    'name' => 'Apex Logistics',
                    'industry' => 'Supply Chain & Logistics',
                    'sector' => 'Freight Tech',
                    'location' => 'Mumbai',
                    'city' => 'Mumbai',
                    'website' => 'https://apexlogistics.in',
                    'employee_count' => '250-500',
                    'turnover' => '₹ 85 Cr',
                    'description' => 'Pan-India automated third-party logistics and multi-modal freight forwarding powerhouse.',
                    'is_verified' => true,
                    'status' => 'active',
                ],
                [
                    'name' => 'Zen Cloud Solutions',
                    'industry' => 'Information Technology',
                    'sector' => 'Enterprise Cloud',
                    'location' => 'Bengaluru',
                    'city' => 'Bengaluru',
                    'website' => 'https://zencloudsolutions.io',
                    'employee_count' => '100-250',
                    'turnover' => '₹ 45 Cr',
                    'description' => 'Cloud native software development, API integrations, and artificial intelligence workflow automation.',
                    'is_verified' => true,
                    'status' => 'active',
                ],
                [
                    'name' => 'EcoPower Technologies',
                    'industry' => 'Renewable Energy',
                    'sector' => 'CleanTech',
                    'location' => 'Ahmedabad',
                    'city' => 'Ahmedabad',
                    'website' => 'https://ecopowertech.com',
                    'employee_count' => '50-100',
                    'turnover' => '₹ 62 Cr',
                    'description' => 'Commercial and industrial distributed solar engineering, procurement, and EPC development.',
                    'is_verified' => true,
                    'status' => 'active',
                ],
                [
                    'name' => 'Horizon Polymers Ltd',
                    'industry' => 'Manufacturing & Materials',
                    'sector' => 'Advanced Polymers',
                    'location' => 'Pune',
                    'city' => 'Pune',
                    'website' => 'https://horizonpolymers.com',
                    'employee_count' => '500-1000',
                    'turnover' => '₹ 140 Cr',
                    'description' => 'High-performance recycled composite polymers for automotive and industrial engineering.',
                    'is_verified' => true,
                    'status' => 'active',
                ],
            ];

            foreach ($companies as $comp) {
                WebCompany::create($comp);
            }
        }

        // 4. Seed Blogs / Publications
        if (WebBlog::count() === 0) {
            $blogs = [
                [
                    'title' => 'The Rise of Cross-Border Peer Collaboration in India',
                    'slug' => 'rise-of-cross-border-peer-collaboration-in-india',
                    'excerpt' => 'How medium and large enterprise promoters are unlocking 10x scale through structured bilateral alliances instead of venture dilution.',
                    'content' => '<p>In today rapidly evolving economic landscape, business promoters are realizing that trust-based peer networks outperform traditional transactional networking...</p>',
                    'author_name' => 'Dr. Pravin Parmar',
                    'author_role' => 'Founder & Global Chair, Peers Global',
                    'category' => 'Thought Leadership',
                    'tags' => ['Collaboration', 'Leadership', 'PeersGlobal'],
                    'read_time' => '5 min read',
                    'is_published' => true,
                    'published_at' => now()->subDays(5),
                ],
                [
                    'title' => 'Building Sustainable CleanTech Partnerships for Tier-2 Cities',
                    'slug' => 'building-sustainable-cleantech-partnerships-for-tier-2-cities',
                    'excerpt' => 'A deep dive into industrial microgrids and the economic incentives driving green manufacturing in Gujarat and Maharashtra.',
                    'content' => '<p>Decentralized energy transition is no longer just an ESG obligation—it is a critical margin defense strategy for manufacturing leaders...</p>',
                    'author_name' => 'Ananya Sharma',
                    'author_role' => 'CleanTech Industry Director',
                    'category' => 'Industry Insights',
                    'tags' => ['CleanTech', 'Energy', 'Industry'],
                    'read_time' => '7 min read',
                    'is_published' => true,
                    'published_at' => now()->subDays(12),
                ],
            ];

            foreach ($blogs as $blog) {
                WebBlog::create($blog);
            }
        }

        // 5. Seed Page Medias
        if (WebPageMedia::count() === 0) {
            $pageMedias = [
                [
                    'page_id' => 'home',
                    'page_title' => 'Home Page',
                    'page_slug' => '/',
                    'section_key' => 'home-hero',
                    'title' => 'Peers Global Grand Launch & Conclave Reel',
                    'description' => 'Cinematic full-bleed ambient video showing Indian promoters collaborating with world globe backdrops.',
                    'media_type' => 'video',
                    'media_source' => 'local',
                    'media_url' => '/videos/hero-background.mp4',
                    'local_file_name' => 'hero-background.mp4',
                    'is_active' => true,
                    'sort_order' => 1,
                ],
                [
                    'page_id' => 'home',
                    'page_title' => 'Home Page',
                    'page_slug' => '/',
                    'section_key' => 'home-earth-globe',
                    'title' => 'Peers Cyber Earth Network Loop',
                    'description' => 'Interactive 3D wireframe rotating globe highlighting interconnected chapter cities across India.',
                    'media_type' => 'video',
                    'media_source' => 'local',
                    'media_url' => '/videos/global-earth-hd.mp4',
                    'local_file_name' => 'global-earth-hd.mp4',
                    'is_active' => true,
                    'sort_order' => 2,
                ],
                [
                    'page_id' => 'circles',
                    'page_title' => 'Circles Hub',
                    'page_slug' => '/circles',
                    'section_key' => 'circles-hero',
                    'title' => 'Circle Meeting Experience & Roundtable',
                    'description' => 'Top-down cinematic video of promoters in high-trust circle meetings.',
                    'media_type' => 'video',
                    'media_source' => 'local',
                    'media_url' => '/videos/leadership-hero-bg.mp4',
                    'local_file_name' => 'leadership-hero-bg.mp4',
                    'is_active' => true,
                    'sort_order' => 1,
                ],
            ];

            foreach ($pageMedias as $pm) {
                WebPageMedia::create($pm);
            }
        }

        // 6. Seed Settings
        if (WebSetting::count() === 0) {
            $settings = [
                ['key' => 'site_name', 'value' => 'Peers Global', 'group' => 'general', 'description' => 'Public website branding name'],
                ['key' => 'site_tagline', 'value' => 'The Global Sovereign Network of Promoters & Founders', 'group' => 'general', 'description' => 'Hero tagline'],
                ['key' => 'contact_email', 'value' => 'connect@peersglobal.com', 'group' => 'contact', 'description' => 'Official contact email'],
                ['key' => 'contact_phone', 'value' => '+91 98765 43210', 'group' => 'contact', 'description' => 'Official helpline number'],
                ['key' => 'public_url', 'value' => 'https://peersglobal.com', 'group' => 'general', 'description' => 'Production public website URL'],
            ];

            foreach ($settings as $setting) {
                WebSetting::create($setting);
            }
        }
    }
}
