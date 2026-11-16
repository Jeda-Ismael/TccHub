import { useEffect, useState } from 'react';
import { AlertIcon, CheckCircleIcon } from './icons';

/**
 * Avisos rápidos ("Dados salvos.") no canto da tela. Qualquer tela chama
 * `toast('texto')`; o <Toaster /> (montado uma vez no App) mostra e some
 * sozinho depois de alguns segundos.
 */
export type ToastKind = 'success' | 'error';
type Toast = { id: number; text: string; kind: ToastKind };

const DURATION_MS = 4000;
let nextId = 1;
let toasts: Toast[] = [];
const listeners = new Set<(list: Toast[]) => void>();

function emit() {
  listeners.forEach((listener) => listener(toasts));
}

export function dismissToast(id: number) {
  toasts = toasts.filter((t) => t.id !== id);
  emit();
}

export function toast(text: string, kind: ToastKind = 'success') {
  const id = nextId++;
  toasts = [...toasts, { id, text, kind }];
  emit();
  setTimeout(() => dismissToast(id), DURATION_MS);
}

/** Usado nos testes, para um teste não ver o aviso do anterior. */
export function clearToasts() {
  toasts = [];
  emit();
}

export function Toaster() {
  const [list, setList] = useState<Toast[]>(toasts);

  useEffect(() => {
    listeners.add(setList);
    return () => {
      listeners.delete(setList);
    };
  }, []);

  return (
    <div className="toaster" role="status" aria-live="polite">
      {list.map((t) => (
        <div key={t.id} className={`toast toast-${t.kind}`}>
          {t.kind === 'success' ? <CheckCircleIcon size={18} /> : <AlertIcon size={18} />}
          <span>{t.text}</span>
          <button type="button" className="toast-close" onClick={() => dismissToast(t.id)} aria-label="Fechar aviso">
            ×
          </button>
        </div>
      ))}
    </div>
  );
}
