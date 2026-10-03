<script lang="ts">
    import AppLogo from '@/components/AppLogo.svelte';
    import { onMount } from 'svelte';
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Icon from '@/components/Icon.svelte';
    import type { IconName } from '@/components/Icon.svelte';

    let { plans, whatsapp }: { plans: { id: number; name: string; price: number; credits: number }[]; whatsapp: string } = $props();
    const user = $derived(page.props.auth.user);
    const startUrl = $derived(user ? '/projects' : '/masuk');
    const featuredPlanId = $derived(plans.find(plan => plan.price === 39000)?.id);
    const startLabel = $derived(user ? 'Buka proyek saya' : 'Mulai menulis');
    const money = (value: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
    onMount(() => {
        const root = document.documentElement;
        root.classList.add('landing-scroll');
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
        const elements = Array.from(document.querySelectorAll<HTMLElement>('[data-reveal]'));
        let observer: IntersectionObserver | undefined;
        if (!reduce.matches && 'IntersectionObserver' in window) {
            observer = new IntersectionObserver((entries) => {
                for (const entry of entries) {
                    if (!entry.isIntersecting) continue;
                    (entry.target as HTMLElement).removeAttribute('data-pending');
                    observer?.unobserve(entry.target);
                }
            }, { threshold: 0.1 });
            for (const element of elements) {
                if (element.getBoundingClientRect().top < window.innerHeight) continue;
                element.setAttribute('data-pending', '');
                observer.observe(element);
            }
        }
        const showAll = () => {
            if (!reduce.matches) return;
            observer?.disconnect();
            elements.forEach(element => element.removeAttribute('data-pending'));
        };
        reduce.addEventListener('change', showAll);
        return () => {
            observer?.disconnect();
            reduce.removeEventListener('change', showAll);
            root.classList.remove('landing-scroll');
        };
    });

    const features: { icon: IconName; title: string; description: string }[] = [
        { icon: 'pen', title: 'Temukan arah tulisan', description: 'Diskusikan ide dengan AI, pertajam fokus penelitian, lalu pilih dari tiga alternatif judul.' },
        { icon: 'book', title: 'Bawa sumber Anda', description: 'Cari referensi atau unggah artikel PDF dan Word. AI membantu mengisi metadata dan ringkasan untuk Anda periksa.' },
        { icon: 'outline', title: 'Susun kerangka yang jelas', description: 'Mulai dari struktur contoh atau bantuan AI. Atur bab dan subbab sesuai kebutuhan tulisan Anda.' },
        { icon: 'list', title: 'Hubungkan tulisan dengan sumber', description: 'Pilih referensi untuk draf. Sitasi menghubungkan klaim yang didukung catatan dengan sumbernya.' },
        { icon: 'check', title: 'Tetap pegang kendali', description: 'Usulan AI ditandai untuk ditinjau. Terima, sunting, atau buang; Anda yang menentukan isi akhir.' },
        { icon: 'download', title: 'Lanjutkan di Word', description: 'Ekspor naskah, sitasi, dan daftar pustaka ke .docx. Pilih format institusi yang tersedia di aplikasi.' },
    ];
    const steps = [
        { title: 'Mulai dari ide & sumber', text: 'Buat proyek, tentukan jenis tulisan, dan kumpulkan referensi yang relevan.' },
        { title: 'Susun, tulis, lalu tinjau', text: 'Bangun kerangka, tulis per bagian, dan periksa usulan AI beserta sitasinya.' },
        { title: 'Rapikan & ekspor', text: 'Lengkapi naskah, pilih format Word, dan unduh dokumen untuk penyuntingan akhir.' },
    ];
    const faqs = [
        { question: 'Riset bisa digunakan untuk tulisan apa?', answer: 'Riset mendukung skripsi, tesis, karya ilmiah, dan artikel. Jenis tulisan menentukan struktur yang disiapkan untuk proyek Anda.' },
        { question: 'Apakah setiap tulisan AI sudah benar?', answer: 'Hasil AI adalah usulan yang perlu Anda periksa. Tinjau isi, sitasi, dan kesesuaiannya dengan artikel asli serta panduan kampus atau jurnal sebelum digunakan.' },
        { question: 'Apakah AI dapat membaca artikel yang saya punya?', answer: 'Anda dapat mengunggah PDF dengan teks atau Word (.docx), maksimal 15 MB. PDF hasil scan memerlukan OCR terlebih dahulu. Artikel dari internet perlu dapat diakses; akses terbuka tidak selalu menjamin teksnya berhasil diekstrak.' },
        { question: 'Bagaimana sistem kreditnya?', answer: 'Kredit digunakan untuk bantuan AI, termasuk diskusi judul, pembacaan artikel, dan penulisan. Pemakaian bergantung pada panjang bahan serta jawaban AI. Perkiraan ditampilkan sebelum tindakan; jumlah akhir mengikuti pemakaian aktual.' },
        { question: 'Bagaimana mengaktifkan paket?', answer: 'Masuk, buka Paket & Kredit, lalu ajukan paket. Ikuti instruksi transfer dan konfirmasi melalui WhatsApp admin yang tersedia di halaman tersebut. Akses aktif setelah pembayaran diverifikasi admin.' },
        { question: 'Apakah saya tetap bisa menulis tanpa paket aktif?', answer: 'Ya. Menulis manual, melihat naskah, dan mengekspor Word tetap tersedia. Paket aktif diperlukan untuk bantuan AI. Kredit bulanan berakhir bersama periodenya; perpanjangan lebih awal dijadwalkan untuk periode berikutnya.' },
    ];
</script>

<AppHead title="Dari ide ke naskah akademik">
    <meta name="description" content="Susun skripsi, tesis, karya ilmiah, dan artikel dengan Riset. Kelola referensi, tinjau draf AI beserta sitasi, lalu ekspor naskah ke Word." />
</AppHead>

<div class="landing min-h-dvh bg-paper text-ink">
    <a href="#main" class="skip-link">Lewati ke konten</a>
    <header class="border-b border-line bg-paper">
        <nav aria-label="Navigasi utama" class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8 lg:px-12">
            <Link href="/" aria-label="Riset, halaman awal" class="font-display text-[30px] font-medium tracking-tight"><AppLogo /></Link>
            <div class="hidden items-center gap-7 text-sm font-medium text-ink-2 md:flex"><a href="#fitur" class="hover:text-primary">Fitur</a><a href="#cara-kerja" class="hover:text-primary">Cara kerja</a><a href="#paket" class="hover:text-primary">Paket</a><a href="#faq" class="hover:text-primary">FAQ</a></div>
            <Link href={startUrl} class="btn btn-primary">{user ? 'Proyek saya' : 'Masuk'}<Icon name="external" size={15} /></Link>
        </nav>
        <nav aria-label="Navigasi bagian seluler" class="mobile-nav mx-5 flex justify-between gap-3 border-t border-line py-1 text-xs font-medium text-ink-2 md:hidden"><a href="#fitur">Fitur</a><a href="#cara-kerja">Cara kerja</a><a href="#paket">Paket</a><a href="#faq">FAQ</a></nav>
    </header>

    <main id="main" tabindex="-1">
        <section class="mx-auto grid max-w-7xl items-center gap-12 px-5 pt-14 pb-16 sm:px-8 sm:pt-20 lg:grid-cols-[1.05fr_1fr] lg:gap-16 lg:px-12 lg:py-24" aria-labelledby="hero-title">
            <div>
                <p class="mb-5 flex items-center gap-2 text-xs font-semibold tracking-[0.14em] text-primary uppercase"><span class="h-px w-7 bg-primary"></span>Ruang kerja untuk tulisan akademik</p>
                <h1 id="hero-title" class="font-display text-[46px] leading-[1.06] font-medium tracking-tight sm:text-[64px] lg:text-[72px]">Dari ide awal,<br />ke naskah<br /><span class="text-primary">yang terarah.</span></h1>
                <p class="mt-6 max-w-lg text-base leading-relaxed text-ink-2 sm:text-lg">Kumpulkan sumber, susun kerangka, dan kembangkan tulisan dengan bantuan AI. Periksa setiap bagian, lalu lanjutkan di Word.</p>
                <div class="mt-8 flex flex-wrap items-center gap-3"><Link href={startUrl} class="btn btn-primary min-h-12 px-6">{startLabel}<Icon name="pen" size={17} /></Link><a href={featuredPlanId ? '#paket' : '#cara-kerja'} class="btn btn-secondary min-h-12">{featuredPlanId ? 'Lihat paket Rp39.000' : 'Lihat cara kerja'}<Icon name="down" size={16} /></a></div>
                <p class="mt-4 text-xs leading-relaxed text-ink-3">Mulai dengan akun Anda. Pilih paket saat membutuhkan bantuan AI.</p>
                <div class="mt-9 flex flex-wrap gap-x-5 gap-y-2 border-t border-line pt-5 text-xs font-medium text-ink-2"><span>Skripsi</span><span>Tesis</span><span>Karya ilmiah</span><span>Artikel</span></div>
            </div>

            <div class="document-scene min-w-0" aria-label="Ilustrasi alur kerja Riset">
                <div class="flex items-center justify-between gap-3 px-1 pb-4 text-[11px] text-ink-3"><span class="font-mono uppercase tracking-wider">Dari sumber ke tulisan</span><span>Ilustrasi tampilan</span></div>
                <div class="document rounded-xl border border-line bg-surface p-5 shadow-[0_18px_50px_-25px_rgba(27,31,42,0.3)] sm:p-7">
                    <div class="flex items-start justify-between gap-4 border-b border-line pb-5"><div><p class="mb-2 text-[10px] font-semibold tracking-widest text-primary uppercase">Proyek · Artikel</p><h2 class="font-display text-[24px] leading-tight sm:text-[28px]">Media digital untuk<br />latihan berbicara</h2></div><span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary"><Icon name="file" size={21} /></span></div>
                    <div class="mt-5 flex flex-wrap gap-2"><span class="badge badge-ok"><Icon name="check" size={12} /> Referensi tersimpan</span><span class="badge">Kerangka siap</span></div>
                    <div class="mt-5 rounded-lg border border-ai-line bg-ai-wash p-4"><p class="mb-3 flex items-center gap-2 text-xs font-semibold text-ai"><span class="rounded border border-ai-line px-1 font-mono text-[10px]">AI</span> Usulan draf · perlu ditinjau</p><h3 class="font-display text-xl font-medium">1. Pendahuluan</h3><p class="mt-2 font-display text-base leading-relaxed">Latihan yang terarah dapat memberi siswa ruang untuk mengembangkan keterampilan berbicara <span class="text-primary">(Penulis, 2024)</span>.</p><p class="mt-3 text-[11px] text-ink-3">Contoh teks dan sitasi untuk ilustrasi.</p></div>
                    <div class="mt-5 flex items-center justify-between gap-4 border-t border-line pt-4"><span class="flex items-center gap-2 text-xs font-medium text-ink-2"><Icon name="book" size={15} /> Periksa sumber asli</span><span class="flex items-center gap-1.5 text-xs font-semibold text-primary">Tinjau & sunting<Icon name="pen" size={13} /></span></div>
                </div>
                <p class="mt-4 flex items-start gap-2 px-1 text-xs leading-relaxed text-ink-3"><Icon name="info" size={14} />AI membantu menulis. Keputusan akhir tetap di tangan Anda.</p>
            </div>
        </section>

        <section id="fitur" class="border-y border-line bg-surface" aria-labelledby="features-title">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-12 lg:py-20">
                <p data-reveal class="eyebrow mb-3">Satu ruang kerja</p><h2 data-reveal id="features-title" class="max-w-2xl font-display text-4xl leading-tight font-medium sm:text-5xl">Sumber, struktur, dan tulisan.<br /><span class="text-ink-3">Tetap saling terhubung.</span></h2>
                <div class="mt-10 grid gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">{#each features as feature, index (feature.title)}<article data-reveal style:transition-delay={`${index % 3 * 50}ms`} class="border-t border-line pt-5"><Icon name={feature.icon} size={23} class="mb-4 text-primary" /><h3 class="text-base font-semibold">{feature.title}</h3><p class="mt-2 text-sm leading-relaxed text-ink-2">{feature.description}</p></article>{/each}</div>
            </div>
        </section>

        <section id="cara-kerja" class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-12 lg:py-20" aria-labelledby="steps-title">
            <div class="flex flex-wrap items-end justify-between gap-5"><div><p class="eyebrow mb-3">Cara kerja</p><h2 data-reveal id="steps-title" class="font-display text-4xl leading-tight font-medium sm:text-5xl">Mulai kecil. Bangun per bagian.</h2></div><p class="max-w-sm text-sm leading-relaxed text-ink-2">Alur yang bisa Anda ikuti, dari ide yang masih umum sampai dokumen yang siap disunting.</p></div>
            <ol class="mt-10 grid gap-7 md:grid-cols-3">{#each steps as step, index (step.title)}<li data-reveal style:transition-delay={`${index * 50}ms`} class="border-t-2 border-primary pt-5"><span class="font-mono text-sm text-primary">0{index + 1}</span><h3 class="mt-4 font-display text-2xl font-medium">{step.title}</h3><p class="mt-2 text-sm leading-relaxed text-ink-2">{step.text}</p></li>{/each}</ol>
        </section>

        <section id="paket" class="border-y border-line bg-sunken/50" aria-labelledby="plans-title">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-12 lg:py-20">
                <div class="max-w-2xl"><p class="eyebrow mb-3">Paket & kredit</p><h2 data-reveal id="plans-title" class="font-display text-4xl leading-tight font-medium sm:text-5xl">Pilih ruang untuk berkembang.</h2><p class="mt-4 text-base leading-relaxed text-ink-2">Paket bulanan untuk bantuan AI. Menulis manual, melihat naskah, dan ekspor Word tetap tersedia tanpa paket aktif.</p></div>
                <div class="mt-9 grid gap-4 md:grid-cols-3">{#each plans as plan, index (plan.id)}<article data-reveal data-best-seller={plan.id === featuredPlanId ? 'true' : undefined} style:transition-delay={`${index * 50}ms`} class="flex min-w-0 flex-col rounded-xl border bg-surface p-6 {plan.id === featuredPlanId ? 'border-primary ring-2 ring-primary shadow-[0_12px_32px_-16px_rgba(47,75,168,0.35)]' : 'border-line'}"><span class="mb-4 self-start rounded-full px-3 py-1.5 text-xs font-semibold {plan.id === featuredPlanId ? 'bg-primary text-white' : 'bg-paper text-ink-2'}">{plan.id === featuredPlanId ? 'Best seller' : 'Bantuan sesuai kebutuhan'}</span><h3 class="font-display text-2xl font-medium">{plan.name}</h3><p class="mt-4 text-[30px] font-semibold tracking-tight">{money(plan.price)}<span class="ml-1 text-sm font-normal tracking-normal text-ink-3">/ bulan</span></p><p class="mt-2 text-sm font-medium">{plan.credits.toLocaleString('id-ID')} kredit per periode</p><ul class="my-6 flex flex-col gap-3 text-sm text-ink-2"><li class="flex items-start gap-2"><Icon name="check" size={15} class="mt-0.5 text-ok" />Bantuan AI untuk ide, sumber, dan tulisan</li><li class="flex items-start gap-2"><Icon name="check" size={15} class="mt-0.5 text-ok" />Tinjau dan sunting setiap hasil</li><li class="flex items-start gap-2"><Icon name="check" size={15} class="mt-0.5 text-ok" />Aktivasi setelah verifikasi admin</li></ul><Link href={user ? '/account/subscription' : '/masuk'} class="btn {plan.id === featuredPlanId ? 'btn-primary' : 'btn-secondary'} mt-auto">{plan.id === featuredPlanId ? 'Pilih paket Rp39.000' : user ? 'Ajukan paket' : 'Masuk untuk memilih paket'}</Link></article>{:else}<p class="rounded-lg border border-line bg-surface p-6 text-sm text-ink-2">Paket AI sedang disiapkan. Anda tetap dapat masuk dan mulai menulis secara manual.</p>{/each}</div>
                <p class="mt-5 max-w-3xl text-xs leading-relaxed text-ink-3">Kredit terpakai sesuai penggunaan AI, termasuk pembacaan sumber dan reasoning. Kredit bulanan berakhir bersama periodenya. Pembayaran dan aktivasi diperiksa manual oleh admin; pengajuan paket belum mengaktifkan akses.</p>
                {#if whatsapp}<a class="mt-4 inline-flex min-h-11 items-center gap-2 text-sm font-medium text-primary underline" href={`https://wa.me/${whatsapp}?text=${encodeURIComponent('Halo Admin, saya ingin bertanya tentang paket Riset.')}`} target="_blank" rel="noopener noreferrer">Tanya paket melalui WhatsApp<Icon name="external" size={14} /></a>{/if}
            </div>
        </section>

        <section id="faq" class="mx-auto grid max-w-7xl gap-8 px-5 py-16 sm:px-8 lg:grid-cols-[1fr_1.5fr] lg:gap-20 lg:px-12 lg:py-20" aria-labelledby="faq-title"><div><p class="eyebrow mb-3">Sebelum mulai</p><h2 data-reveal id="faq-title" class="font-display text-4xl leading-tight font-medium sm:text-5xl">Yang sering<br />ditanyakan.</h2><p class="mt-4 max-w-sm text-sm leading-relaxed text-ink-2">Kenali cara kerja Riset dan batas bantuan AI sebelum memulai tulisan Anda.</p></div><div>{#each faqs as faq (faq.question)}<details data-reveal class="group border-b border-line first:border-t"><summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-5 py-5 text-sm font-semibold"><span>{faq.question}</span><Icon name="plus" size={17} class="text-primary group-open:rotate-45" /></summary><p class="pb-5 pr-6 text-sm leading-relaxed text-ink-2">{faq.answer}</p></details>{/each}</div></section>

        <section class="mx-auto max-w-7xl px-5 pb-16 sm:px-8 lg:px-12"><div data-reveal class="flex flex-wrap items-center justify-between gap-7 rounded-xl bg-primary px-6 py-9 text-white sm:px-10 sm:py-12"><div><p class="mb-2 text-xs font-medium text-white/80">Tulisan besar dimulai dari satu langkah.</p><h2 class="font-display text-3xl leading-tight font-medium sm:text-4xl">Beri arah pada ide Anda.</h2></div><Link href={startUrl} class="btn border-white bg-white text-primary hover:bg-primary-soft min-h-12 px-6">{startLabel}<Icon name="pen" size={16} /></Link></div></section>
    </main>

    <footer class="border-t border-line"><div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-7 text-xs text-ink-3 sm:px-8 lg:px-12"><span class="font-display text-2xl font-medium text-ink"><AppLogo /></span><p>Ruang kerja akademik. Periksa sumber, tinjau tulisan.</p><Link href={startUrl} class="inline-flex min-h-11 items-center font-medium text-primary">{user ? 'Kembali ke proyek' : 'Masuk ke aplikasi'}</Link></div></footer>
</div>

<style>
    .landing { overflow-wrap: anywhere; --ease-out: cubic-bezier(0.23, 1, 0.32, 1); }
    :global(html.landing-scroll) { scroll-behavior: smooth; }
    [data-reveal] { transition: opacity 600ms var(--ease-out), transform 600ms var(--ease-out); }
    [data-reveal]:global([data-pending]) { opacity: 0; transform: translateY(8px); }
    [data-reveal]:global([data-pending]):focus-within { opacity: 1; transform: none; }
    @media (prefers-reduced-motion: reduce) {
        :global(html.landing-scroll) { scroll-behavior: auto; }
        [data-reveal] { transition: none !important; opacity: 1; transform: none; }
    }
    .mobile-nav a { display: inline-flex; min-height: 44px; align-items: center; }
    .landing section[id] { scroll-margin-top: 24px; }
    .landing h1, .landing h2 { text-wrap: balance; }
    .document { transform: rotate(-1.5deg); }
    .skip-link { position: absolute; left: 16px; top: -100px; z-index: 50; padding: 12px 18px; background: white; color: #2f4ba8; border: 2px solid currentColor; border-radius: 6px; }
    .skip-link:focus { top: 12px; }
    summary::-webkit-details-marker { display: none; }
    @media (max-width: 639px) { .document { transform: none; } }
</style>
