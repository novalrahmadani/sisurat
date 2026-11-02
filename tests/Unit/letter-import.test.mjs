import assert from 'node:assert/strict';
import test from 'node:test';
import {
    cloneLetterItems,
    createLetterItem,
    csvTemplate,
    parseCsv,
    parseLetterRows,
} from '../../resources/js/lib/letters.ts';

test('imports mixed school and pondok PMB rows and carries merged kabupaten values forward', () => {
    const rows = [
        ['REKAP KUNJUNGAN PMB'],
        ['No.', 'Kabupaten', 'Nama Sekolah/Pondok'],
        ['1', 'OKU', 'MAN 1 Ogan Komering Ulu'],
        ['2', '', 'Pondok Pesantren Darussalam'],
        ['', 'OKI', ''],
        ['3', '', 'SMA Negeri 1 Kayuagung'],
    ];

    const items = parseLetterRows(rows, 'permission', 'school');

    assert.deepEqual(
        items.map((item) => ({
            institution: item.institution_name,
            district: item.recipient_district,
            type: item.institution_type,
            position: item.recipient_position,
            target: item.target_class,
        })),
        [
            {
                institution: 'MAN 1 Ogan Komering Ulu',
                district: 'Kabupaten Ogan Komering Ulu',
                type: 'school',
                position: 'Kepala',
                target: 'siswa kelas XII',
            },
            {
                institution: 'Pondok Pesantren Darussalam',
                district: 'Kabupaten Ogan Komering Ulu',
                type: 'pesantren',
                position: 'Pimpinan',
                target: 'santri',
            },
            {
                institution: 'SMA Negeri 1 Kayuagung',
                district: 'Kabupaten Ogan Komering Ilir',
                type: 'school',
                position: 'Kepala',
                target: 'siswa kelas XII',
            },
        ],
    );
});

test('preserves recipient overrides in a legacy permission CSV', () => {
    const csv =
        'jenis_instansi,nama_instansi,kabupaten,jabatan_penerima,nama_penerima,kelas_sasaran\nPondok,Al Hikmah,OKUS,Pengasuh,Ustaz Hasan,santri kelas akhir';

    const [item] = parseLetterRows(parseCsv(csv), 'permission', 'school');

    assert.equal(item.institution_type, 'pesantren');
    assert.equal(item.institution_name, 'Al Hikmah');
    assert.equal(
        item.recipient_district,
        'Kabupaten Ogan Komering Ulu Selatan',
    );
    assert.equal(item.recipient_position, 'Pengasuh');
    assert.equal(item.recipient_name, 'Ustaz Hasan');
    assert.equal(item.target_class, 'santri kelas akhir');
});

test('uses the selected pondok destination when an institution has no explicit type', () => {
    const rows = [
        ['nama_instansi', 'kabupaten'],
        ['Darussalam Serasan', 'OKUT'],
    ];

    const [item] = parseLetterRows(rows, 'permission', 'pesantren');

    assert.equal(item.institution_type, 'pesantren');
    assert.equal(item.recipient_position, 'Pimpinan');
    assert.equal(item.target_class, 'santri');
});

test('groups assignment staff by code and keeps school and pondok destinations separate', () => {
    const rows = [
        [
            'kode_tugas',
            'jenis_instansi',
            'nama_instansi',
            'kabupaten_tujuan',
            'tujuan_kegiatan',
            'nama_petugas',
            'dinas',
            'kabupaten',
        ],
        [
            'SEKOLAH1',
            'Sekolah',
            'MAN 1 OKU',
            'OKU',
            'MAN 1 OKU',
            'Fitria',
            'Dosen',
            'OKI',
        ],
        [
            'SEKOLAH1',
            'Sekolah',
            'MAN 1 OKU',
            'OKU',
            'MAN 1 OKU',
            'Suwandi',
            'Staf',
            'OKUS',
        ],
        [
            'PONDOK1',
            'Pondok',
            'Pondok Darussalam',
            'OKUT',
            'Pondok Darussalam',
            'Hasan',
            'Dosen',
            'OKUT',
        ],
    ];

    const items = parseLetterRows(rows, 'assignment', 'school');

    assert.equal(items.length, 2);
    assert.equal(items[0].institution_type, 'school');
    assert.equal(items[0].activity_destination, 'MAN 1 OKU');
    assert.equal(items[0].recipient_district, 'Kabupaten Ogan Komering Ulu');
    assert.deepEqual(items[0].assignees, [
        {
            name: 'Fitria',
            office: 'Dosen',
            district: 'Kabupaten Ogan Komering Ilir',
        },
        {
            name: 'Suwandi',
            office: 'Staf',
            district: 'Kabupaten Ogan Komering Ulu Selatan',
        },
    ]);
    assert.equal(items[1].institution_type, 'pesantren');
    assert.equal(items[1].activity_destination, 'Pondok Darussalam');
    assert.deepEqual(items[1].assignees, [
        {
            name: 'Hasan',
            office: 'Dosen',
            district: 'Kabupaten Ogan Komering Ulu Timur',
        },
    ]);
});

