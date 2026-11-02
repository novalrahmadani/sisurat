<?php

namespace App\Actions\Interviews;

use Dompdf\FontMetrics;

class InterviewAnswerTable
{
    private const int LinesPerRow = 8;

    public function __construct(
        private readonly FontMetrics $fontMetrics,
        private readonly float $questionWidth = 180,
        private readonly float $answerWidth = 261,
    ) {}

    /**
     * @param  array<int, array{question_id: string, question: string, required: bool, answer: string}>  $answers
     * @return array<int, array{number: int, continuation: bool, question: string, answer: string, required: bool, empty: bool}>
     */
    public function rows(array $answers): array
    {
        $rows = [];

        foreach ($answers as $index => $answer) {
            $questionChunks = array_chunk($this->lines($answer['question'], $this->questionWidth, true), self::LinesPerRow);
            $empty = trim($answer['answer']) === '';
            $answerChunks = array_chunk($this->lines($empty ? 'Tidak diisi oleh peserta.' : $answer['answer'], $this->answerWidth, false), self::LinesPerRow);

            for ($part = 0; $part < max(count($questionChunks), count($answerChunks)); $part++) {
                $rows[] = [
                    'number' => $index + 1,
                    'continuation' => $part > 0,
                    'question' => implode("\n", $questionChunks[$part] ?? []),
                    'answer' => implode("\n", $answerChunks[$part] ?? []),
                    'required' => $answer['required'],
                    'empty' => $empty,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function lines(string $text, float $width, bool $bold): array
    {
        $font = $this->fontMetrics->getFont('DejaVu Serif', $bold ? 'bold' : 'normal');
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [''] as $paragraph) {
            $line = '';

            foreach (preg_split('/(\h+)/u', $paragraph, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [''] as $word) {
                if ($line !== '' && $this->fontMetrics->getTextWidth($line.$word, $font, 9.2) > $width) {
                    $lines[] = rtrim($line);
                    $line = '';
                    $word = ltrim($word);
                }

                foreach (mb_str_split($word) as $character) {
                    if ($line !== '' && $this->fontMetrics->getTextWidth($line.$character, $font, 9.2) > $width) {
                        $lines[] = $line;
                        $line = '';
                    }

                    $line .= $character;
                }
            }

            $lines[] = rtrim($line);
        }

        return $lines;
    }
}
