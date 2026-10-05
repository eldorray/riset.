<script lang="ts">
    import { useHttp } from '@inertiajs/svelte';
    import { tick } from 'svelte';
    import Icon from '@/components/Icon.svelte';
    import AiCost from '@/components/AiCost.svelte';
    import projects from '@/routes/projects';
    import { errorMessage } from '@/lib/format';

    let { documentType, onselect }: { documentType: string; onselect: (title: string, idea: string) => void } = $props();
    type Turn = { question: string; answer: string; feedback?: string };
    type Result = { feedback: string; question?: string; titles?: { title: string; reason: string }[] };
    const discussion = useHttp<{ document_type: string; turns: Turn[] }, Result>({ document_type: '', turns: [] });
    let started = $state(false);
    let turns = $state<Turn[]>([]);
    let question = $state('Bidang atau topik apa yang menarik bagi Anda? Ceritakan minat atau pengalaman Anda, meskipun idenya masih umum.');
    let answer = $state('');
    let result = $state<Result | null>(null);
    let error = $state('');
    let input = $state<HTMLTextAreaElement>();
    let selectedType = $state('');

    function reset() {
        started = false;
        turns = [];
        question = 'Bidang atau topik apa yang menarik bagi Anda? Ceritakan minat atau pengalaman Anda, meskipun idenya masih umum.';
        answer = '';
        result = null;
        error = '';
    }

    $effect(() => {
        if (documentType !== selectedType && !discussion.processing) {
            selectedType = documentType;
            reset();
        }
    });

    async function start() {
        started = true;
        await tick();
        input?.focus();
    }

    async function send(event: SubmitEvent) {
        event.preventDefault();
        if (!answer.trim() || discussion.processing) return;
        error = '';
        const pending = { question, answer: answer.trim() };
        discussion.document_type = selectedType;
        discussion.turns = [...$state.snapshot(turns).map(({ question, answer }) => ({ question, answer })), pending];
        try {
            const response = await discussion.post(projects.brainstorm().url);
            turns = [...turns, { ...pending, feedback: response.feedback }];
            result = response;
            answer = '';
            if (response.question) question = response.question;
            await tick();
            input?.focus();
        } catch (cause) {
            error = errorMessage(cause, 'Diskusi dengan AI gagal. Coba lagi.');
        }
    }
</script>

<section class="card flex flex-col gap-4 p-7" aria-labelledby="brainstorm-title" aria-busy={discussion.processing}>
    <div class="flex flex-col gap-2">
        <span class="badge badge-ai self-start">Brainstorming AI</span>
        <h2 id="brainstorm-title" class="font-display text-[26px] font-medium">Bingung menentukan judul?</h2>
        <p class="text-sm leading-relaxed text-ink-2">Mari diskusikan ide Anda. AI akan membantu mempertajam fokus lewat 3 pertanyaan, lalu menawarkan 3 alternatif judul.</p>
    </div>
    <AiCost inputCharacters={2000 + answer.length + turns.reduce((sum, turn) => sum + turn.answer.length, 0)} outputWords={250} detail="Perkiraan setiap jawaban AI dalam diskusi judul." />
    {#if !started}
        <button type="button" class="btn btn-secondary" onclick={start} disabled={!documentType}>Diskusi dengan AI</button>
        {#if !documentType}<p class="help">Pilih jenis tulisan pada form proyek untuk memulai.</p>{/if}
    {:else}
        <ol class="flex flex-col gap-4" aria-label="Riwayat diskusi">
            {#each turns as turn, i}
                <li class="flex flex-col gap-2 border-t border-line pt-4 text-sm leading-relaxed">
                    <p class="font-semibold">{i + 1}. {turn.question}</p>
                    <p class="whitespace-pre-wrap rounded-lg bg-paper p-3"><span class="font-semibold">Anda:</span> {turn.answer}</p>
                    <p><span class="font-semibold text-primary">AI:</span> {turn.feedback}</p>
                </li>
            {/each}
        </ol>
        {#if result?.titles}
            <div class="flex flex-col gap-3" aria-live="polite">
                <h3 class="font-semibold">3 alternatif judul untuk Anda</h3>
                {#each result.titles as suggestion, i}
                    <div class="flex flex-col gap-2 rounded-lg border border-line p-4">
                        <h4 class="font-medium">{i + 1}. {suggestion.title}</h4>
                        <p class="text-sm leading-relaxed text-ink-2">{suggestion.reason}</p>
                        <button type="button" class="btn btn-secondary self-start" onclick={() => onselect(suggestion.title, turns.map((turn) => `${turn.question}\n${turn.answer}`).join('\n\n'))}>Pakai judul</button>
                    </div>
                {/each}
            </div>
        {:else}
            <form class="flex flex-col gap-3 border-t border-line pt-4" onsubmit={send}>
                <p class="text-xs text-ink-2" aria-live="polite">Pertanyaan {turns.length + 1} dari 3</p>
                <label for="brainstorm-answer" class="label leading-relaxed">{question}</label>
                <textarea id="brainstorm-answer" bind:this={input} bind:value={answer} class="input min-h-28" rows="4" maxlength="2000" required disabled={discussion.processing} placeholder="Ceritakan ide Anda. Belum tahu juga boleh."></textarea>
                <button type="submit" class="btn btn-primary" disabled={discussion.processing || !answer.trim()}>
                    {#if discussion.processing}<Icon name="spinner" size={16} /> AI sedang berpikir…{:else if turns.length === 2}Lihat 3 alternatif judul{:else}Kirim jawaban{/if}
                </button>
            </form>
        {/if}
        {#if discussion.processing}<p role="status" class="help">AI sedang membaca jawaban Anda dan menyusun feedback.</p>{/if}
        {#if error}<p role="alert" class="error">{error} Jawaban Anda tetap tersedia untuk dicoba ulang.</p>{/if}
        <button type="button" class="btn btn-ghost self-start" onclick={reset} disabled={discussion.processing}>Mulai ulang</button>
    {/if}
</section>
