import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Toaster, toast } from './Toaster';

describe('avisos rápidos', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('mostra o aviso e some sozinho', () => {
    vi.useFakeTimers();
    render(<Toaster />);

    act(() => toast('Dados salvos.'));
    expect(screen.getByText('Dados salvos.')).toBeInTheDocument();

    act(() => {
      vi.advanceTimersByTime(4000);
    });
    expect(screen.queryByText('Dados salvos.')).not.toBeInTheDocument();
  });

  it('fecha no botão', async () => {
    render(<Toaster />);
    act(() => toast('Não deu certo.', 'error'));

    await userEvent.setup().click(screen.getByRole('button', { name: 'Fechar aviso' }));

    expect(screen.queryByText('Não deu certo.')).not.toBeInTheDocument();
  });
});
