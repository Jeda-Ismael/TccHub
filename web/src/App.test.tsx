import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { App } from './App';
import { mockApi } from './test/mockApi';

describe('App', () => {
  it('sem sessão, abre no login', async () => {
    mockApi(() => ({ status: 401 }));
    window.history.pushState({}, '', '/repository');

    render(<App />);

    expect(await screen.findByRole('heading', { name: 'Entrar' })).toBeInTheDocument();
  });
});
