<?php

namespace App\Http\Controllers\Api\V1\Forms;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Forms\SubmitBecomeMentorRequest;
use App\Http\Resources\BecomeMentorSubmissionResource;
use App\Models\BecomeMentorSubmission;
use Illuminate\Support\Facades\Log;

class BecomeMentorController extends BaseApiController
{
    public function submit(SubmitBecomeMentorRequest $request)
    {
        $data = $request->validated();

        Log::info('Mentor form submission started', [
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'ip' => $request->ip(),
        ]);

        try {
            $recentDuplicateExists = BecomeMentorSubmission::query()
                ->where('email', $data['email'])
                ->where('phone', $data['phone'])
                ->where('created_at', '>=', now()->subMinutes(10))
                ->exists();

            if ($recentDuplicateExists) {
                Log::warning('Mentor form submission blocked as duplicate', [
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'A similar submission was received recently. Please try again after some time.',
                    'data' => null,
                ], 429);
            }

            $submission = BecomeMentorSubmission::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'city' => $data['city'],
                'linkedin_profile' => $data['linkedin_profile'],
                'status' => 'new',
            ]);

            Log::info('Mentor form submission stored successfully', [
                'submission_id' => $submission->id,
                'email' => $submission->email,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Mentor form submitted successfully.',
                'data' => new BecomeMentorSubmissionResource($submission),
            ], 201);
        } catch (\Throwable $exception) {
            Log::error('Mentor form submission failed', [
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'ip' => $request->ip(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Unable to submit mentor form right now. Please try again later.',
                'data' => null,
            ], 500);
        }
    }
}
