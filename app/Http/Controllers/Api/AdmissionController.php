<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveDigitalFormRequest;
use App\Http\Requests\Api\UploadAttachmentRequest;
use App\Models\AdmissionAttachment;
use App\Models\AdmissionFile;
use App\Models\DigitalAdmissionForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\Doctor $doctor */
        $doctor = $request->user();

        $admissions = AdmissionFile::query()
            ->with('patient')
            ->where('DoctorId', $doctor->Id)
            ->when($request->query('start_date'), fn ($query, $value) => $query->where('AdmDate', '>=', $value))
            ->when($request->query('end_date'), fn ($query, $value) => $query->where('AdmDate', '<=', $value))
            ->when($request->query('status'), fn ($query, $value) => $query->where('Posted', $value === 'closed' ? 1 : 0))
            ->orderBy('AdmDate', 'desc')
            ->limit(80)
            ->get()
            ->map(function (AdmissionFile $admission) {
                return [
                    'id' => $admission->Id,
                    'patient' => "{$admission->patient?->First} {$admission->patient?->Last}",
                    'date' => optional($admission->AdmDate)->toDateTimeString(),
                    'status' => $admission->Posted ? 'closed' : 'open',
                ];
            });

        return response()->json([
            'admissions' => $admissions,
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        /** @var \App\Models\Doctor $doctor */
        $doctor = $request->user();

        $admission = AdmissionFile::with(['patient', 'digitalForm'])
            ->findOrFail($id);

        if ($admission->DoctorId !== $doctor->Id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $history = AdmissionFile::query()
            ->where('PatientId', $admission->PatientId)
            ->orderBy('AdmDate', 'desc')
            ->limit(5)
            ->get()
            ->map(fn (AdmissionFile $record) => [
                'id' => $record->Id,
                'date' => optional($record->AdmDate)->toDateTimeString(),
                'status' => $record->Posted ? 'closed' : 'open',
            ]);

        $attachments = $admission->attachments()->orderBy('uploaded_at', 'desc')->get();

        return response()->json([
            'admission' => $admission,
            'patient' => $admission->patient,
            'history' => $history,
            'digital_form' => $admission->digitalForm,
            'attachments' => $attachments,
        ]);
    }

    public function saveForm(int $id, SaveDigitalFormRequest $request): JsonResponse
    {
        /** @var \App\Models\Doctor $doctor */
        $doctor = $request->user();

        $admission = AdmissionFile::findOrFail($id);

        if ($admission->DoctorId !== $doctor->Id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $form = DigitalAdmissionForm::updateOrCreate(
            ['admission_id' => $admission->Id],
            [
                'doctor_id' => $doctor->Id,
                'payload' => $request->payload,
                'strokes' => $request->strokes ?? [],
                'form_version' => $request->form_version ?? 'v1',
                'status' => $request->status ?? 'draft',
            ]
        );

        return response()->json([
            'form' => $form,
        ]);
    }

    public function uploadAttachment(int $id, UploadAttachmentRequest $request): JsonResponse
    {
        /** @var \App\Models\Doctor $doctor */
        $doctor = $request->user();

        $admission = AdmissionFile::findOrFail($id);

        if ($admission->DoctorId !== $doctor->Id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $file = $request->file('file');
        $path = Storage::disk('public')->putFile('admissions', $file);

        $attachment = AdmissionAttachment::create([
            'doctor_id' => $doctor->Id,
            'admission_id' => $admission->Id,
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'label' => $request->label,
            'uploaded_at' => now(),
        ]);

        return response()->json([
            'attachment' => $attachment,
        ]);
    }
}
