<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('naqaa')->table('TblDigitalAdmissionForms', function (Blueprint $table) {
            if (!Schema::connection('naqaa')->hasColumn('TblDigitalAdmissionForms', 'UpdatedByUserId')) {
                $table->unsignedBigInteger('UpdatedByUserId')->nullable()->after('DoctorId');
                $table->index('UpdatedByUserId');
            }
        });

        Schema::connection('naqaa')->table('TblAdmissionAttachments', function (Blueprint $table) {
            if (!Schema::connection('naqaa')->hasColumn('TblAdmissionAttachments', 'UploadedByUserId')) {
                $table->unsignedBigInteger('UploadedByUserId')->nullable()->after('DoctorId');
                $table->index('UploadedByUserId');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('naqaa')->table('TblDigitalAdmissionForms', function (Blueprint $table) {
            if (Schema::connection('naqaa')->hasColumn('TblDigitalAdmissionForms', 'UpdatedByUserId')) {
                $table->dropIndex(['UpdatedByUserId']);
                $table->dropColumn('UpdatedByUserId');
            }
        });

        Schema::connection('naqaa')->table('TblAdmissionAttachments', function (Blueprint $table) {
            if (Schema::connection('naqaa')->hasColumn('TblAdmissionAttachments', 'UploadedByUserId')) {
                $table->dropIndex(['UploadedByUserId']);
                $table->dropColumn('UploadedByUserId');
            }
        });
    }
};

