// Contas de calendário para a página de prazos. Tudo em dias inteiros (UTC),
// a partir do AAAA-MM-DD que a API manda, para não depender do fuso do navegador.

const DAY = 86_400_000;

/** "2026-08-01T00:00:00-03:00" ou "2026-08-01" → dias desde 1970 */
export function dayNumber(iso: string): number {
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
  return Math.round(Date.UTC(y, m - 1, d) / DAY);
}

export function todayNumber(now = new Date()): number {
  return Math.round(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()) / DAY);
}

export function fromDayNumber(n: number): Date {
  return new Date(n * DAY);
}

/** "01/08/2026" a partir do AAAA-MM-DD (sem passar pelo fuso do navegador) */
export function formatDayOnly(iso: string) {
  const [year, month, day] = iso.slice(0, 10).split('-');
  return `${day}/${month}/${year}`;
}

/** "em 12 dias", "amanhã", "hoje", "há 3 dias" */
export function relativeDays(diff: number) {
  if (diff === 0) return 'hoje';
  if (diff === 1) return 'amanhã';
  if (diff === -1) return 'ontem';
  return diff > 0 ? `em ${diff} dias` : `há ${-diff} dias`;
}

export function plural(n: number, one: string, many: string) {
  return `${n} ${n === 1 ? one : many}`;
}

const MONTHS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

export interface MonthTick {
  day: number;
  label: string;
  year?: number;
}

/**
 * Janela da linha do tempo: do começo do mês do primeiro item ao fim do mês do último,
 * sem ir mais de 6 meses para trás nem 13 para a frente de hoje.
 */
export function timelineWindow(days: number[], today: number): { start: number; end: number; months: MonthTick[] } {
  const lo = Math.max(Math.min(today - 14, ...days), today - 183);
  const hi = Math.min(Math.max(today + 30, ...days), today + 400);
  const a = fromDayNumber(lo);
  const b = fromDayNumber(hi);
  const start = Math.round(Date.UTC(a.getUTCFullYear(), a.getUTCMonth(), 1) / DAY);
  const end = Math.round(Date.UTC(b.getUTCFullYear(), b.getUTCMonth() + 1, 1) / DAY);

  const months: MonthTick[] = [];
  let y = a.getUTCFullYear();
  let m = a.getUTCMonth();
  for (;;) {
    const day = Math.round(Date.UTC(y, m, 1) / DAY);
    if (day >= end) break;
    months.push({ day, label: MONTHS[m], year: m === 0 || months.length === 0 ? y : undefined });
    m += 1;
    if (m === 12) {
      m = 0;
      y += 1;
    }
  }
  return { start, end, months };
}
