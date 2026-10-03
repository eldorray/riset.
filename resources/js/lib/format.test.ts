import { expect, test } from 'vite-plus/test';
import { errorMessage } from './format';

test('errorMessage menjelaskan penyebab galat', () => {
    const json = {
        response: {
            status: 502,
            data: '{"message":"Layanan AI menolak permintaan (HTTP 401)."}',
        },
    };
    expect(errorMessage(json, 'gagal')).toBe(
        'Layanan AI menolak permintaan (HTTP 401).',
    );
    expect(
        errorMessage({ response: { status: 429, data: '' } }, 'gagal'),
    ).toContain('Terlalu banyak');
    expect(
        errorMessage({ response: { status: 404, data: '<html>' } }, 'gagal'),
    ).toBe('gagal (HTTP 404)');
    expect(errorMessage({ code: 'ERR_NETWORK' }, 'gagal')).toContain(
        'Koneksi ke server terputus',
    );
    expect(errorMessage(undefined, 'gagal')).toBe('gagal');
});
