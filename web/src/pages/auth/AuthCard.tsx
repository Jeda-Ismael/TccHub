import { ReactNode } from 'react';
import { GraduationCapIcon } from '../../components/icons/GraduationCapIcon';

/** Cartão branco sobre o fundo verde da marca, usado no login e no cadastro. */
export function AuthCard({ title, subtitle, children }: { title: string; subtitle: string; children: ReactNode }) {
  return (
    <div className="auth-screen">
      <main className="auth-card">
        <p className="auth-brand">
          <span className="brand-mark" aria-hidden="true">
            <GraduationCapIcon size={19} />
          </span>
          hubtcc
        </p>
        <h1>{title}</h1>
        <p className="auth-subtitle">{subtitle}</p>
        {children}
      </main>
    </div>
  );
}
