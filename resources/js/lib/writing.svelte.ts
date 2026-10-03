export type WritingResult = {
    key: string;
    label: string;
    type: 'draft' | 'front' | 'outline';
    status: string;
    error?: string;
    limitations?: string;
};
export type WritingSuggestion = WritingResult & {
    run_id: number;
    index: number;
    kind: string;
    text: string;
    keywords?: string;
    sourceIds: number[];
};
export type WritingRun = {
    id: number;
    kind: string;
    key: string | null;
    references_count: number;
    status: string;
    done: number;
    total: number;
    label: string;
    stop_requested: boolean;
    error: string | null;
    results: WritingResult[];
    target_words: number | null;
    updated_at: string;
};
type WritingState = {
    run: WritingRun | null;
    suggestions: WritingSuggestion[];
};
type ReviewResponse = WritingState & {
    draft: Record<string, string>;
    ai_units: string[];
    front_matter: Record<string, { text: string; keywords: string }>;
};

async function request<T>(url: string, data?: unknown): Promise<T> {
    const token = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.slice(11) ?? '',
    );
    const response = await fetch(url, {
        method: data === undefined ? 'GET' : 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': token,
        },
        body: data === undefined ? undefined : JSON.stringify(data),
    });
    const body = await response.json().catch(() => null);
    if (!response.ok)
        throw new Error(
            body?.message ?? 'Tidak dapat menghubungi server. Coba lagi.',
        );
    return body as T;
}

export function createWriting(projectId: () => number) {
    let state = $state<WritingState>({ run: null, suggestions: [] });
    let error = $state('');
    let loading = $state(true);
    let sending = $state(false);
    let current = 0;
    let revision = 0;
    const active = $derived(
        state.run !== null && ['queued', 'running'].includes(state.run.status),
    );
    async function refresh() {
        const id = projectId();
        const sequence = ++revision;
        try {
            const next = await request<WritingState>(`/projects/${id}/writing`);
            if (id === current && sequence === revision) {
                state = next;
                error = '';
            }
        } catch (e) {
            if (id === current && sequence === revision)
                error = (e as Error).message;
        } finally {
            if (id === current && sequence === revision) loading = false;
        }
    }
    $effect(() => {
        current = projectId();
        state = { run: null, suggestions: [] };
        loading = true;
        void refresh();
        const timer = setInterval(() => void refresh(), 3000);
        return () => {
            clearInterval(timer);
            current = 0;
            revision++;
        };
    });
    return {
        get run() {
            return state.run;
        },
        get suggestions() {
            return state.suggestions;
        },
        get active() {
            return active;
        },
        get busy() {
            return active || sending || loading;
        },
        get error() {
            return error;
        },
        refresh,
        async start(data: Record<string, unknown>) {
            sending = true;
            try {
                await request(`/projects/${projectId()}/writing`, data);
                await refresh();
            } finally {
                sending = false;
            }
        },
        async stop() {
            revision++;
            if (state.run)
                state = await request<WritingState>(
                    `/projects/${projectId()}/writing/${state.run.id}/stop`,
                    {},
                );
        },
        async review(
            suggestion: WritingSuggestion,
            action: 'accept' | 'discard',
        ) {
            revision++;
            const result = await request<ReviewResponse>(
                `/projects/${projectId()}/writing/${suggestion.run_id}/review`,
                { index: suggestion.index, action },
            );
            state = result;
            return result;
        },
    };
}
