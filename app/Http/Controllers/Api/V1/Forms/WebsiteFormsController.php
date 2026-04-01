<?php

namespace App\Http\Controllers\Api\V1\Forms;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Forms\SubmitBecomeSpeakerRequest;
use App\Http\Requests\Forms\SubmitEntrepreneurCertificationRequest;
use App\Http\Requests\Forms\SubmitLeadershipCertificationRequest;
use App\Http\Requests\Forms\SubmitSmeBusinessStoryRequest;
use App\Models\BecomeSpeakerSubmission;
use App\Models\EntrepreneurCertificationSubmission;
use App\Models\FileModel;
use App\Models\LeadershipCertificationSubmission;
use App\Models\SmeBusinessStorySubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebsiteFormsController extends BaseApiController
{
    public function submitBecomeSpeaker(SubmitBecomeSpeakerRequest $request)
    {
        $data = $request->validated();

        Log::info('Become a speaker submission started', [
            'headers' => $request->headers->all(),
            'all' => $request->all(),
            'all_files' => array_keys($request->allFiles()),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'ip' => $request->ip(),
        ]);

        try {
            $storedImage = null;

            if ($request->hasFile('image') && $request->file('image') instanceof UploadedFile) {
                $storedImage = $this->storeWebsiteFormImage($request->file('image'));
            }

            $submission = BecomeSpeakerSubmission::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'city' => $data['city'],
                'linkedin_profile_url' => $data['linkedin_profile_url'],
                'company_name' => $data['company_name'],
                'brief_bio' => $data['brief_bio'],
                'topics_to_speak_on' => $data['topics_to_speak_on'],
                'image_file_id' => $storedImage?->id,
                'status' => 'new',
            ]);

            Log::info('Become a speaker submission stored successfully', [
                'submission_id' => $submission->id,
                'image_file_id' => $storedImage?->id,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Form submitted successfully.',
                'data' => [
                    'id' => $submission->id,
                    'first_name' => $submission->first_name,
                    'last_name' => $submission->last_name,
                    'email' => $submission->email,
                    'phone' => $submission->phone,
                    'city' => $submission->city,
                    'linkedin_profile_url' => $submission->linkedin_profile_url,
                    'company_name' => $submission->company_name,
                    'brief_bio' => $submission->brief_bio,
                    'topics_to_speak_on' => $submission->topics_to_speak_on,
                    'image_file_id' => $submission->image_file_id,
                    'image_url' => $storedImage ? url('/api/v1/files/' . $storedImage->id) : null,
                    'created_at' => optional($submission->created_at)?->toISOString(),
                ],
            ], 201);
        } catch (\Throwable $exception) {
            Log::error('Become a speaker submission failed', [
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'ip' => $request->ip(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Unable to submit form right now. Please try again later.',
                'data' => null,
            ], 500);
        }
    }

    public function submitSmeBusinessStory(SubmitSmeBusinessStoryRequest $request)
    {
        $data = $request->validated();

        Log::info('SME business story submission started', [
            'email' => $data['email'] ?? null,
            'contact_number' => $data['contact_number'] ?? null,
            'ip' => $request->ip(),
        ]);

        try {
            $submission = SmeBusinessStorySubmission::create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'contact_number' => $data['contact_number'],
                'business_name' => $data['business_name'],
                'company_introduction' => $data['company_introduction'],
                'co_founders_and_partners_details' => $data['co_founders_and_partners_details'] ?? null,
                'status' => 'new',
            ]);

            Log::info('SME business story submission stored successfully', [
                'submission_id' => $submission->id,
                'email' => $submission->email,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Form submitted successfully.',
                'data' => [
                    'id' => $submission->id,
                    'full_name' => $submission->full_name,
                    'email' => $submission->email,
                    'contact_number' => $submission->contact_number,
                    'business_name' => $submission->business_name,
                    'company_introduction' => $submission->company_introduction,
                    'co_founders_and_partners_details' => $submission->co_founders_and_partners_details,
                    'created_at' => optional($submission->created_at)?->toISOString(),
                ],
            ], 201);
        } catch (\Throwable $exception) {
            Log::error('SME business story submission failed', [
                'email' => $data['email'] ?? null,
                'contact_number' => $data['contact_number'] ?? null,
                'ip' => $request->ip(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Unable to submit form right now. Please try again later.',
                'data' => null,
            ], 500);
        }
    }

    public function submitLeadershipCertification(SubmitLeadershipCertificationRequest $request)
    {
        $data = $request->validated();

        Log::info('Leadership certification submission started', [
            'email' => $data['email'] ?? null,
            'contact_no' => $data['contact_no'] ?? null,
            'ip' => $request->ip(),
        ]);

        try {
            $submission = LeadershipCertificationSubmission::create([
                'full_name' => $data['full_name'],
                'business_name' => $data['business_name'],
                'email' => $data['email'],
                'contact_no' => $data['contact_no'],
                'status' => 'new',
            ]);

            Log::info('Leadership certification submission stored successfully', [
                'submission_id' => $submission->id,
                'email' => $submission->email,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Form submitted successfully.',
                'data' => [
                    'id' => $submission->id,
                    'full_name' => $submission->full_name,
                    'business_name' => $submission->business_name,
                    'email' => $submission->email,
                    'contact_no' => $submission->contact_no,
                    'created_at' => optional($submission->created_at)?->toISOString(),
                ],
            ], 201);
        } catch (\Throwable $exception) {
            Log::error('Leadership certification submission failed', [
                'email' => $data['email'] ?? null,
                'contact_no' => $data['contact_no'] ?? null,
                'ip' => $request->ip(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Unable to submit form right now. Please try again later.',
                'data' => null,
            ], 500);
        }
    }

    public function submitEntrepreneurCertification(SubmitEntrepreneurCertificationRequest $request)
    {
        $data = $request->validated();

        Log::info('Entrepreneur certification submission started', [
            'email' => $data['email'] ?? null,
            'contact_no' => $data['contact_no'] ?? null,
            'ip' => $request->ip(),
        ]);

        try {
            $submission = EntrepreneurCertificationSubmission::create([
                'full_name' => $data['full_name'],
                'business_name' => $data['business_name'],
                'email' => $data['email'],
                'contact_no' => $data['contact_no'],
                'status' => 'new',
            ]);

            Log::info('Entrepreneur certification submission stored successfully', [
                'submission_id' => $submission->id,
                'email' => $submission->email,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Form submitted successfully.',
                'data' => [
                    'id' => $submission->id,
                    'full_name' => $submission->full_name,
                    'business_name' => $submission->business_name,
                    'email' => $submission->email,
                    'contact_no' => $submission->contact_no,
                    'created_at' => optional($submission->created_at)?->toISOString(),
                ],
            ], 201);
        } catch (\Throwable $exception) {
            Log::error('Entrepreneur certification submission failed', [
                'email' => $data['email'] ?? null,
                'contact_no' => $data['contact_no'] ?? null,
                'ip' => $request->ip(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Unable to submit form right now. Please try again later.',
                'data' => null,
            ], 500);
        }
    }

    private function storeWebsiteFormImage(UploadedFile $file): FileModel
    {
        $disk = config('filesystems.default', 'public');
        $folder = 'uploads/' . now()->format('Y/m/d');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = (string) Str::uuid() . '.' . $extension;
        $path = $file->storeAs($folder, $filename, $disk);

        return FileModel::create([
            'uploader_user_id' => null,
            's3_key' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'width' => null,
            'height' => null,
            'duration' => null,
        ]);
    }
}
