import { describe, expect, it } from 'vitest';
import { mediaKind, mediaPath, parseAddVisitResult, parseAnswers } from './add-visit';

describe('add_visit data helpers', () => {
  it('keeps only yes / partial / no answers', () => {
    expect(parseAnswers({ g1: 'yes', g2: 'partial', g3: 'no', g4: 'maybe', g5: 1 })).toEqual({ g1: 'yes', g2: 'partial', g3: 'no' });
    expect(parseAnswers(null)).toEqual({});
    expect(parseAnswers(['yes'])).toEqual({});
  });

  it('parses the RPC answer and rejects anything else', () => {
    expect(parseAddVisitResult({ lead_id: 'l1', visit_id: 'v1', score: 52, stage_before: 'not_visited', existing: false })).toEqual({
      leadId: 'l1',
      visitId: 'v1',
      score: 52,
      stageBefore: 'not_visited',
      existing: false,
    });
    expect(parseAddVisitResult({ lead_id: 'l1', visit_id: 'v1', existing: true })).toMatchObject({ score: null, stageBefore: null, existing: true });
    expect(() => parseAddVisitResult({ lead_id: 'l1' })).toThrow();
  });

  it('stores files under owner/visit with their extension and kind', () => {
    const png = new File(['x'], 'front.PNG', { type: 'image/png' });
    const mov = new File(['x'], 'clip', { type: 'video/mp4' });
    expect(mediaPath('o1', 'v1', png, 'n1')).toBe('o1/v1/n1.png');
    expect(mediaPath('o1', 'v1', mov, 'n2')).toBe('o1/v1/n2.mp4');
    expect([mediaKind(png), mediaKind(mov), mediaKind(new File(['x'], 'a.m4a', { type: 'audio/mp4' }))]).toEqual(['photo', 'video', 'audio']);
  });
});
