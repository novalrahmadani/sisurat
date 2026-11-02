<?php

namespace App\Actions\Interviews;

use App\Models\InterviewForm;
use App\Models\InterviewSubmission;
use Illuminate\Support\LazyCollection;

class InterviewReport
{
    private readonly int $lastSubmissionId;

    public function __construct(private readonly InterviewForm $interview)
    {
        $this->lastSubmissionId = (int) $interview->submissions()->max('id');
    }

    /**
     * @return LazyCollection<int, InterviewSubmission>
     */
    public function submissions(): LazyCollection
    {
        return $this->interview->submissions()
            ->with('form')->select(['id', 'interview_form_id', 'wave', 'period', 'academic_year', 'campus', 'name', 'registration_number', 'study_program', 'submitted_at', 'answers'])
            ->where('id', '<=', $this->lastSubmissionId)
            ->lazyById(100);
    }

    /**
     * @return array<string, array{prompt: string, required: bool}>
     */
    public function questions(): array
    {
        $questions = [];

        foreach ($this->interview->questions as $question) {
            $key = self::questionKey($question['id'], $question['prompt'], (bool) $question['required']);
            $questions[$key] = ['prompt' => $question['prompt'], 'required' => (bool) $question['required']];
        }

        foreach ($this->submissions() as $submission) {
            foreach ($submission->answers as $answer) {
                $key = self::questionKey($answer['question_id'], $answer['question'], (bool) $answer['required']);
                $questions[$key] ??= ['prompt' => $answer['question'], 'required' => (bool) $answer['required']];
            }
        }

        return $questions;
    }

    /**
     * @return array<string, string>
     */
    public function answersByQuestion(InterviewSubmission $submission): array
    {
        $answers = [];

        foreach ($submission->answers as $answer) {
            $key = self::questionKey($answer['question_id'], $answer['question'], (bool) $answer['required']);
            $answers[$key] = $answer['answer'];
        }

        return $answers;
    }

    private static function questionKey(string $id, string $prompt, bool $required): string
    {
        return hash('sha256', json_encode([$id, $prompt, $required], JSON_THROW_ON_ERROR));
    }
}
