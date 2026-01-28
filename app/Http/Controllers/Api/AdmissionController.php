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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdmissionController extends Controller
{
    private const DEFAULT_PEN_COLOR = '#38bdf8';
    private const DEFAULT_PEN_WIDTH = 3;
    private const DEFAULT_ERASER_WIDTH = 48;

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\Doctor $doctor */
        $doctor = $request->user();

        $admissions = AdmissionFile::query()
            ->with('patient')
            ->where('DoctorId', $doctor->Id)
            ->when($request->query('start_date'), fn ($query, $value) => $query->where('AdmDate', '>=', $value))
            ->when($request->query('end_date'), fn ($query, $value) => $query->where('AdmDate', '<=', $value))
            ->when($request->query('status'), fn ($query, $value) => $query->where('Closed', $value === 'closed' ? 1 : 0))
            ->orderBy('AdmDate', 'desc')
            ->limit(80)
            ->get()
            ->map(function (AdmissionFile $admission) {
                return [
                    'id' => $admission->Id,
                    'Patient' => "{$admission->Patient?->First} {$admission->Patient?->Last}",
                    'AdmDate' => optional($admission->AdmDate)->toDateTimeString(),
                    'Status' => $admission->Closed ? 'closed' : 'open',
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

        $admission = AdmissionFile::with(['Patient', 'DigitalForm'])
            ->findOrFail($id);

        $history = AdmissionFile::with('doctor')
            ->where('PatientId', $admission->PatientId)
            ->orderBy('AdmDate', 'desc')
            ->limit(5)
            ->get()
            ->map(fn (AdmissionFile $record) => [
                'id' => $record->Id,
                'admDate' => optional($record->AdmDate)->toDateTimeString(),
                'status' => $record->Closed ? 'closed' : 'open',
                'doctorId' => $record->DoctorId,
                'doctorName' => optional($record->doctor)->FullName,
            ]);

        $attachments = $admission->attachments()
            ->orderBy('UploadedAt', 'desc')
            ->get()
            ->map(function (AdmissionAttachment $attachment) {
                return [
                    'id' => $attachment->getKey(),
                    'Path' => $attachment->Path,
                    'Url' => Storage::url($attachment->Path),
                    'Label' => $attachment->Label,
                    'UploadedAt' => optional($attachment->UploadedAt)->toDateTimeString(),
                ];
            });

        return response()->json([
            'Admission' => $admission,
            'Patient' => $admission->Patient,
            'History' => $history,
            'DigitalForm' => $admission->DigitalForm,
            'Attachments' => $attachments,
        ]);
    }

    public function saveForm(int $id, SaveDigitalFormRequest $request): JsonResponse
    {
        /** @var \App\Models\Doctor $doctor */
        $doctor = $request->user();
    
        // Fetch the admission
        $admission = AdmissionFile::findOrFail($id);
    
        // Check if the logged-in doctor owns this admission
        if ($admission->DoctorId !== $doctor->Id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($admission->Closed) {
            return response()->json(['message' => 'Admission is closed'], 403);
        }
    
        // Find or create the form
        $form = DigitalAdmissionForm::firstOrNew(['AdmissionId' => $admission->Id]);
        
        // Set attributes explicitly
        $form->DoctorId = $doctor->Id;
        $form->Payload = $request->Payload;
        $sanitizedStrokes = collect($request->Strokes ?? [])
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
        $form->Strokes = $sanitizedStrokes;
        $form->FormVersion = $request->FormVersion ?? 'v1';
        $form->Status = $request->Status ?? 'draft';
        
        // Force save
        $form->save();
    
        return response()->json([
            'Form' => $form->fresh(), // Refresh to get the actual saved data
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

        if ($admission->Closed) {
            return response()->json(['message' => 'Admission is closed'], 403);
        }

        Log::info('Admission attachment upload requested', [
            'doctor_id' => $doctor->Id,
            'admission_id' => $admission->Id,
            'has_file' => $request->hasFile('File'),
        ]);

        try {
            $file = $request->file('File');
            $Path = Storage::disk('public')->putFile('admissions', $file);

            $attachment = AdmissionAttachment::create([
                'DoctorId' => $doctor->Id,
                'AdmissionId' => $admission->Id,
                'Path' => $Path,
                'Mime' => $file->getClientMimeType(),
                'Label' => $request->Label,
                'UploadedAt' => now(),
            ]);

            $attachmentData = [
                'id' => $attachment->getKey(),
                'Path' => $attachment->Path,
                'Url' => Storage::url($attachment->Path),
                'Label' => $attachment->Label,
                'UploadedAt' => optional($attachment->UploadedAt)->toDateTimeString(),
            ];

            Log::info('Admission attachment uploaded', [
                'attachment_id' => $attachment->getKey(),
                'doctor_id' => $doctor->Id,
                'admission_id' => $admission->Id,
                'path' => $attachment->Path,
            ]);

            return response()->json([
                'Attachment' => $attachmentData,
            ]);
        } catch (\Throwable $error) {
            Log::error('Admission attachment upload failed', [
                'doctor_id' => $doctor->Id,
                'admission_id' => $admission->Id,
                'error' => $error->getMessage(),
                'trace' => $error->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Unable to upload attachment'], 500);
        }

    }

    public function deleteAttachment(int $id, int $attachmentId, Request $request): JsonResponse
    {
        /** @var \App\Models\Doctor $doctor */
        $doctor = $request->user();

        $admission = AdmissionFile::findOrFail($id);

        if ($admission->DoctorId !== $doctor->Id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($admission->Closed) {
            return response()->json(['message' => 'Admission is closed'], 403);
        }

        $attachment = AdmissionAttachment::findOrFail($attachmentId);

        if ($attachment->AdmissionId !== $admission->Id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (Storage::disk('public')->exists($attachment->Path)) {
            Storage::disk('public')->delete($attachment->Path);
        }

        $attachment->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
