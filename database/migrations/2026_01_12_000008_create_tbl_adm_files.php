<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('meditop')->create('TblAdmFiles', function (Blueprint $table) {
            $table->integer('Id')->primary();
            $table->integer('PatientId')->nullable();
            $table->dateTime('AdmDate')->nullable();
            $table->integer('DoctorId')->nullable();
            $table->integer('GuarantorId')->nullable();
            $table->integer('AccountId')->nullable();
            $table->string('Notes', 500)->nullable();
            $table->string('AdmUser', 100)->nullable();
            $table->integer('SerialId')->nullable();
            $table->decimal('GuarantorRate', 18, 2)->default(0);
            $table->string('RequestID', 100)->nullable();
            $table->string('Reference', 100)->nullable();
            $table->boolean('Posted')->default(false);
            $table->integer('TranactionId')->nullable();
            $table->string('Comment', 500)->nullable();
            $table->integer('AlternativeGuarantorId')->nullable();
            $table->string('AlternativePatient', 500)->nullable();
            $table->dateTime('AlternativeDOB')->nullable();
            $table->string('ApprovalNb', 100)->nullable();
            $table->dateTime('ApprovalDate')->nullable();
            $table->string('ApprovalOrigine', 100)->nullable();
            $table->string('GuaranteedName', 150)->nullable();
            $table->string('GuaranteedNb', 100)->nullable();
            $table->string('GuaranteedPlace', 100)->nullable();
            $table->integer('RelationId')->nullable();
            $table->integer('CaseId')->nullable();
            $table->decimal('GrandTotal', 18, 2)->default(0);
            $table->decimal('GuarantorShare', 18, 2)->default(0);
            $table->decimal('GuarantorDiscount', 18, 2)->default(0);
            $table->decimal('PatientDiscount', 18, 2)->default(0);
            $table->decimal('PatientShare', 18, 2)->default(0);
            $table->decimal('Paid', 18, 2)->default(0);
            $table->boolean('PaymentClosed')->default(false);
            $table->integer('ScheduleId')->nullable();
            $table->decimal('Collected', 18, 2)->default(0);
            $table->boolean('Closed')->default(false);
            $table->string('AltDOBYear', 10)->nullable();
            $table->boolean('PaymentClosed1')->default(false);
            $table->integer('BrockerId')->nullable();
            $table->decimal('BrockerPercentage', 18, 2)->default(0);
            $table->string('AlternativeGender', 10)->nullable();
            $table->decimal('DoctorShare', 18, 2)->default(0);
            $table->boolean('ForDoctor')->default(false);
            $table->boolean('ForPatient')->default(false);
            $table->string('AddedBy', 100)->nullable();
            $table->string('UpdatedBy', 100)->nullable();
            $table->string('PostedBy', 100)->nullable();
            $table->boolean('Checked')->default(false);
            $table->dateTime('CheckedDate')->nullable();
            $table->integer('AdmTypeId')->nullable();
            $table->string('CompanyNb', 50)->nullable();
            $table->boolean('Approved')->default(false);
            $table->dateTime('CDate')->nullable();
            $table->dateTime('PDate')->nullable();
            $table->integer('CurrencyId')->nullable();
            $table->decimal('Rate', 18, 4)->default(0);
            $table->boolean('LastPostState')->default(false);
        });
    }

    public function down(): void
    {
        Schema::connection('meditop')->dropIfExists('TblAdmFiles');
    }
};
