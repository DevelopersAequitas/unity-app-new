<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('industries')) {
            $defaultIndustries = [
                ['id' => 'd81f2eac-8855-448d-8af3-c1327e22de55', 'name' => 'Agriculture & Rural Enterprises'],
                ['id' => 'd1e0fbf5-1ff7-4df0-857f-3080798e3191', 'name' => 'Creative & Lifestyle'],
                ['id' => '31d3e2e2-3943-4737-b960-44395b30ed65', 'name' => 'Education & Skill Development'],
                ['id' => 'c7ad515e-4c78-4946-92e0-bf5d38693c33', 'name' => 'Finance & Investment'],
                ['id' => '731228d0-fafc-4327-bd0b-5f81cb3af242', 'name' => 'Food, Hospitality & Travel'],
                ['id' => '8c2d8588-6357-4a28-bc38-7cba83ec1d8a', 'name' => 'Healthcare & Life Sciences'],
                ['id' => 'f1aa505d-99a6-41c2-8f25-056365760960', 'name' => 'Import, Export & Global Trade'],
                ['id' => '0556f88c-b145-42d5-a479-32c857019110', 'name' => 'IPO, Corporate & Large Enterprise Services'],
                ['id' => '96724e2f-b31a-49fd-9c36-bb0b204ee600', 'name' => 'Manufacturing & Engineering'],
                ['id' => 'a4245962-2f9b-4cdd-943f-cad088decebb', 'name' => 'Marketing, Media & Communication'],
                ['id' => 'e268eda3-79dc-4d7b-9302-09940672fee3', 'name' => 'MSME Services & Business Support'],
                ['id' => '183a5553-f50a-4bb6-a91a-3792427b5e6e', 'name' => 'Professional Services'],
                ['id' => 'cb3c364a-fbfb-4b83-afc5-88aa9f2cc514', 'name' => 'Real Estate & Infrastructure'],
                ['id' => '6798f9d9-3b19-477e-b366-c5fa00ce0bf8', 'name' => 'Retail & E-Commerce'],
                ['id' => '1b93c3d2-c573-48d6-b847-583247c90a17', 'name' => 'Startup Ecosystem'],
                ['id' => 'f5adfc7b-5cd5-4b6c-9330-e3fcd5680bf1', 'name' => 'Sustainability & ESG'],
                ['id' => 'd17b210c-2370-4f0d-be43-1434c215bdfd', 'name' => 'Technology & Digital'],
                ['id' => 'f94245b5-3f18-4ad8-b87d-ebab677c76de', 'name' => 'Women & Social Enterprises'],
            ];

            foreach ($defaultIndustries as $industry) {
                DB::table('industries')->insertOrIgnore(array_merge($industry, [
                    'parent_id' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        // No down operation needed
    }
};