for (const [field, replacement] of [
    ['jenis_instansi', 'Pondok'],
    ['nama_instansi', 'SMA Negeri 2'],
    ['kabupaten_tujuan', 'OKI'],
    ['tujuan_kegiatan', 'Kunjungan ke SMA Negeri 2'],
]) {
    test(`rejects different ${field} values for the same assignment code`, () => {
        const header = [
            'kode_tugas',
            'jenis_instansi',
            'nama_instansi',
            'kabupaten_tujuan',
            'tujuan_kegiatan',
            'nama_petugas',
            'dinas',
            'kabupaten',
        ];
        const first = [
            'TUGAS1',
            'Sekolah',
            'MAN 1 OKU',
            'OKU',
            'MAN 1 OKU',
            'Fitria',
            'Dosen',
            'OKI',
        ];
        const second = [...first];
        second[header.indexOf(field)] = replacement;
        second[header.indexOf('nama_petugas')] = 'Suwandi';

        assert.throws(
            () =>
                parseLetterRows(
                    [header, first, second],
                    'assignment',
                    'school',
                ),
            /Tujuan untuk kode_tugas TUGAS1 berbeda antarbaris/,
        );
    });
}

test('rejects an assignment row without a grouping code', () => {
    const csv =
        'kode_tugas,tujuan_kegiatan,nama_petugas,dinas,kabupaten\n,MAN 1 OKU,Fitria,Dosen,OKI';

    assert.throws(
        () => parseLetterRows(parseCsv(csv), 'assignment', 'school'),
        /kode_tugas pada baris 2 belum diisi/,
    );
});

test('imports institutional spreadsheet rows as assignment destinations', () => {
    const rows = [
        ['Kabupaten', 'Nama Sekolah/Pondok'],
        ['OKUS', 'Pondok Pesantren Darussalam'],
        ['', 'SMK Negeri 1 Muaradua'],
    ];

    const items = parseLetterRows(rows, 'assignment', 'school');

    assert.deepEqual(
        items.map((item) => ({
            type: item.institution_type,
            institution: item.institution_name,
            destination: item.activity_destination,
            district: item.recipient_district,
            assignees: item.assignees,
        })),
        [
            {
                type: 'pesantren',
                institution: 'Pondok Pesantren Darussalam',
                destination: 'Pondok Pesantren Darussalam',
                district: 'Kabupaten Ogan Komering Ulu Selatan',
                assignees: [{ name: '', office: 'Dosen', district: '' }],
            },
            {
                type: 'school',
                institution: 'SMK Negeri 1 Muaradua',
                destination: 'SMK Negeri 1 Muaradua',
                district: 'Kabupaten Ogan Komering Ulu Selatan',
                assignees: [{ name: '', office: 'Dosen', district: '' }],
            },
        ],
    );
});

test('preserves leading zero NIM values and imports each PKKMB participation status', () => {
    const csv =
        'nama_mahasiswa,nim,program_studi,status\nAisyah,0000123,Pendidikan Bahasa Inggris,attended\nBudi,001024,Pendidikan Matematika,completed\nCitra,0000567,Pendidikan Ekonomi,registered\nDian,0000789,Pendidikan Matematika,';

    const items = parseLetterRows(parseCsv(csv), 'pkkmb', 'school');

    assert.deepEqual(
        items.map((item) => [
            item.participant_name,
            item.participant_nim,
            item.participant_study_program,
            item.participation_status,
        ]),
        [
            ['Aisyah', '0000123', 'Pendidikan Bahasa Inggris', 'attended'],
            ['Budi', '001024', 'Pendidikan Matematika', 'completed'],
            ['Citra', '0000567', 'Pendidikan Ekonomi', 'registered'],
            ['Dian', '0000789', 'Pendidikan Matematika', 'attended'],
        ],
    );
});

