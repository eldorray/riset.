<script lang="ts">
    import { useHttp } from '@inertiajs/svelte';
    import { tick } from 'svelte';
    import Icon from '@/components/Icon.svelte';
    import AiCost from '@/components/AiCost.svelte';
    import projects from '@/routes/projects';
    import { errorMessage } from '@/lib/format';

    let { documentType, onselect }: { documentType: string; onselect: (title: string, idea: string) => void } = $props();
    type Turn = { question: string; answer: string; feedback?: string };
    type Titles = { feedback: string; titles: { title: string; reason: string }[]; summary: string };
    type Result = Partial<Titles> & { feedback: string; question?: string; ready?: boolean };
    // Sama dengan BrainstormController::MAX_TURNS; giliran terakhir selalu menghasilkan judul.
    const MAX_TURNS = 12;
    const FIRST_QUESTION = 'Bidang atau topik apa yang menarik bagi Anda? Ceritakan minat atau pengalaman Anda, meskipun idenya masih umum.';
    const discussion = useHttp<{ document_type: string; turns: Turn[]; titles: boolean }, Result>({ document_type: '', turns: [], titles: false });
    let started = $state(false);
    let turns = $state<Turn[]>([]);
    let question = $state(FIRST_QUESTION);
    let ready = $state(false);
    let answer = $state('');
    let titles = $state<Titles | null>(null);
    let error = $state('');
    let input = $state<HTMLTextAreaElement>();
    let selectedType = $state('');
    const lastTurn = $derived(turns.length + 1 >= MAX_TURNS);

    function reset() {
        started = false;
        turns = [];
        question = FIRST_QUESTION;
        ready = false;
        answer = '';
        titles = null;
        error = '';
    }

    $effect(() => {
        if (documentType !== selectedType && !discussion.processing) {
            selectedType = documentType;
            reset();
        }
    });

    async function focusInput() {
        await tick();
        input?.focus();
    }

    async function start() {
        started = true;
        await focusInput();
    }

    async function send(wantTitles: boolean) {
        if (discussion.processing || (!answer.trim() && !(wantTitles && turns.length))) return;
        error = '';
        const pending = answer.trim() ? [{ question, answer: answer.trim() }] : [];
        discussion.document_type = selectedType;
        discussion.titles = wantTitles;
        discussion.turns = [...$state.snapshot(turns).map(({ question, answer }) => ({ question, answer })), ...pending];
        try {
            const response = await discussion.post(projects.brainstorm().url);
            answer = '';
            if (response.titles && response.summary) {
                turns = [...turns, ...pending];
                titles = { feedback: response.feedback, titles: response.titles, summary: response.summary };
                return;
            }
            turns = [...turns, ...pending.map((turn) => ({ ...turn, feedback: response.feedback }))];
            question = response.question ?? question;
            ready = response.ready ?? false;
            await focusInput();
        } catch (cause) {
            error = errorMessage(cause, 'Diskusi dengan AI gagal. Coba lagi.');
        }
    }

    function submit(event: SubmitEvent) {
        event.preventDefault();
        void send(lastTurn);
    }

    async function keepDiscussing() {
        if (!titles) return;
        // Judul yang ditawarkan masuk riwayat agar AI tahu apa yang belum cocok.
        question = `Saya menawarkan: ${titles.titles.map((suggestion) => `“${suggestion.title}”`).join('; ')}. Apa yang belum cocok, atau arah mana yang ingin Anda dalami?`;
        ready = false;
        titles = null;
        await focusInput();
    }
</script>

