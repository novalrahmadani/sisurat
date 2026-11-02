export type LetterTemplateKey =
    | 'permission-school'
    | 'permission-pesantren'
    | 'assignment-school'
    | 'assignment-pesantren'
    | 'pkkmb';

export type LetterTemplateFields = Partial<
    Record<
        | 'title'
        | 'subject'
        | 'attachment'
        | 'basis'
        | 'opening_greeting'
        | 'introduction'
        | 'description'
        | 'closing'
        | 'closing_greeting',
        string
    >
>;

export type LetterContentTemplates = {
    version: number;
    templates: Partial<Record<LetterTemplateKey, LetterTemplateFields>>;
};

export type LetterTemplateTokens = Record<string, string>;

export function templateParagraphs(value: string | undefined): string[] {
    return (value ?? '')
        .replace(/\r\n?/g, '\n')
        .split(/\n[\t ]*\n/)
        .map((paragraph) => paragraph.trim())
        .filter(Boolean);
}
