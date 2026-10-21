import { ReactNode } from 'react';

/** Cartão cinza flutuando no centro, usado no login e no cadastro. */
export function AuthCard({ title, subtitle, children }: { title: string; subtitle: string; children: ReactNode }) {
  return (
    <div className="auth-screen">
      <main className="auth-card">
        <p className="auth-brand">hubtcc</p>
        <h1>{title}</h1>
        <p className="auth-subtitle">{subtitle}</p>
        {children}
      </main>
    </div>
  );
}
