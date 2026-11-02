<?php

namespace App\Support;

use App\Models\LetterAssignee;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class OutgoingLetterAgenda
{
    public const TYPES = ['finance' => 'Surat Keuangan', 'permission' => 'Izin Sosialisasi', 'assignment' => 'Surat Tugas', 'pkkmb' => 'Keterangan PKKMB', 'general' => 'Surat Umum'];

    /** @param array<string, mixed> $filters */
    public function query(array $filters): Builder
    {
        $subject = $this->subjectExpression();
        $automatic = DB::table('letters as l')->join('letter_batches as b', 'b.id', '=', 'l.letter_batch_id')
            ->whereNull('l.deleted_at')->whereNull('b.deleted_at')
            ->selectRaw("l.id, 'sisurat' as source, b.id as batch_id, b.user_id as created_by, b.letter_date, l.letter_number, l.sequence_number, b.type, b.is_trial, COALESCE(NULLIF(l.institution_name, ''), NULLIF(l.participant_name, ''), NULLIF(l.recipient_name, ''), NULLIF(l.recipient_district, ''), NULLIF(l.recipient_position, ''), 'Penerima surat') as recipient, $subject as subject");
        $manual = DB::table('manual_outgoing_letters')->whereNull('deleted_at')
            ->selectRaw("id, 'manual' as source, NULL as batch_id, created_by, letter_date, letter_number, sequence_number, type, 0 as is_trial, recipient, subject");
        $query = DB::query()->fromSub($automatic->unionAll($manual), 'agenda');
        if ($filters['year'] !== null) {
            $query->whereYear('agenda.letter_date', $filters['year']);
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if ($filters[$key] !== '') {
                $query->where('agenda.letter_date', $operator, $filters[$key]);
            }
        }
        foreach (['source', 'type'] as $key) {
            if ($filters[$key] !== '') {
                $query->where('agenda.'.$key, $filters[$key]);
            }
        }
        if ($filters['status'] !== 'all') {
            $query->where('agenda.is_trial', $filters['status'] === 'trial');
        }
        if ($filters['q'] !== '') {
            $pattern = '%'.$filters['q'].'%';
            $query->where(function (Builder $search) use ($pattern): void {
                $search->where('agenda.recipient', 'like', $pattern)->orWhere('agenda.subject', 'like', $pattern)->orWhere('agenda.letter_number', 'like', $pattern)
                    ->orWhere(function (Builder $assigned) use ($pattern): void {
                        $assigned->where('agenda.source', 'sisurat')->whereExists(function (Builder $people) use ($pattern): void {
                            $people->selectRaw('1')->from('letter_assignees')->whereColumn('letter_assignees.letter_id', 'agenda.id')->where('letter_assignees.name', 'like', $pattern);
                        });
                    });
            });
        }

        return $query;
    }

    /** @return array{total: int, automatic: int, manual: int} */
    public function counts(Builder $query): array
    {
        return ['total' => (clone $query)->count(), 'automatic' => (clone $query)->where('agenda.source', 'sisurat')->count(), 'manual' => (clone $query)->where('agenda.source', 'manual')->count()];
    }

    public function ordered(Builder $query): Builder
    {
        return $query->orderBy('agenda.letter_date')->orderBy('agenda.sequence_number')->orderBy('agenda.source')->orderBy('agenda.id');
    }

    /** @param Collection<int, stdClass> $rows
     * @return Collection<int, array{id: int, source: string, batch_id: int|null, created_by: int, recipient: string, letter_date: string, subject: string, letter_number: string, type: string, is_trial: bool}>
     */
    public function present(Collection $rows): Collection
    {
        $ids = $rows->filter(fn (stdClass $row): bool => $row->source === 'sisurat' && $row->type === 'assignment')->pluck('id');
        $names = LetterAssignee::query()->whereIn('letter_id', $ids)->orderBy('sort_order')->get(['letter_id', 'name'])->groupBy('letter_id');

        return $rows->map(function (stdClass $row) use ($names): array {
            $recipient = (string) $row->recipient;
            if ($row->source === 'sisurat' && $row->type === 'assignment') {
                $assigned = $names->get($row->id)?->pluck('name')->filter()->implode(', ');
                if ($assigned) {
                    $recipient = $assigned.($recipient !== 'Penerima surat' ? ' - '.$recipient : '');
                }
            }
            $date = Carbon::parse((string) $row->letter_date)->toDateString();
            $formattedDate = Carbon::parse($date);
            $formattedDate->locale('id');
            $tokens = ['nomor_surat' => $row->letter_number, 'tanggal_surat' => $formattedDate->translatedFormat('j F Y'), 'nama_lembaga' => $recipient, 'nama_mahasiswa' => $recipient];
            $subject = preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/u', fn (array $match): string => (string) ($tokens[$match[1]] ?? $match[0]), (string) $row->subject) ?? (string) $row->subject;

            return ['id' => (int) $row->id, 'source' => (string) $row->source, 'batch_id' => $row->batch_id !== null ? (int) $row->batch_id : null, 'created_by' => (int) $row->created_by, 'recipient' => preg_replace('/\s+/u', ' ', $recipient) ?? $recipient, 'letter_date' => $date, 'subject' => preg_replace('/\s+/u', ' ', $subject) ?? $subject, 'letter_number' => (string) $row->letter_number, 'type' => (string) $row->type, 'is_trial' => (bool) $row->is_trial];
        });
    }

    /** @param array<string, mixed> $filters
     * @return \Generator<int, array<string, mixed>>
     */
    public function rows(array $filters): \Generator
    {
        $query = $this->ordered($this->query($filters));
        $offset = 0;
        do {
            $chunk = (clone $query)->offset($offset)->limit(200)->get();
            foreach ($this->present($chunk) as $row) {
                yield $row;
            }
            $offset += 200;
        } while ($chunk->count() === 200);
    }

    /**
     * @param  literal-string  $key
     * @param  literal-string  $field
     * @return literal-string
     */
    private function templateField(string $key, string $field): string
    {
        $json = "JSON_EXTRACT(b.content_templates, '$.templates.\"$key\".$field')";

        return DB::getDriverName() === 'mysql' ? 'JSON_UNQUOTE('.$json.')' : $json;
    }

    /** @return literal-string */
    private function subjectExpression(): string
    {
        $permission = "CASE WHEN l.institution_type = 'pesantren' THEN ".$this->templateField('permission-pesantren', 'subject').' ELSE '.$this->templateField('permission-school', 'subject').' END';
        $assignment = "CASE WHEN l.institution_type = 'pesantren' THEN ".$this->templateField('assignment-pesantren', 'title').' ELSE '.$this->templateField('assignment-school', 'title').' END';

        return "CASE b.type WHEN 'general' THEN COALESCE(NULLIF(b.subject, ''), 'Surat Umum') WHEN 'permission' THEN COALESCE(NULLIF(($permission), ''), 'Permohonan Izin Sosialisasi dan Promosi') WHEN 'assignment' THEN COALESCE(NULLIF(($assignment), ''), 'Surat Tugas') WHEN 'pkkmb' THEN COALESCE(NULLIF(".$this->templateField('pkkmb', 'title').", ''), 'Surat Keterangan PKKMB') ELSE 'Surat Keluar' END";
    }
}