<section class="card flex flex-col gap-4 p-7" aria-labelledby="brainstorm-title" aria-busy={discussion.processing}>
    <div class="flex flex-col gap-2">
        <span class="badge badge-ai self-start">Brainstorming AI</span>
        <h2 id="brainstorm-title" class="font-display text-[26px] font-medium">Bingung menentukan judul?</h2>
        <p class="text-sm leading-relaxed text-ink-2">Diskusikan ide Anda sedalam yang Anda mau. AI menanggapi, menjawab pertanyaan balik, dan menggali fokus penelitian. Minta 3 alternatif judul kapan saja.</p>
    </div>
    <AiCost inputCharacters={2000 + answer.length + turns.reduce((sum, turn) => sum + turn.question.length + turn.answer.length + (turn.feedback?.length ?? 0), 0)} outputWords={250} detail="Perkiraan setiap balasan AI. Makin panjang diskusi, makin banyak riwayat yang dibaca AI." />
    {#if !started}
        <button type="button" class="btn btn-secondary" onclick={start} disabled={!documentType}>Diskusi dengan AI</button>
        {#if !documentType}<p class="help">Pilih jenis tulisan pada form proyek untuk memulai.</p>{/if}
    {:else}
        <ol class="flex flex-col gap-4" aria-label="Riwayat diskusi" aria-live="polite">
            {#each turns as turn, i (i)}
                <li class="flex flex-col gap-2 border-t border-line pt-4 text-sm leading-relaxed">
                    <p class="font-semibold">{turn.question}</p>
                    <p class="whitespace-pre-wrap rounded-lg bg-paper p-3"><span class="font-semibold">Anda:</span> {turn.answer}</p>
                    {#if turn.feedback}<p class="whitespace-pre-line"><span class="font-semibold text-primary">AI:</span> {turn.feedback}</p>{/if}
                </li>
            {/each}
        </ol>
        {#if titles}
            <div class="flex flex-col gap-3 border-t border-line pt-4" aria-live="polite">
                <h3 class="font-semibold">3 alternatif judul untuk Anda</h3>
                <p class="text-sm leading-relaxed"><span class="font-semibold text-primary">Fokus:</span> {titles.feedback}</p>
                {#each titles.titles as suggestion, i (suggestion.title)}
                    <div class="flex flex-col gap-2 rounded-lg border border-line p-4">
                        <h4 class="font-medium">{i + 1}. {suggestion.title}</h4>
                        <p class="text-sm leading-relaxed text-ink-2">{suggestion.reason}</p>
                        <button type="button" class="btn btn-secondary self-start" onclick={() => titles && onselect(suggestion.title, titles.summary)}>Pakai judul</button>
                    </div>
                {/each}
                {#if turns.length < MAX_TURNS}
                    <button type="button" class="btn btn-ghost self-start" onclick={keepDiscussing}>Belum cocok? Lanjut diskusi</button>
                {/if}
            </div>
        {:else}
            <form class="flex flex-col gap-3 border-t border-line pt-4" onsubmit={submit}>
                <label for="brainstorm-answer" class="label leading-relaxed whitespace-pre-line">{question}</label>
                <textarea id="brainstorm-answer" bind:this={input} bind:value={answer} class="input min-h-28" rows="4" maxlength="2000" required disabled={discussion.processing} placeholder="Jawab, tanya balik, atau ceritakan keraguan Anda."></textarea>
                {#if lastTurn}
                    <p class="help">Ini giliran terakhir; AI akan langsung memberi 3 alternatif judul.</p>
                {:else if ready}
                    <p class="text-xs font-medium text-ok" role="status">Fokus sudah cukup jelas untuk judul. Lanjutkan diskusi atau lihat judul sekarang.</p>
                {/if}
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary grow" disabled={discussion.processing || !answer.trim()}>
                        {#if discussion.processing}<Icon name="spinner" size={16} /> AI sedang berpikir…{:else if lastTurn}Kirim & lihat judul{:else}Kirim{/if}
                    </button>
                    {#if !lastTurn}
                        <button type="button" class="btn {ready ? 'btn-primary' : 'btn-secondary'} grow" onclick={() => send(true)} disabled={discussion.processing || (!turns.length && !answer.trim())}>Lihat 3 judul</button>
                    {/if}
                </div>
            </form>
        {/if}
        {#if discussion.processing}<p role="status" class="help">AI sedang membaca diskusi dan menyusun tanggapan.</p>{/if}
        {#if error}<p role="alert" class="error">{error} Jawaban Anda tetap tersedia untuk dicoba ulang.</p>{/if}
        <button type="button" class="btn btn-ghost self-start" onclick={reset} disabled={discussion.processing}>Mulai ulang</button>
    {/if}
</section>
