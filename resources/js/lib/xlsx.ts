const workbookRelationshipNamespace =
    'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

type ZipEntry = {
    compressedSize: number;
    compressionMethod: number;
    localHeaderOffset: number;
};

function parseXml(bytes: Uint8Array): XMLDocument {
    const document = new DOMParser().parseFromString(
        new TextDecoder('utf-8').decode(bytes),
        'application/xml',
    );

    if (document.getElementsByTagName('parsererror').length > 0) {
        throw new Error('Struktur XML di dalam file XLSX tidak valid.');
    }

    return document;
}

function findZipEntries(buffer: ArrayBuffer): Map<string, ZipEntry> {
    const bytes = new DataView(buffer);
    const minimumOffset = Math.max(0, buffer.byteLength - 65_557);
    let endRecordOffset = -1;

    for (
        let offset = buffer.byteLength - 22;
        offset >= minimumOffset;
        offset -= 1
    ) {
        if (bytes.getUint32(offset, true) === 0x06054b50) {
            endRecordOffset = offset;
            break;
        }
    }

    if (endRecordOffset < 0) {
        throw new Error('File ini bukan workbook XLSX yang valid.');
    }

    const entryCount = bytes.getUint16(endRecordOffset + 10, true);
    const centralDirectoryOffset = bytes.getUint32(endRecordOffset + 16, true);
    const entries = new Map<string, ZipEntry>();
    let offset = centralDirectoryOffset;

    for (let entryIndex = 0; entryIndex < entryCount; entryIndex += 1) {
        if (bytes.getUint32(offset, true) !== 0x02014b50) {
            throw new Error('Daftar isi workbook XLSX tidak dapat dibaca.');
        }

        const compressionMethod = bytes.getUint16(offset + 10, true);
        const compressedSize = bytes.getUint32(offset + 20, true);
        const fileNameLength = bytes.getUint16(offset + 28, true);
        const extraLength = bytes.getUint16(offset + 30, true);
        const commentLength = bytes.getUint16(offset + 32, true);
        const localHeaderOffset = bytes.getUint32(offset + 42, true);
        const fileNameBytes = new Uint8Array(
            buffer,
            offset + 46,
            fileNameLength,
        );
        const fileName = new TextDecoder('utf-8').decode(fileNameBytes);

        entries.set(fileName, {
            compressedSize,
            compressionMethod,
            localHeaderOffset,
        });

        offset += 46 + fileNameLength + extraLength + commentLength;
    }

    return entries;
}

async function readZipEntry(
    buffer: ArrayBuffer,
    entries: Map<string, ZipEntry>,
    path: string,
): Promise<Uint8Array> {
    const entry = entries.get(path);

    if (!entry) {
        throw new Error('Workbook tidak memiliki bagian ' + path + '.');
    }

    const view = new DataView(buffer);
    const localOffset = entry.localHeaderOffset;

    if (view.getUint32(localOffset, true) !== 0x04034b50) {
        throw new Error('Bagian XLSX tidak dapat dibaca.');
    }

    const fileNameLength = view.getUint16(localOffset + 26, true);
    const extraLength = view.getUint16(localOffset + 28, true);
    const contentOffset = localOffset + 30 + fileNameLength + extraLength;
    const compressed = new Uint8Array(
        buffer,
        contentOffset,
        entry.compressedSize,
    );

    if (entry.compressionMethod === 0) {
        return compressed;
    }

    if (
        entry.compressionMethod !== 8 ||
        typeof DecompressionStream === 'undefined'
    ) {
        throw new Error(
            'Browser ini belum mendukung pembacaan XLSX terkompresi.',
        );
    }

    const compressedBuffer = compressed.slice().buffer as ArrayBuffer;
    const stream = new Blob([compressedBuffer])
        .stream()
        .pipeThrough(
            new DecompressionStream('deflate-raw' as CompressionFormat),
        );

    return new Uint8Array(await new Response(stream).arrayBuffer());
}

