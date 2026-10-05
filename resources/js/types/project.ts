export type Option = { value: string; label: string };

export type ProjectSummary = {
    id: number;
    title: string;
    document_type: Option;
    citation_style: Option | null;
    docx_template_id: number | null;
    references_count: number;
    chapters: number;
    units: number;
    filled: number;
    front_parts: number;
    front_filled: number;
    design_ready: boolean;
    has_data: boolean;
    literature_study: boolean;
    created_at: string | null;
    updated_at: string | null;
};

export type Section = { id: string; title: string };
export type Chapter = { id: string; title: string; sections: Section[] };

export type UnitKind = 'literatur' | 'metode' | 'empiris';
export type Unit = {
    id: string;
    number: string;
    title: string;
    level: 1 | 2;
    kind: UnitKind;
};

export type Segment = { text: string; italic: boolean };

export type ReferenceMetadata = {
    open_access_url?: string;
    type?: string;
    authors?: string[];
    year?: string;
    publication?: string;
    volume?: string;
    issue?: string;
    pages?: string;
    publisher?: string;
    doi?: string;
    keywords?: string[];
};

export type ExportFormat = {
    name: string;
    table_of_contents: boolean;
    title_page: boolean;
    citation_style: string;
    font: string;
};
