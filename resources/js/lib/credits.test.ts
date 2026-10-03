import { expect, test } from 'vitest';
import { estimateCredits } from './credits';

test('estimates grow with document size and include all AI requests', () => {
    const small = estimateCredits(2000, 250, 1);
    const large = estimateCredits(20000, 250, 1);
    const batch = estimateCredits(2000, 250, 3);
    expect(large.max).toBeGreaterThan(small.max);
    expect(batch.min).toBe(small.min * 3);
    expect(batch.max).toBe(small.max * 3);
    expect(estimateCredits(2000, 250, 0)).toEqual({ min: 0, max: 0 });
});