function resolveWorkbookPath(target: string): string {
    const normalizedTarget = target.replaceAll('\\', '/');

    if (normalizedTarget.startsWith('/')) {
        return normalizedTarget.slice(1);
    }

    if (normalizedTarget.startsWith('xl/')) {
        return normalizedTarget;
    }

    return 'xl/' + normalizedTarget;
}

function columnIndex(reference: string): number {
    const letters = reference.match(/^[A-Z]+/i)?.[0]?.toUpperCase();

    if (!letters) {
        return -1;
    }

    let index = 0;

    for (const letter of letters) {
        index = index * 26 + letter.charCodeAt(0) - 64;
    }

    return index - 1;
}

function textFromXml(element: Element | null): string {
    if (!element) {
        return '';
    }

    return Array.from(element.getElementsByTagNameNS('*', 't'))
        .map((textNode) => textNode.textContent ?? '')
        .join('');
}

function readWorksheetRows(
    document: XMLDocument,
    sharedStrings: string[],
): string[][] {
    return Array.from(document.getElementsByTagNameNS('*', 'row')).map(
        (row) => {
            const values: string[] = [];

            for (const cell of Array.from(
                row.getElementsByTagNameNS('*', 'c'),
            )) {
                const index = columnIndex(cell.getAttribute('r') ?? '');

                if (index < 0) {
                    continue;
                }

                const type = cell.getAttribute('t');
                const value =
                    cell.getElementsByTagNameNS('*', 'v')[0]?.textContent ?? '';
                let cellText = value;

                if (type === 's') {
                    cellText = sharedStrings[Number(value)] ?? '';
                } else if (type === 'inlineStr') {
                    cellText = textFromXml(
                        cell.getElementsByTagNameNS('*', 'is')[0] ?? null,
                    );
                }

                values[index] = cellText.trim();
            }

            return values;
        },
    );
}

export async function readXlsxSheet(
    file: File,
    requestedSheetName?: string,
    fallbackToFirstSheet = false,
): Promise<string[][]> {
    const buffer = await file.arrayBuffer();
    const entries = findZipEntries(buffer);
    const workbook = parseXml(
        await readZipEntry(buffer, entries, 'xl/workbook.xml'),
    );
    const relationships = parseXml(
        await readZipEntry(buffer, entries, 'xl/_rels/workbook.xml.rels'),
    );
    const relationshipTargets = new Map<string, string>();

    for (const relationship of Array.from(
        relationships.getElementsByTagNameNS('*', 'Relationship'),
    )) {
        relationshipTargets.set(
            relationship.getAttribute('Id') ?? '',
            relationship.getAttribute('Target') ?? '',
        );
    }

    const requestedName = requestedSheetName?.trim().toLowerCase();
    const sheets = Array.from(workbook.getElementsByTagNameNS('*', 'sheet'));
    const worksheet =
        (requestedName
            ? sheets.find(
                  (sheet) =>
                      (sheet.getAttribute('name') ?? '')
                          .trim()
                          .toLowerCase() === requestedName,
              )
            : sheets[0]) ?? (fallbackToFirstSheet ? sheets[0] : undefined);

    if (!worksheet) {
        throw new Error(
            'Sheet "' + requestedSheetName + '" tidak ditemukan di workbook.',
        );
    }

    const relationshipId =
        worksheet.getAttributeNS(workbookRelationshipNamespace, 'id') ??
        worksheet.getAttribute('r:id') ??
        '';
    const target = relationshipTargets.get(relationshipId);

    if (!target) {
        throw new Error(
            'Lokasi sheet tujuan di dalam workbook tidak ditemukan.',
        );
    }

    let sharedStrings: string[] = [];

    if (entries.has('xl/sharedStrings.xml')) {
        const sharedStringsDocument = parseXml(
            await readZipEntry(buffer, entries, 'xl/sharedStrings.xml'),
        );
        sharedStrings = Array.from(
            sharedStringsDocument.getElementsByTagNameNS('*', 'si'),
        ).map((item) => textFromXml(item));
    }

    const worksheetDocument = parseXml(
        await readZipEntry(buffer, entries, resolveWorkbookPath(target)),
    );

    return readWorksheetRows(worksheetDocument, sharedStrings);
}
