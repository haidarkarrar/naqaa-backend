<?php

namespace App\Http\Controllers\Api;

use Carbon\CarbonImmutable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BatchUpdateAdmissionStatusRequest;
use App\Http\Requests\Api\PreviewBatchAdmissionStatusRequest;
use App\Http\Requests\Api\SaveDigitalFormRequest;
use App\Http\Requests\Api\UpdateAdmissionPatientRequest;
use App\Http\Requests\Api\UpdateAdmissionStatusRequest;
use App\Http\Requests\Api\UploadAttachmentRequest;
use App\Models\AdmissionAttachment;
use App\Models\AdmissionFile;
use App\Models\AdmissionStatusAudit;
use App\Models\CheckList;
use App\Models\CheckListItem;
use App\Models\DigitalAdmissionForm;
use App\Models\Patient;
use App\Models\PatientCheckedItem;
use App\Models\TblDocument;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdmissionController extends Controller
{
    private const DEFAULT_PEN_COLOR = '#38bdf8';
    private const DEFAULT_PEN_WIDTH = 3;
    private const DEFAULT_ERASER_WIDTH = 48;
    private const LEGACY_PLACEHOLDER_DATA_URI = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));
        $page = max(1, (int) $request->query('page', 1));

        $scope = $this->resolveScopeForPermissions(
            $user,
            PermissionCatalog::ADMISSIONS_LIST_ALL,
            PermissionCatalog::ADMISSIONS_LIST_ASSIGNED,
        );

        $admissionQuery = AdmissionFile::query()
            ->with('patient')
            ->tap(fn (Builder $query) => $this->applyAdmissionScope($query, $scope))
            ->tap(fn (Builder $query) => $this->applyAdmissionListFilters($query, [
                'status' => $request->query('status'),
                'start_date' => $request->query('start_date'),
                'end_date' => $request->query('end_date'),
                'start_at' => $request->query('start_at'),
                'end_before' => $request->query('end_before'),
                'patient' => $request->query('patient'),
            ]))
            ->orderBy('AdmDate', 'desc');

        $paginator = $admissionQuery->paginate($perPage, ['*'], 'page', $page);
        $admissionRecords = collect($paginator->items());
        $admissionIds = $admissionRecords->pluck('Id')->map(fn ($id) => (int) $id)->values();

        $legacyDocuments = TblDocument::query()
            ->whereIn('AdmNb', $admissionIds->all())
            ->get()
            ->groupBy('AdmNb');

        $assignedLookup = $this->buildAssignedLookupForUser($user, $admissionIds);

        $admissions = $admissionRecords->map(function (AdmissionFile $admission) use ($legacyDocuments, $assignedLookup, $user) {
            $legacyTump = ($legacyDocuments->get($admission->Id) ?? collect())
                ->map(fn (TblDocument $doc) => $this->toLegacyPreviewDataUri($doc))
                ->values()
                ->all();

            $isAssignedToUser = isset($assignedLookup[(int) $admission->Id]);
            $actions = $this->buildAdmissionActions(
                $user,
                $isAssignedToUser,
                (bool) $admission->Closed,
                !empty($legacyTump),
            );

            return [
                'id' => $admission->Id,
                'Patient' => "{$admission->Patient?->First} {$admission->Patient?->Last}",
                'AdmDate' => $this->serializeUtcDateTime($admission->AdmDate),
                'Status' => $admission->Closed ? 'closed' : 'open',
                'LegacyTump' => $legacyTump,
                ...$actions,
            ];
        });

        return response()->json([
            'admissions' => $admissions,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Log::info('API get single admission request', [
            'admission_id' => $id,
            'user_id' => $user->id ?? null,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ]);

        try {
            $admission = $this->resolveAdmissionByPermission(
                $user,
                $id,
                PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ALL,
                PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ASSIGNED,
                ['Patient', 'DigitalForm']
            );

            $isAssignedToUser = $this->isAdmissionAssignedToUser($user, (int) $admission->Id);

            $history = collect();
            if ($user->can(PermissionCatalog::ADMISSIONS_HISTORY_VIEW)) {
                $history = $this->buildHistoryPayload($user, $admission);
            }

            $legacyDocumentsByAdmission = TblDocument::query()
                ->where('AdmNb', (int) $admission->Id)
                ->get()
                ->groupBy('AdmNb');

            $legacyDocumentsForAdmission = ($legacyDocumentsByAdmission->get($admission->Id) ?? collect())
                ->map(fn (TblDocument $doc) => $this->toLegacyDocumentDataUri($doc))
                ->values()
                ->all();

            $attachments = $admission->attachments()
                ->orderBy('UploadedAt', 'desc')
                ->get()
                ->map(function (AdmissionAttachment $attachment) {
                    return [
                        'id' => $attachment->getKey(),
                        'Path' => $attachment->Path,
                        'Url' => Storage::url($attachment->Path),
                        'Label' => $attachment->Label,
                        'UploadedAt' => $this->serializeUtcDateTime($attachment->UploadedAt),
                    ];
                });

            $actions = $this->buildAdmissionActions(
                $user,
                $isAssignedToUser,
                (bool) $admission->Closed,
                !empty($legacyDocumentsForAdmission),
            );

            $patient = $admission->patient ?? $admission->Patient;

            return response()->json([
                'Admission' => $this->serializeAdmission($admission),
                'Patient' => $this->serializePatient($patient),
                'Checklists' => $patient ? $this->buildChecklistPayload((int) $patient->Id) : [],
                'History' => $history,
                'DigitalForm' => $admission->DigitalForm,
                'LegacyDocuments' => $legacyDocumentsForAdmission,
                'Attachments' => $attachments,
                ...$actions,
            ]);
        } catch (\Throwable $e) {
            Log::error('API get single admission failed', [
                'admission_id' => $id,
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function updatePatient(int $id, UpdateAdmissionPatientRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->can(PermissionCatalog::ADMISSIONS_PATIENT_EDIT)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $admission = $this->resolveAdmissionByPermission(
            $user,
            $id,
            PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ALL,
            PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ASSIGNED,
            ['patient']
        );

        /** @var Patient|null $patient */
        $patient = $admission->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $validated = $request->validated();
        $hasChecklistItemIds = array_key_exists('ChecklistItemIds', $validated);
        $checklistItemIds = collect($validated['ChecklistItemIds'] ?? [])
            ->map(fn ($itemId) => (int) $itemId)
            ->unique()
            ->values()
            ->all();
        unset($validated['ChecklistItemIds']);

        if (array_key_exists('DOB', $validated) && $validated['DOB']) {
            $validated['DOB'] = CarbonImmutable::parse($validated['DOB'])->toDateString();
        }

        /** @var Patient $freshPatient */
        $freshPatient = DB::connection('meditop')->transaction(function () use ($patient, $validated, $hasChecklistItemIds, $checklistItemIds) {
            $patient->fill($validated);
            $patient->save();

            if ($hasChecklistItemIds) {
                $this->syncPatientChecklistSelections((int) $patient->Id, $checklistItemIds);
            }

            /** @var Patient $refreshed */
            $refreshed = $patient->fresh();

            return $refreshed;
        });

        return response()->json([
            'message' => 'Patient info updated',
            'patient' => $this->serializePatient($freshPatient),
            'Checklists' => $this->buildChecklistPayload((int) $freshPatient->Id),
        ]);
    }

    public function saveForm(int $id, SaveDigitalFormRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $admission = $this->resolveAdmissionByPermission(
            $user,
            $id,
            PermissionCatalog::ADMISSIONS_FORM_EDIT_ALL,
            PermissionCatalog::ADMISSIONS_FORM_EDIT_ASSIGNED,
        );

        if ($admission->Closed) {
            return response()->json(['message' => 'Admission is closed'], 403);
        }

        if ($this->hasLegacyDocuments((int) $admission->Id)) {
            return response()->json(['message' => 'Admission has legacy documents and cannot be edited'], 403);
        }

        $form = DigitalAdmissionForm::firstOrNew(['AdmissionId' => $admission->Id]);
        $form->DoctorId = $user->doctor_id ?? (int) $admission->DoctorId;
        $form->UpdatedByUserId = $user->id;
        $form->Payload = $request->Payload;
        $form->Strokes = $this->sanitizeStrokes($request->Strokes ?? []);
        $form->FormVersion = $request->FormVersion ?? 'v1';
        $form->Status = $request->Status ?? 'draft';
        $form->save();

        return response()->json([
            'Form' => $form->fresh(),
        ]);
    }

    public function uploadAttachment(int $id, UploadAttachmentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $admission = $this->resolveAdmissionByPermission(
            $user,
            $id,
            PermissionCatalog::ADMISSIONS_ATTACHMENTS_MANAGE_ALL,
            PermissionCatalog::ADMISSIONS_ATTACHMENTS_MANAGE_ASSIGNED,
        );

        if ($admission->Closed) {
            return response()->json(['message' => 'Admission is closed'], 403);
        }

        if ($this->hasLegacyDocuments((int) $admission->Id)) {
            return response()->json(['message' => 'Admission has legacy documents and cannot be edited'], 403);
        }

        Log::info('Admission attachment upload requested', [
            'user_id' => $user->id,
            'admission_id' => $admission->Id,
            'has_file' => $request->hasFile('File'),
        ]);

        try {
            $file = $request->file('File');
            $path = Storage::disk('public')->putFile('admissions', $file);

            $attachment = AdmissionAttachment::create([
                'DoctorId' => $user->doctor_id ?? (int) $admission->DoctorId,
                'UploadedByUserId' => $user->id,
                'AdmissionId' => $admission->Id,
                'Path' => $path,
                'Mime' => $file->getClientMimeType(),
                'Label' => $request->Label,
                'UploadedAt' => now(),
            ]);

            Log::info('Admission attachment uploaded', [
                'attachment_id' => $attachment->getKey(),
                'user_id' => $user->id,
                'admission_id' => $admission->Id,
                'path' => $attachment->Path,
            ]);

            return response()->json([
                'Attachment' => [
                    'id' => $attachment->getKey(),
                    'Path' => $attachment->Path,
                    'Url' => Storage::url($attachment->Path),
                    'Label' => $attachment->Label,
                    'UploadedAt' => $this->serializeUtcDateTime($attachment->UploadedAt),
                ],
            ]);
        } catch (\Throwable $error) {
            Log::error('Admission attachment upload failed', [
                'user_id' => $user->id,
                'admission_id' => $admission->Id,
                'error' => $error->getMessage(),
                'trace' => $error->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Unable to upload attachment'], 500);
        }
    }

    public function deleteAttachment(int $id, int $attachmentId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $admission = $this->resolveAdmissionByPermission(
            $user,
            $id,
            PermissionCatalog::ADMISSIONS_ATTACHMENTS_MANAGE_ALL,
            PermissionCatalog::ADMISSIONS_ATTACHMENTS_MANAGE_ASSIGNED,
        );

        if ($admission->Closed) {
            return response()->json(['message' => 'Admission is closed'], 403);
        }

        if ($this->hasLegacyDocuments((int) $admission->Id)) {
            return response()->json(['message' => 'Admission has legacy documents and cannot be edited'], 403);
        }

        $attachment = AdmissionAttachment::query()
            ->whereKey($attachmentId)
            ->where('AdmissionId', $admission->Id)
            ->firstOrFail();

        if (Storage::disk('public')->exists($attachment->Path)) {
            Storage::disk('public')->delete($attachment->Path);
        }

        $attachment->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function updateStatus(int $id, UpdateAdmissionStatusRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->can(PermissionCatalog::ADMISSIONS_STATUS_UPDATE)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $admission = $this->resolveAdmissionByPermission(
            $user,
            $id,
            PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ALL,
            PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ASSIGNED,
        );

        $targetStatus = $request->status;
        $targetClosed = $targetStatus === 'closed';
        $currentStatus = $admission->Closed ? 'closed' : 'open';

        if ((bool) $admission->Closed !== $targetClosed) {
            AdmissionFile::query()
                ->whereKey($admission->Id)
                ->update(['Closed' => $targetClosed ? 1 : 0]);
        }

        AdmissionStatusAudit::create([
            'admission_id' => (int) $admission->Id,
            'old_status' => $currentStatus,
            'new_status' => $targetStatus,
            'changed_by_user_id' => $user->id,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Admission status updated',
            'status' => $targetStatus,
        ]);
    }

    public function previewBatchStatus(PreviewBatchAdmissionStatusRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$this->canBatchUpdateStatus($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validated();
        $mode = (string) $validated['mode'];
        $targetStatus = (string) $validated['status'];
        $targetClosed = $targetStatus === 'closed';
        $admissionIds = $mode === 'selected'
            ? collect($validated['admission_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all()
            : [];
        $scopeType = $mode === 'scope' ? (string) ($validated['scope_type'] ?? 'all') : null;
        $scopeFilters = $scopeType === 'date_range'
            ? [
                'start_at' => $validated['start_at'] ?? null,
                'end_before' => $validated['end_before'] ?? null,
            ]
            : [];

        $scope = $this->resolveScopeForPermissions(
            $user,
            PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ALL,
            PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ASSIGNED,
        );

        $counts = $this->computeBatchStatusCounts(
            $scope,
            $mode,
            $targetClosed,
            $admissionIds,
            $scopeType,
            $scopeFilters,
        );

        return response()->json([
            'mode' => $mode,
            'status' => $targetStatus,
            'matched_count' => $counts['matched_count'],
            'will_change_count' => $counts['will_change_count'],
            'already_target_count' => $counts['already_target_count'],
            'inaccessible_count' => $counts['inaccessible_count'],
            'selection_summary' => $this->buildBatchSelectionSummary($mode, $admissionIds, $scopeType, $scopeFilters),
        ]);
    }

    public function batchUpdateStatus(BatchUpdateAdmissionStatusRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$this->canBatchUpdateStatus($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validated();
        $mode = (string) $validated['mode'];
        $targetStatus = (string) $validated['status'];
        $targetClosed = $targetStatus === 'closed';
        $admissionIds = $mode === 'selected'
            ? collect($validated['admission_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all()
            : [];
        $scopeType = $mode === 'scope' ? (string) ($validated['scope_type'] ?? 'all') : null;
        $scopeFilters = $scopeType === 'date_range'
            ? [
                'start_at' => $validated['start_at'] ?? null,
                'end_before' => $validated['end_before'] ?? null,
            ]
            : [];

        $scope = $this->resolveScopeForPermissions(
            $user,
            PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ALL,
            PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ASSIGNED,
        );

        $counts = $this->computeBatchStatusCounts(
            $scope,
            $mode,
            $targetClosed,
            $admissionIds,
            $scopeType,
            $scopeFilters,
        );

        if ((int) $validated['expected_will_change_count'] !== $counts['will_change_count']) {
            return response()->json([
                'message' => 'Batch preview is stale. Please preview and confirm again.',
                'mode' => $mode,
                'status' => $targetStatus,
                'matched_count' => $counts['matched_count'],
                'updated_count' => 0,
                'already_target_count' => $counts['already_target_count'],
                'inaccessible_count' => $counts['inaccessible_count'],
                'audits_created' => 0,
                'will_change_count' => $counts['will_change_count'],
            ], 409);
        }

        [$updatedCount, $auditsCreated] = $this->performBatchStatusUpdate(
            $scope,
            $mode,
            $targetClosed,
            $admissionIds,
            $scopeType,
            $scopeFilters,
            (int) $user->id,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Admission statuses updated',
            'status' => $targetStatus,
            'mode' => $mode,
            'matched_count' => $counts['matched_count'],
            'updated_count' => $updatedCount,
            'already_target_count' => $counts['already_target_count'],
            'inaccessible_count' => $counts['inaccessible_count'],
            'audits_created' => $auditsCreated,
        ]);
    }

    private function sanitizeStrokes(array $strokes): array
    {
        return collect($strokes)
            ->map(function ($stroke) {
                $points = collect($stroke['points'] ?? [])
                    ->map(function ($point) {
                        $x = $point['x'] ?? null;
                        $y = $point['y'] ?? null;

                        if (!is_numeric($x) || !is_numeric($y)) {
                            return null;
                        }

                        return [
                            'x' => (float) $x,
                            'y' => (float) $y,
                            'timestamp' => isset($point['timestamp']) && is_numeric($point['timestamp'])
                                ? (int) $point['timestamp']
                                : now()->valueOf(),
                        ];
                    })
                    ->filter()
                    ->values();

                if ($points->isEmpty()) {
                    return null;
                }

                $tool = isset($stroke['tool']) && in_array($stroke['tool'], ['pen', 'eraser'], true)
                    ? $stroke['tool']
                    : 'pen';

                return [
                    'id' => isset($stroke['id']) && is_string($stroke['id'])
                        ? $stroke['id']
                        : Str::uuid()->toString(),
                    'tool' => $tool,
                    'width' => isset($stroke['width']) && is_numeric($stroke['width'])
                        ? (float) $stroke['width']
                        : ($tool === 'eraser' ? self::DEFAULT_ERASER_WIDTH : self::DEFAULT_PEN_WIDTH),
                    'color' => isset($stroke['color']) && is_string($stroke['color'])
                        ? $stroke['color']
                        : self::DEFAULT_PEN_COLOR,
                    'points' => $points->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function buildHistoryPayload(User $user, AdmissionFile $admission): Collection
    {
        $historyQuery = AdmissionFile::query()
            ->with('doctor')
            ->where('PatientId', $admission->PatientId)
            ->withNonNullWorksDoctor();

        if (!$user->can(PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ALL)) {
            $doctorId = $this->requireLinkedDoctorId($user);
            $historyQuery->assignedToDoctorViaWorks($doctorId);
        }

        /** @var EloquentCollection<int, AdmissionFile> $historyRecords */
        $historyRecords = $historyQuery
            ->orderBy('AdmDate', 'desc')
            ->get();

        $legacyLookupIds = $historyRecords->pluck('Id')->push($admission->Id)->unique()->filter()->all();
        $historyPatientIds = $historyRecords->pluck('PatientId')->push($admission->PatientId)->unique()->filter()->all();
        $historyAdmissionIds = collect($legacyLookupIds)
            ->map(fn ($value) => (int) $value)
            ->values();

        $legacyDocumentsByAdmission = TblDocument::query()
            ->whereIn('AdmNb', $legacyLookupIds)
            ->get()
            ->groupBy('AdmNb');

        $legacyDocumentsByPatient = empty($historyPatientIds)
            ? collect()
            : TblDocument::query()
                ->whereIn('MRN', $historyPatientIds)
                ->get()
                ->groupBy('MRN');

        $historyAdmissions = $historyRecords->map(function (AdmissionFile $record) use ($legacyDocumentsByAdmission) {
            $legacyTump = ($legacyDocumentsByAdmission->get($record->Id) ?? collect())
                ->map(fn (TblDocument $doc) => $this->toLegacyPreviewDataUri($doc))
                ->values()
                ->all();

            return [
                'id' => $record->Id,
                'admissionNumber' => (int) $record->Id,
                'historyType' => 'admission',
                'admDate' => $this->serializeUtcDateTime($record->AdmDate),
                'status' => $record->Closed ? 'closed' : 'open',
                'doctorId' => $record->DoctorId,
                'doctorName' => optional($record->doctor)->FullName,
                'legacyTump' => $legacyTump,
                'legacyDocumentId' => null,
            ];
        });

        $legacyOnlyHistory = ($legacyDocumentsByPatient->get($admission->PatientId) ?? collect())
            ->filter(function (TblDocument $doc) use ($historyAdmissionIds) {
                if ($doc->AdmNb === null) {
                    return true;
                }

                return !$historyAdmissionIds->contains((int) $doc->AdmNb);
            })
            ->map(function (TblDocument $doc) {
                return [
                    'id' => null,
                    'admissionNumber' => $doc->AdmNb !== null ? (int) $doc->AdmNb : null,
                    'historyType' => 'legacy',
                    'admDate' => $this->serializeUtcDateTime($doc->Date),
                    'status' => 'legacy',
                    'doctorId' => null,
                    'doctorName' => null,
                    'legacyTump' => [$this->toLegacyPreviewDataUri($doc)],
                    'legacyDocumentId' => (int) $doc->Id,
                ];
            });

        return $historyAdmissions
            ->merge($legacyOnlyHistory)
            ->sortByDesc(fn (array $item) => $item['admDate'] ?? '0000-00-00 00:00:00')
            ->values();
    }

    private function resolveScopeForPermissions(User $user, string $allPermission, string $assignedPermission): array
    {
        if ($user->can($allPermission)) {
            return [
                'mode' => 'all',
                'doctor_id' => null,
            ];
        }

        if ($user->can($assignedPermission)) {
            return [
                'mode' => 'assigned',
                'doctor_id' => $this->requireLinkedDoctorId($user),
            ];
        }

        abort(403, 'Forbidden');
    }

    private function resolveAdmissionByPermission(
        User $user,
        int $admissionId,
        string $allPermission,
        string $assignedPermission,
        array $with = []
    ): AdmissionFile {
        $scope = $this->resolveScopeForPermissions($user, $allPermission, $assignedPermission);

        $query = AdmissionFile::query()
            ->where('Id', $admissionId)
            ->with($with)
            ->tap(fn (Builder $builder) => $this->applyAdmissionScope($builder, $scope));

        return $query->firstOrFail();
    }

    private function applyAdmissionScope(Builder $query, array $scope): Builder
    {
        if (($scope['mode'] ?? null) === 'all') {
            return $query->withNonNullWorksDoctor();
        }

        return $query->assignedToDoctorViaWorks((int) ($scope['doctor_id'] ?? 0));
    }

    private function applyAdmissionListFilters(Builder $query, array $filters): Builder
    {
        $startAt = $this->normalizeUtcFilterBoundary($filters['start_at'] ?? null);
        if ($startAt !== null) {
            $query->where('AdmDate', '>=', $startAt);
        }

        $endBefore = $this->normalizeUtcFilterBoundary($filters['end_before'] ?? null);
        if ($endBefore !== null) {
            $query->where('AdmDate', '<', $endBefore);
        }

        $status = isset($filters['status']) ? strtolower(trim((string) $filters['status'])) : '';
        if ($status === 'closed') {
            $query->where('Closed', 1);
        } elseif ($status === 'open') {
            $query->where('Closed', 0);
        }

        $patient = isset($filters['patient']) ? trim((string) $filters['patient']) : '';
        if ($patient !== '') {
            $like = '%' . $patient . '%';
            $query->whereHas('patient', function (Builder $patientQuery) use ($like) {
                $patientQuery->where(function (Builder $innerQuery) use ($like) {
                    $innerQuery
                        ->where('First', 'like', $like)
                        ->orWhere('Middle', 'like', $like)
                        ->orWhere('Last', 'like', $like)
                        ->orWhere('ArabicName', 'like', $like)
                        ->orWhere('Phone', 'like', $like);
                });
            });
        }

        return $query;
    }

    private function normalizeUtcFilterBoundary(mixed $value): ?string
    {
        $boundary = trim((string) $value);
        if ($boundary === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($boundary)->utc()->format('Y-m-d H:i:s.u');
        } catch (\Throwable) {
            return null;
        }
    }

    private function serializeAdmission(AdmissionFile $admission): array
    {
        $payload = $admission->attributesToArray();
        $payload['AdmDate'] = $this->serializeUtcDateTime($admission->AdmDate);

        return $payload;
    }

    private function serializePatient(?Patient $patient): ?array
    {
        if (!$patient) {
            return null;
        }

        return $patient->attributesToArray();
    }

    private function buildChecklistPayload(int $patientId): array
    {
        $checklists = CheckList::query()
            ->with(['items' => fn ($query) => $query->orderBy('Id')])
            ->orderBy('Id')
            ->get();

        if ($checklists->isEmpty()) {
            return [];
        }

        $checkedItemLookup = PatientCheckedItem::query()
            ->where('PatientId', $patientId)
            ->pluck('ItemId')
            ->mapWithKeys(fn ($itemId) => [(int) $itemId => true])
            ->all();

        return $checklists
            ->map(function (CheckList $checklist) use ($checkedItemLookup) {
                $checkedItems = $checklist->items
                    ->filter(fn (CheckListItem $item) => isset($checkedItemLookup[(int) $item->Id]))
                    ->values();

                return [
                    'Id' => (int) $checklist->Id,
                    'Name' => $checklist->Name,
                    'Items' => $checklist->items
                        ->map(fn (CheckListItem $item) => [
                            'Id' => (int) $item->Id,
                            'Name' => $item->Name,
                        ])
                        ->values()
                        ->all(),
                    'CheckedItemIds' => $checkedItems
                        ->pluck('Id')
                        ->map(fn ($itemId) => (int) $itemId)
                        ->values()
                        ->all(),
                    'CheckedItemNames' => $checkedItems
                        ->pluck('Name')
                        ->filter(fn ($name) => is_string($name) && $name !== '')
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function syncPatientChecklistSelections(int $patientId, array $selectedItemIds): void
    {
        $selectedIds = collect($selectedItemIds)
            ->map(fn ($itemId) => (int) $itemId)
            ->unique()
            ->values()
            ->all();
        $selectedLookup = array_fill_keys($selectedIds, true);

        $currentRows = PatientCheckedItem::query()
            ->where('PatientId', $patientId)
            ->orderBy('Id')
            ->get(['Id', 'ItemId']);

        $rowIdsToDelete = [];
        $existingSelectedIds = [];

        foreach ($currentRows->groupBy(fn (PatientCheckedItem $row) => (int) $row->ItemId) as $itemId => $rowsForItem) {
            $numericItemId = (int) $itemId;
            if (!isset($selectedLookup[$numericItemId])) {
                foreach ($rowsForItem as $row) {
                    $rowIdsToDelete[] = (int) $row->Id;
                }
                continue;
            }

            $existingSelectedIds[] = $numericItemId;

            foreach ($rowsForItem->slice(1) as $duplicateRow) {
                $rowIdsToDelete[] = (int) $duplicateRow->Id;
            }
        }

        if (!empty($rowIdsToDelete)) {
            PatientCheckedItem::query()->whereIn('Id', $rowIdsToDelete)->delete();
        }

        $itemIdsToInsert = array_values(array_diff($selectedIds, array_unique($existingSelectedIds)));
        if (empty($itemIdsToInsert)) {
            return;
        }

        $now = now();
        $rowsToInsert = array_map(fn (int $itemId) => [
            'PatientId' => $patientId,
            'ItemId' => $itemId,
            'Date' => $now,
            'Note' => null,
        ], $itemIdsToInsert);

        PatientCheckedItem::query()->insert($rowsToInsert);
    }

    private function serializeUtcDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc()->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable) {
            return null;
        }
    }

    private function canBatchUpdateStatus(User $user): bool
    {
        return $user->can(PermissionCatalog::ADMISSIONS_STATUS_UPDATE)
            && $user->can(PermissionCatalog::ADMISSIONS_STATUS_UPDATE_BATCH);
    }

    private function buildBatchStatusBaseQuery(
        array $scope,
        string $mode,
        array $admissionIds,
        ?string $scopeType,
        array $scopeFilters
    ): Builder {
        $query = AdmissionFile::query()
            ->tap(fn (Builder $builder) => $this->applyAdmissionScope($builder, $scope));

        if ($mode === 'selected') {
            if (empty($admissionIds)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn('Id', $admissionIds);
        }

        if ($scopeType === 'date_range') {
            return $this->applyAdmissionListFilters($query, $scopeFilters);
        }

        return $query;
    }

    private function computeBatchStatusCounts(
        array $scope,
        string $mode,
        bool $targetClosed,
        array $admissionIds,
        ?string $scopeType,
        array $scopeFilters
    ): array {
        $baseQuery = $this->buildBatchStatusBaseQuery($scope, $mode, $admissionIds, $scopeType, $scopeFilters);

        $matchedCount = (clone $baseQuery)->count();
        $willChangeCount = (clone $baseQuery)->where('Closed', $targetClosed ? 0 : 1)->count();

        return [
            'matched_count' => (int) $matchedCount,
            'will_change_count' => (int) $willChangeCount,
            'already_target_count' => max(0, (int) $matchedCount - (int) $willChangeCount),
            'inaccessible_count' => $mode === 'selected'
                ? max(0, count($admissionIds) - (int) $matchedCount)
                : 0,
        ];
    }

    private function performBatchStatusUpdate(
        array $scope,
        string $mode,
        bool $targetClosed,
        array $admissionIds,
        ?string $scopeType,
        array $scopeFilters,
        int $userId,
        ?string $notes
    ): array {
        $updatedCount = 0;
        $auditsCreated = 0;
        $targetValue = $targetClosed ? 1 : 0;

        $this->buildBatchStatusBaseQuery($scope, $mode, $admissionIds, $scopeType, $scopeFilters)
            ->where('Closed', $targetClosed ? 0 : 1)
            ->orderBy('Id')
            ->chunkById(200, function (EloquentCollection $chunk) use (
                &$updatedCount,
                &$auditsCreated,
                $targetValue,
                $targetClosed,
                $userId,
                $notes
            ) {
                if ($chunk->isEmpty()) {
                    return;
                }

                $chunkIds = $chunk->pluck('Id')->map(fn ($id) => (int) $id)->values()->all();

                if (empty($chunkIds)) {
                    return;
                }

                AdmissionFile::query()
                    ->whereIn('Id', $chunkIds)
                    ->update(['Closed' => $targetValue]);

                $now = now();
                $auditRows = $chunk
                    ->map(function (AdmissionFile $admission) use ($targetClosed, $userId, $notes, $now) {
                        return [
                            'admission_id' => (int) $admission->Id,
                            'old_status' => $admission->Closed ? 'closed' : 'open',
                            'new_status' => $targetClosed ? 'closed' : 'open',
                            'changed_by_user_id' => $userId,
                            'notes' => $notes,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })
                    ->values()
                    ->all();

                if (!empty($auditRows)) {
                    AdmissionStatusAudit::query()->insert($auditRows);
                }

                $updatedCount += count($chunkIds);
                $auditsCreated += count($auditRows);
            }, 'Id', 'Id');

        return [$updatedCount, $auditsCreated];
    }

    private function buildBatchSelectionSummary(string $mode, array $admissionIds, ?string $scopeType, array $scopeFilters): string
    {
        if ($mode === 'selected') {
            $count = count($admissionIds);
            return $count === 1
                ? 'Selected scope: 1 admission.'
                : sprintf('Selected scope: %d admissions.', $count);
        }

        if ($scopeType === 'all') {
            return 'Scope: all accessible admissions.';
        }

        $startAt = $this->normalizeUtcFilterBoundary($scopeFilters['start_at'] ?? null);
        $endBefore = $this->normalizeUtcFilterBoundary($scopeFilters['end_before'] ?? null);

        if ($startAt !== null && $endBefore !== null) {
            return sprintf('Scope: admissions from %s up to %s (UTC).', $startAt, $endBefore);
        }

        return 'Scope: accessible admissions in the selected date range.';
    }

    private function buildAssignedLookupForUser(User $user, Collection $admissionIds): array
    {
        if ($admissionIds->isEmpty() || !$user->doctor_id) {
            return [];
        }

        $ids = AdmissionFile::query()
            ->whereIn('Id', $admissionIds->all())
            ->assignedToDoctorViaWorks((int) $user->doctor_id)
            ->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_fill_keys($ids, true);
    }

    private function isAdmissionAssignedToUser(User $user, int $admissionId): bool
    {
        if (!$user->doctor_id) {
            return false;
        }

        return AdmissionFile::query()
            ->where('Id', $admissionId)
            ->assignedToDoctorViaWorks((int) $user->doctor_id)
            ->exists();
    }

    private function requireLinkedDoctorId(User $user): int
    {
        if (!$user->doctor_id) {
            abort(403, 'Doctor link is required for this action.');
        }

        return (int) $user->doctor_id;
    }

    private function hasLegacyDocuments(int $admissionId): bool
    {
        return TblDocument::query()->where('AdmNb', $admissionId)->exists();
    }

    private function buildAdmissionActions(
        User $user,
        bool $isAssignedToUser,
        bool $isClosed,
        bool $hasLegacyDocuments
    ): array {
        $canView = $user->can(PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ALL)
            || ($user->can(PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ASSIGNED) && $isAssignedToUser);

        $canEditForm = !$isClosed
            && !$hasLegacyDocuments
            && (
                $user->can(PermissionCatalog::ADMISSIONS_FORM_EDIT_ALL)
                || ($user->can(PermissionCatalog::ADMISSIONS_FORM_EDIT_ASSIGNED) && $isAssignedToUser)
            );

        $canManageAttachments = !$isClosed
            && !$hasLegacyDocuments
            && (
                $user->can(PermissionCatalog::ADMISSIONS_ATTACHMENTS_MANAGE_ALL)
                || ($user->can(PermissionCatalog::ADMISSIONS_ATTACHMENTS_MANAGE_ASSIGNED) && $isAssignedToUser)
            );

        $canEditPatientInfo = $user->can(PermissionCatalog::ADMISSIONS_PATIENT_EDIT) && $canView;

        $canChangeStatus = $user->can(PermissionCatalog::ADMISSIONS_STATUS_UPDATE)
            && (
                $user->can(PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ALL)
                || ($user->can(PermissionCatalog::ADMISSIONS_VIEW_DETAIL_ASSIGNED) && $isAssignedToUser)
            );

        $canViewHistory = $canView && $user->can(PermissionCatalog::ADMISSIONS_HISTORY_VIEW);

        return [
            'canView' => $canView,
            'canEditForm' => $canEditForm,
            'canEditPatientInfo' => $canEditPatientInfo,
            'canManageAttachments' => $canManageAttachments,
            'canChangeStatus' => $canChangeStatus,
            'canViewHistory' => $canViewHistory,
        ];
    }

    private function toLegacyImageDataUri($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode($value);
    }

    private function toLegacyDocumentDataUri(TblDocument $document): string
    {
        return $this->toLegacyImageDataUri($document->Document)
            ?? $this->toLegacyImageDataUri($document->Tump)
            ?? self::LEGACY_PLACEHOLDER_DATA_URI;
    }

    private function toLegacyPreviewDataUri(TblDocument $document): string
    {
        return $this->toLegacyImageDataUri($document->Document)
            ?? $this->toLegacyImageDataUri($document->Tump)
            ?? self::LEGACY_PLACEHOLDER_DATA_URI;
    }
}
