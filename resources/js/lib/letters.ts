export type LetterType = 'permission' | 'assignment' | 'pkkmb' | 'general';
export type StandardLetterType = Exclude<LetterType, 'general'>;
export type InstitutionType = 'school' | 'pesantren';
export type ParticipationStatus = 'attended' | 'completed' | 'registered';
export type ParticipantResult = 'passed' | 'not_passed';
export const maxLettersPerBatch = 100;
export const maxLettersPerSubmission = 1_000;

export type MasterInstitution = {
    id: number;
    institution_type: InstitutionType;
    name: string;
    district: string;
    address: string | null;
};

export type Assignee = { name: string; office: string; district: string };

export type LetterItem = {
    institution_id: number | null;
    recipient_address: string;
    institution_type: InstitutionType;
    institution_name: string;
    recipient_district: string;
    recipient_position: string;
    recipient_name: string;
    activity_destination: string;
    participant_name: string;
    participant_nim: string;
    participant_study_program: string;
    participant_faculty: string;
    participant_result: ParticipantResult | '';
    participation_status: ParticipationStatus | '';
    assignees: Assignee[];
};

export const letterTypeLabels: Record<LetterType, string> = {
    permission: 'Permohonan izin sosialisasi',
    assignment: 'Surat tugas',
    pkkmb: 'Surat Keterangan PKKMB',
    general: 'Surat Umum',
};

export const institutionLabels: Record<InstitutionType, string> = {
    school: 'Sekolah / madrasah',
    pesantren: 'Pondok pesantren',
};

export const participationLabels: Record<ParticipationStatus, string> = {
    attended: 'Telah mengikuti PKKMB',
    completed: 'Telah menyelesaikan seluruh rangkaian PKKMB',
    registered: 'Terdaftar untuk mengikuti PKKMB',
};

export const participantResultLabels: Record<ParticipantResult, string> = {
    passed: 'LULUS',
    not_passed: 'TIDAK LULUS',
};

export function createLetterItem(
    institutionType: InstitutionType = 'school',
): LetterItem {
    return {
        institution_id: null,
        recipient_address: '',
        institution_type: institutionType,
        institution_name: '',
        recipient_district: '',
        recipient_position:
            institutionType === 'pesantren' ? 'Pimpinan' : 'Kepala',
        recipient_name: '',
        activity_destination:
            institutionType === 'pesantren'
                ? 'pondok-pondok pesantren'
                : 'sekolah-sekolah tingkat SMA, SMK, dan MA',
        participant_name: '',
        participant_nim: '',
        participant_study_program: '',
        participant_faculty: '',
        participant_result: 'passed',
        participation_status: 'attended',
        assignees: [{ name: '', office: 'Dosen', district: '' }],
    };
}

export function createAssignmentItem(
    institutionType: InstitutionType = 'school',
    district = '',
): LetterItem {
    const assignmentDistrict = normalizeDistrict(district);
    return {
        ...createLetterItem(institutionType),
        recipient_district: assignmentDistrict,
        activity_destination:
            institutionType === 'pesantren'
                ? 'Pondok Pesantren'
                : 'sekolah-sekolah tingkat SMA, SMK, dan MA',
        assignees: [
            { name: '', office: 'Dosen', district: assignmentDistrict },
        ],
    };
}

export function cloneLetterItems(items: LetterItem[]): LetterItem[] {
    return items.map((item) => ({
        institution_id: item.institution_id,
        recipient_address: item.recipient_address,
        institution_type: item.institution_type,
        institution_name: item.institution_name,
        recipient_district: item.recipient_district,
        recipient_position: item.recipient_position,
        recipient_name: item.recipient_name,
        activity_destination: item.activity_destination,
        participant_name: item.participant_name,
        participant_nim: item.participant_nim,
        participant_study_program: item.participant_study_program,
        participant_faculty: item.participant_faculty,
        participant_result: item.participant_result,
        participation_status: item.participation_status,
        assignees: item.assignees.map((person) => ({ ...person })),
    }));
}

