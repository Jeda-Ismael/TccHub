import { describe, expect, it } from 'vitest';
import { dayNumber, formatDayOnly, plural, relativeDays, timelineWindow } from './schedule';

describe('contas de calendário dos prazos', () => {
  it('lê a data sem passar pelo fuso do navegador', () => {
    expect(dayNumber('2026-08-01T23:59:59-03:00')).toBe(dayNumber('2026-08-01'));
    expect(formatDayOnly('2026-08-01T00:00:00-03:00')).toBe('01/08/2026');
  });

  it('descreve distâncias em dias', () => {
    expect(relativeDays(0)).toBe('hoje');
    expect(relativeDays(1)).toBe('amanhã');
    expect(relativeDays(-1)).toBe('ontem');
    expect(relativeDays(12)).toBe('em 12 dias');
    expect(relativeDays(-3)).toBe('há 3 dias');
    expect(plural(1, 'dia', 'dias')).toBe('1 dia');
  });

  it('janela vai do começo do mês do primeiro item ao fim do mês do último', () => {
    const today = dayNumber('2026-11-17');
    const { start, end, months } = timelineWindow([dayNumber('2026-10-30'), dayNumber('2027-02-20')], today);
    expect(start).toBe(dayNumber('2026-10-01'));
    expect(end).toBe(dayNumber('2027-03-01'));
    expect(months.map((m) => m.label)).toEqual(['out', 'nov', 'dez', 'jan', 'fev']);
    expect(months[0].year).toBe(2026);
    expect(months[3].year).toBe(2027);
  });

  it('não volta mais de seis meses', () => {
    const today = dayNumber('2026-11-17');
    const { start } = timelineWindow([dayNumber('2024-01-01')], today);
    expect(start).toBe(dayNumber('2026-05-01'));
  });
});
