import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { App } from './App';
import { mockApi } from './test/mockApi';
import { makeTcc } from './test/fixtures';
import { TccSummary } from './components/TccSummary';
import { MemoryRouter } from 'react-router-dom';

describe('App', () => {
  it('sem sessão, abre no login', async () => {
    mockApi(() => ({ status: 401 }));
    window.history.pushState({}, '', '/repository');

    render(<App />);

    expect(await screen.findByRole('heading', { name: 'Entrar' })).toBeInTheDocument();
  });
});

describe('resumo do TCC', () => {
  it('mostra o PDF atual e as versões anteriores', () => {
    const doc = (version: number) => ({
      version,
      originalName: `v${version}.pdf`,
      sizeBytes: version === 2 ? 3 * 1024 * 1024 : 500,
      uploadedAt: '2026-10-0' + version + 'T10:00:00-03:00',
      downloadUrl: `/api/tcc/t1/documents/${version}/download`,
    });
    const tcc = makeTcc({ document: doc(2), documents: [doc(2), doc(1)] });

    render(
      <MemoryRouter>
        <TccSummary tcc={tcc} />
      </MemoryRouter>,
    );

    expect(screen.getByRole('link', { name: 'Abrir PDF (versão 2)' })).toHaveAttribute('href', '/api/tcc/t1/documents/2/download');
    expect(screen.getByText(/3\.0 MB/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Versão 1/ })).toHaveAttribute('href', '/api/tcc/t1/documents/1/download');
  });

  it('sem PDF', () => {
    render(<TccSummary tcc={makeTcc({ advisor: null })} showStudent={false} />);

    expect(screen.getByText(/Orientador: não definido/)).toBeInTheDocument();
  });
});