export function normalizeDistrict(value: string): string {
    const district = value.trim().replace(/\s+/g, ' ');
    const districtName = district
        .replace(/^(?:kabupaten|kab\.?|kota)\s+/i, '')
        .trim();
    const districts: Record<string, string> = {
        OKUT: 'Kabupaten Ogan Komering Ulu Timur',
        'OKU TIMUR': 'Kabupaten Ogan Komering Ulu Timur',
        'OGAN KOMERING ULU TIMUR': 'Kabupaten Ogan Komering Ulu Timur',
        OKI: 'Kabupaten Ogan Komering Ilir',
        'OGAN KOMERING ILIR': 'Kabupaten Ogan Komering Ilir',
        OKU: 'Kabupaten Ogan Komering Ulu',
        'OGAN KOMERING ULU': 'Kabupaten Ogan Komering Ulu',
        OKUS: 'Kabupaten Ogan Komering Ulu Selatan',
        'OKU SELATAN': 'Kabupaten Ogan Komering Ulu Selatan',
        'OGAN KOMERING ULU SELATAN': 'Kabupaten Ogan Komering Ulu Selatan',
        'MUARA ENIM': 'Kabupaten Muara Enim',
        'WAY KANAN': 'Kabupaten Way Kanan',
        PRABUMULIH: 'Kota Prabumulih',
    };
    if (districts[districtName.toUpperCase()])
        return districts[districtName.toUpperCase()];
    if (/^kota\s+/i.test(district)) return `Kota ${districtName}`;
    if (/^(?:kabupaten|kab\.?)\s+/i.test(district))
        return `Kabupaten ${districtName}`;
    return district;
}

export function districtCategoryLabel(
    value: string | null | undefined,
): string {
    const district = normalizeDistrict(value ?? '');
    const labels: Record<string, string> = {
        'Kabupaten Ogan Komering Ulu Timur': 'OKUT',
        'Kabupaten Ogan Komering Ilir': 'OKI',
        'Kabupaten Ogan Komering Ulu': 'OKU',
        'Kabupaten Ogan Komering Ulu Selatan': 'OKUS',
        'Kabupaten Muara Enim': 'Muara Enim',
        'Kabupaten Way Kanan': 'Way Kanan',
        'Kota Prabumulih': 'Prabumulih',
    };
    return labels[district] ?? district;
}

export function normalizeInstitutionIdentity(
    institutionType: InstitutionType,
    name: string,
    district: string,
    address = '',
): string {
    const normalize = (value: string) =>
        value
            .normalize('NFKC')
            .toLowerCase()
            .replace(/[^\p{L}\p{N}]/gu, '');

    return `${institutionType}:${normalize(name)}:${normalize(normalizeDistrict(district))}:${normalize(address)}`;
}

export function normalizeHeader(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '');
}

export function parseCsv(text: string): string[][] {
    const firstLine = text.split(/\r?\n/, 1)[0] ?? '';
    const delimiter =
        (firstLine.match(/;/g) ?? []).length >
        (firstLine.match(/,/g) ?? []).length
            ? ';'
            : ',';
    const rows: string[][] = [];
    let row: string[] = [];
    let value = '';
    let quoted = false;
    for (let index = 0; index < text.length; index += 1) {
        const character = text[index];
        if (character === '"' && quoted && text[index + 1] === '"') {
            value += '"';
            index += 1;
        } else if (character === '"') {
            quoted = !quoted;
        } else if (character === delimiter && !quoted) {
            row.push(value.trim());
            value = '';
        } else if ((character === '\n' || character === '\r') && !quoted) {
            if (character === '\r' && text[index + 1] === '\n') index += 1;
            row.push(value.trim());
            if (row.some((cell) => cell !== '')) rows.push(row);
            row = [];
            value = '';
        } else {
            value += character;
        }
    }
    if (quoted)
        throw new Error(
            'Tanda kutip pada CSV belum ditutup. Periksa file sumber.',
        );
    row.push(value.trim());
    if (row.some((cell) => cell !== '')) rows.push(row);
    return rows;
}

