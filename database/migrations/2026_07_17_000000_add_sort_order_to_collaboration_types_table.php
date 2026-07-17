<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('collaboration_types') && !Schema::hasColumn('collaboration_types', 'sort_order')) {
            Schema::table('collaboration_types', function (Blueprint $table) {
                $table->integer('sort_order')->default(0);
            });
        }

        if (Schema::hasTable('collaboration_types')) {
            $count = DB::table('collaboration_types')->count();
            if ($count === 0) {
                $defaultTypes = [
                    ['id' => '1d58bb1e-b3c4-45b8-bae6-554a08bd3149', 'name' => 'Distributor / Channel Partner', 'slug' => 'distributor_channel_partner', 'sort_order' => 1],
                    ['id' => 'aa24a81c-b319-4182-9a10-e3010130524b', 'name' => 'Investor / Funding', 'slug' => 'investor_funding', 'sort_order' => 2],
                    ['id' => 'ac743b3b-770f-478d-81bf-2a85a955ea8b', 'name' => 'Strategic Partner', 'slug' => 'strategic_partner', 'sort_order' => 3],
                    ['id' => '1b94cb65-c327-42e0-8f41-b7805ed0f54d', 'name' => 'Joint Venture', 'slug' => 'joint_venture', 'sort_order' => 4],
                    ['id' => 'ab1bde6c-247f-43e7-b898-0eeac2deed4a', 'name' => 'Marketing Partner', 'slug' => 'marketing_partner', 'sort_order' => 5],
                    ['id' => '269ea371-b6d4-4ba6-b774-3f4a2809a0bd', 'name' => 'Vendor / Supplier', 'slug' => 'vendor_supplier', 'sort_order' => 6],
                    ['id' => '27939ca4-98f6-4a70-b6bc-85ba4fea045c', 'name' => 'Export / Import Partner', 'slug' => 'export_import_partner', 'sort_order' => 7],
                    ['id' => 'f04cdd67-1bdb-439b-afbc-fa794693b790', 'name' => 'Technology Partner', 'slug' => 'technology_partner', 'sort_order' => 8],
                    ['id' => 'e8a64535-cfe1-4c55-b2ee-9dd7a5a87e75', 'name' => 'Co-Founder', 'slug' => 'co_founder', 'sort_order' => 9],
                    ['id' => 'e8d5ff09-e080-4599-80cb-e2b690beeaa4', 'name' => 'Franchise Partner', 'slug' => 'franchise_partner', 'sort_order' => 10],
                    ['id' => '668c1c22-9c0e-4755-8884-ab743b664019', 'name' => 'Advisory / Mentor', 'slug' => 'advisory_mentor', 'sort_order' => 11],
                    ['id' => '346c68fe-06d7-4a7f-8c9e-42951a5542de', 'name' => 'Hiring / Talent', 'slug' => 'hiring_talent', 'sort_order' => 12],
                    ['id' => '48534393-1b46-4e76-8e2c-c070cc5f94f3', 'name' => 'Other', 'slug' => 'other', 'sort_order' => 13],
                ];

                foreach ($defaultTypes as $type) {
                    DB::table('collaboration_types')->insertOrIgnore(array_merge($type, [
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('collaboration_types') && Schema::hasColumn('collaboration_types', 'sort_order')) {
            Schema::table('collaboration_types', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};
