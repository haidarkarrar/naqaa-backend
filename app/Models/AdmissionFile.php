<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdmissionFile extends Model
{
    protected $connection = 'meditop';
    protected $table = 'TblAdmFiles';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $casts = [
        'AdmDate' => 'datetime',
        // SQL Server can return int/bit as strings; cast so API returns consistent types
        'Id' => 'integer',
        'PatientId' => 'integer',
        'DoctorId' => 'integer',
        'GuarantorId' => 'integer',
        'Closed' => 'boolean',
        'Posted' => 'boolean',
        'PaymentClosed' => 'boolean',
        'PaymentClosed1' => 'boolean',
        'ForDoctor' => 'boolean',
        'ForPatient' => 'boolean',
        'Checked' => 'boolean',
        'Approved' => 'boolean',
        'LastPostState' => 'boolean',
    ];

    public function scopeAssignedToDoctorViaWorks(Builder $query, int $doctorId): Builder
    {
        $admissionsTable = $query->getModel()->getTable();

        return $query->whereExists(function ($subQuery) use ($admissionsTable, $doctorId) {
            $subQuery
                ->selectRaw('1')
                ->from('tblWorks')
                ->whereColumn('tblWorks.AdmId', $admissionsTable . '.Id')
                ->where('tblWorks.DoctorId', $doctorId);
        });
    }

    public function scopeWithNonNullWorksDoctor(Builder $query): Builder
    {
        $admissionsTable = $query->getModel()->getTable();

        return $query->whereExists(function ($subQuery) use ($admissionsTable) {
            $subQuery
                ->selectRaw('1')
                ->from('tblWorks')
                ->whereColumn('tblWorks.AdmId', $admissionsTable . '.Id')
                ->whereNotNull('tblWorks.DoctorId');
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'PatientId', 'Id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'DoctorId', 'Id');
    }

    public function digitalForm(): HasOne
    {
        return $this->hasOne(
            DigitalAdmissionForm::class,
            'AdmissionId',
            'Id'
        );
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AdmissionAttachment::class, 'AdmissionId', 'Id');
    }

}
