/**
 * POST lalu unduh file. Server membalas file .docx bila berhasil, atau redirect
 * (dengan pesan flash) bila gagal — redirect dianggap gagal, file tidak ditawarkan.
 */
export async function postDownload(url: string): Promise<boolean> {
    const token = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    const response = await fetch(url, {
        method: 'POST',
        redirect: 'manual',
        credentials: 'same-origin',
        headers: { 'X-XSRF-TOKEN': decodeURIComponent(token ?? '') },
    });

    const type = response.headers.get('Content-Type') ?? '';

    if (!response.ok || !type.includes('officedocument')) {
        return false;
    }

    const name =
        /filename="?([^";]+)"?/.exec(
            response.headers.get('Content-Disposition') ?? '',
        )?.[1] ?? 'draf.docx';
    const link = document.createElement('a');
    link.href = URL.createObjectURL(await response.blob());
    link.download = name;
    link.click();
    URL.revokeObjectURL(link.href);

    return true;
}