test('rejects a PKKMB status that would make the certificate state ambiguous', () => {
    const rows = [
        ['nama_mahasiswa', 'nim', 'program_studi', 'status'],
        ['Aisyah', '0000123', 'Pendidikan Bahasa Inggris', 'lulus'],
    ];

    assert.throws(
        () => parseLetterRows(rows, 'pkkmb', 'school'),
        /Status PKKMB pada baris 2 tidak valid/,
    );
});

test('rejects PKKMB data without the study program column', () => {
    const rows = [
        ['nama_mahasiswa', 'nim'],
        ['Aisyah', '0000123'],
    ];

    assert.throws(
        () => parseLetterRows(rows, 'pkkmb', 'school'),
        /memerlukan kolom nama_mahasiswa, nim, dan program_studi/,
    );
});

test('parses quoted commas, escaped quotes, and multiline institution names as one row', () => {
    const csv =
        'nama_instansi,nama_penerima\r\n"Pondok, ""Darussalam""\r\nSerasan","Ustaz Hasan"\r\n';

    const rows = parseCsv(csv);

    assert.deepEqual(rows, [
        ['nama_instansi', 'nama_penerima'],
        ['Pondok, "Darussalam"\r\nSerasan', 'Ustaz Hasan'],
    ]);
});

test('imports semicolon CSV with a UTF-8 BOM and preserves commas in cell values', () => {
    const csv =
        '\uFEFFnama_instansi;kabupaten;nama_penerima\r\n"MAN 1, Ogan Komering Ulu";OKU;"Dra. Fitria, M.Pd."\r\n\r\n';

    const [item] = parseLetterRows(parseCsv(csv), 'permission', 'school');

    assert.equal(item.institution_name, 'MAN 1, Ogan Komering Ulu');
    assert.equal(item.recipient_name, 'Dra. Fitria, M.Pd.');
    assert.equal(item.recipient_district, 'Kabupaten Ogan Komering Ulu');
});

test('rejects CSV with an unclosed quoted cell', () => {
    const csv = 'nama_instansi,kabupaten\n"MAN 1 OKU,OKU';

    assert.throws(() => parseCsv(csv), /Tanda kutip pada CSV belum ditutup/);
});

for (const [name, rows, message] of [
    [
        'an unknown header',
        [
            ['name', 'address'],
            ['MAN 1 OKU', 'OKU'],
        ],
        /Kolom data tidak ditemukan/,
    ],
    [
        'an empty data section',
        [['nama_instansi', 'kabupaten']],
        /File tidak berisi baris data/,
    ],
    [
        'only district rows',
        [
            ['nama_instansi', 'kabupaten'],
            ['', 'OKU'],
        ],
        /Tidak ada nama sekolah atau pondok/,
    ],
]) {
    test(`rejects a permission import containing ${String(name)}`, () => {
        assert.throws(
            () => parseLetterRows(rows, 'permission', 'school'),
            message,
        );
    });
}

for (const type of ['permission', 'assignment']) {
    for (const destination of ['school', 'pesantren']) {
        test(`provides an importable ${type} CSV example for ${destination}`, () => {
            const csv = csvTemplate(type, destination);

            const [item] = parseLetterRows(parseCsv(csv), type, destination);

            assert.equal(item.institution_type, destination);
            assert.ok(item.institution_name.length > 0);
            assert.equal(
                item.recipient_district,
                'Kabupaten Ogan Komering Ulu',
            );
        });
    }
}

test('provides an importable PKKMB CSV example with a leading zero NIM', () => {
    const csv = csvTemplate('pkkmb', 'school');

    const [item] = parseLetterRows(parseCsv(csv), 'pkkmb', 'school');

    assert.equal(item.participant_name, 'Nama Mahasiswa');
    assert.equal(item.participant_nim, '001234');
    assert.equal(item.participation_status, 'attended');
});

test('isolates recipient and assignee edits when copying a letter list', () => {
    const original = [
        createLetterItem('school'),
        createLetterItem('pesantren'),
    ];
    original[0].institution_name = 'MAN 1 OKU';
    original[0].assignees[0].name = 'Fitria';

    const copy = cloneLetterItems(original);
    copy[0].institution_name = 'SMA Negeri 1';
    copy[0].assignees[0].name = 'Suwandi';
    copy[1].assignees.push({
        name: 'Hasan',
        office: 'Dosen',
        district: 'OKUT',
    });

    assert.equal(original[0].institution_name, 'MAN 1 OKU');
    assert.equal(original[0].assignees[0].name, 'Fitria');
    assert.equal(original[1].assignees.length, 1);
});
