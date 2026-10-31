// A API manda datas no fuso da instituição (ex.: "2026-11-20T14:00:00-03:00").
// Aqui mostramos exatamente esse horário, sem converter para o fuso do
// navegador: a defesa é às 14h no local dela, esteja o usuário onde estiver.

const WEEKDAYS = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

function parts(iso: string) {
  const [date, time = '00:00'] = iso.slice(0, 16).split('T');
  const [year, month, day] = date.split('-').map(Number);
  return { year, month, day, time, dd: String(day).padStart(2, '0'), mm: String(month).padStart(2, '0') };
}

/** "20/11/2026" */
export function formatDate(iso: string) {
  const { dd, mm, year } = parts(iso);
  return `${dd}/${mm}/${year}`;
}

/** "20/11/2026, 14:00" */
export function formatDateTime(iso: string) {
  const { time } = parts(iso);
  return `${formatDate(iso)}, ${time}`;
}

/** "sexta-feira, 20/11/2026 às 14:00" */
export function formatLongDateTime(iso: string) {
  const { year, month, day, time } = parts(iso);
  const weekday = WEEKDAYS[new Date(Date.UTC(year, month - 1, day)).getUTCDay()];
  return `${weekday}, ${formatDate(iso)} às ${time}`;
}