function institutionTypeFor(
    value: string,
    fallback: InstitutionType,
): InstitutionType {
    if (/(pondok|ponpes|pesantren)/i.test(value)) return 'pesantren';
    if (/(school|sekolah|madrasah|\bSMA\b|\bSMK\b|\bMA\b|\bMAN\b)/i.test(value))
        return 'school';
    return fallback;
}

export function parseLetterRows(
    rows: string[][],
    type: StandardLetterType,
    destination: InstitutionType,
): LetterItem[] {
    const headerIndex = rows.findIndex((row) =>
        row.some((cell) => {
            const header = normalizeHeader(cell);
            return (
                ['namainstansi', 'kodetugas', 'namamahasiswa'].includes(
                    header,
                ) ||
                (header.includes('namasekolah') && header.includes('pondok'))
            );
        }),
    );
    if (headerIndex < 0)
        throw new Error(
            'Kolom data tidak ditemukan. Gunakan format CSV contoh atau sheet UNTK LPJ PMB.',
        );
    const headers = rows[headerIndex].map(normalizeHeader);
    const column = (row: string[], name: string) =>
        row[headers.indexOf(normalizeHeader(name))]?.trim() ?? '';
    const dataRows = rows
        .slice(headerIndex + 1)
        .filter((row) => row.some((cell) => cell?.trim()));
    if (!dataRows.length) throw new Error('File tidak berisi baris data.');

    if (type === 'pkkmb') {
        const required = ['nama_mahasiswa', 'nim', 'program_studi'];
        if (required.some((name) => !headers.includes(normalizeHeader(name)))) {
            throw new Error(
                'Data PKKMB memerlukan kolom nama_mahasiswa, nim, dan program_studi.',
            );
        }
        return dataRows.map((row, index) => {
            const item = createLetterItem();
            item.participant_name = column(row, 'nama_mahasiswa');
            item.participant_nim = column(row, 'nim');
            item.participant_study_program = column(row, 'program_studi');
            item.participant_faculty = column(row, 'fakultas');
            const result = normalizeHeader(column(row, 'hasil'));
            const resultOptions: Record<string, ParticipantResult> = {
                lulus: 'passed',
                passed: 'passed',
                tidaklulus: 'not_passed',
                notpassed: 'not_passed',
            };
            if (result && !Object.hasOwn(resultOptions, result)) {
                throw new Error(
                    `Hasil PKKMB pada baris ${headerIndex + index + 2} tidak valid. Gunakan LULUS, TIDAK LULUS, passed, atau not_passed.`,
                );
            }
            item.participant_result = resultOptions[result] ?? 'passed';
            const status = column(row, 'status').toLowerCase() || 'attended';
            if (!['attended', 'completed'].includes(status)) {
                throw new Error(
                    `Status PKKMB pada baris ${headerIndex + index + 2} harus attended atau completed. Status registered belum menyatakan mahasiswa telah mengikuti PKKMB.`,
                );
            }
            item.participation_status = status as ParticipationStatus;
            return item;
        });
    }

    if (type === 'assignment' && headers.includes('kodetugas')) {
        const required = [
            'tujuan_kegiatan',
            'nama_petugas',
            'dinas',
            'kabupaten',
        ];
        if (required.some((name) => !headers.includes(normalizeHeader(name)))) {
            throw new Error(
                'Kolom surat tugas belum lengkap. Unduh format CSV contoh.',
            );
        }
        const groups = new Map<string, LetterItem>();
        dataRows.forEach((row, index) => {
            const key = column(row, 'kode_tugas');
            if (!key)
                throw new Error(
                    `kode_tugas pada baris ${headerIndex + index + 2} belum diisi.`,
                );
            const target = column(row, 'tujuan_kegiatan');
            const institutionName = column(row, 'nama_instansi');
            const institutionType = institutionTypeFor(
                column(row, 'jenis_instansi') || institutionName || target,
                destination,
            );
            let item = groups.get(key);
            if (!item) {
                item = createLetterItem(institutionType);
                item.institution_name = institutionName;
                item.recipient_district = normalizeDistrict(
                    column(row, 'kabupaten_tujuan'),
                );
                item.activity_destination = target;
                item.recipient_address =
                    column(row, 'alamat_tujuan') || column(row, 'alamat');
                item.assignees = [];
                groups.set(key, item);
            } else if (
                item.institution_type !== institutionType ||
                item.activity_destination !== target ||
                item.institution_name !== institutionName ||
                item.recipient_district !==
                    normalizeDistrict(column(row, 'kabupaten_tujuan')) ||
                item.recipient_address !==
                    (column(row, 'alamat_tujuan') || column(row, 'alamat'))
            ) {
                throw new Error(
                    `Tujuan untuk kode_tugas ${key} berbeda antarbaris. Gunakan kode yang berbeda untuk setiap tujuan.`,
                );
            }
            item.assignees.push({
                name: column(row, 'nama_petugas'),
                office: column(row, 'dinas'),
                district: normalizeDistrict(column(row, 'kabupaten')),
            });
        });
        return Array.from(groups.values());
    }

    const institutionColumn = headers.findIndex(
        (name) =>
            name === 'namainstansi' ||
            (name.includes('namasekolah') && name.includes('pondok')),
    );
    if (institutionColumn < 0)
        throw new Error(
            'Kolom nama_instansi atau Nama Sekolah/Pondok tidak ditemukan.',
        );
    let currentDistrict = '';
    const items: LetterItem[] = [];
    for (const row of dataRows) {
        const district = column(row, 'kabupaten');
        if (district) currentDistrict = district;
        const institutionName = row[institutionColumn]?.trim();
        if (!institutionName) continue;
        const institutionType = institutionTypeFor(
            column(row, 'jenis_instansi') || institutionName,
            destination,
        );
        const item = createLetterItem(institutionType);
        item.institution_name = institutionName;
        item.recipient_district = normalizeDistrict(currentDistrict);
        item.recipient_address =
            column(row, 'alamat_tujuan') || column(row, 'alamat');
        item.recipient_position =
            column(row, 'jabatan_penerima') || item.recipient_position;
        item.recipient_name = column(row, 'nama_penerima');
        item.activity_destination = institutionName;
        items.push(item);
    }
    if (!items.length)
        throw new Error(
            'Tidak ada nama sekolah atau pondok pada file tersebut.',
        );
    return items;
}

export function csvTemplate(
    type: StandardLetterType,
    destination: InstitutionType,
): string {
    const isPondok = destination === 'pesantren';
    const institution = isPondok
        ? 'Pondok Pesantren Thawalib Darussalam Serasan'
        : 'MAN 1 Ogan Komering Ulu';
    const institutionType = isPondok ? 'Pondok' : 'Sekolah';
    if (type === 'pkkmb')
        return 'nama_mahasiswa,nim,program_studi,fakultas,hasil\nNama Mahasiswa,001234,Pendidikan Bahasa Inggris,Fakultas Ilmu Pendidikan,LULUS';
    if (type === 'assignment')
        return `kode_tugas,jenis_instansi,nama_instansi,kabupaten_tujuan,tujuan_kegiatan,nama_petugas,dinas,kabupaten,alamat_tujuan\nTUGAS1,${institutionType},${institution},OKU,${institution},Nama Petugas,Dosen,OKU,`;
    return `jenis_instansi,nama_instansi,kabupaten,jabatan_penerima,nama_penerima,alamat_tujuan\n${institutionType},${institution},OKU,${isPondok ? 'Pimpinan' : 'Kepala'},,`;
}
