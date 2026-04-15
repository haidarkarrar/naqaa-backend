<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('meditop')->hasTable('TblCheckLists')) {
            Schema::connection('meditop')->create('TblCheckLists', function (Blueprint $table) {
                $table->increments('Id');
                $table->string('Name', 255);
                $table->text('Description')->nullable();
            });
        }

        if (!Schema::connection('meditop')->hasTable('TblCheckListItems')) {
            Schema::connection('meditop')->create('TblCheckListItems', function (Blueprint $table) {
                $table->increments('Id');
                $table->unsignedInteger('CheckListId');
                $table->string('Name', 255);
                $table->text('Description')->nullable();

                $table->index('CheckListId', 'tblchecklistitems_checklistid_index');
            });
        }

        if (!Schema::connection('meditop')->hasTable('TblPatientCheckedItems')) {
            Schema::connection('meditop')->create('TblPatientCheckedItems', function (Blueprint $table) {
                $table->increments('Id');
                $table->unsignedInteger('PatientId');
                $table->unsignedInteger('ItemId');
                $table->dateTime('Date')->nullable();
                $table->text('Note')->nullable();

                $table->index('PatientId', 'tblpatientcheckeditems_patientid_index');
                $table->index('ItemId', 'tblpatientcheckeditems_itemid_index');
                $table->unique(['PatientId', 'ItemId'], 'tblpatientcheckeditems_patient_item_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('meditop')->dropIfExists('TblPatientCheckedItems');
        Schema::connection('meditop')->dropIfExists('TblCheckListItems');
        Schema::connection('meditop')->dropIfExists('TblCheckLists');
    }
};
